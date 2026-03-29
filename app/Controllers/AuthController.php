<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Models\EmailVerification;
use App\Models\User;
use App\Models\UserPasskey;
use App\Services\Logger;
use App\Services\Notifier;
use App\Services\WebAuthnService;
use Throwable;

final class AuthController extends Controller
{
    private ?array $jsonPayloadCache = null;
    private const EMAIL_VERIFICATION_TTL_SECONDS = 86400;
    private const EMAIL_VERIFICATION_RESEND_COOLDOWN_SECONDS = 60;

    public function showLogin(): void
    {
        $this->view('auth/login');
    }

    public function login(): void
    {
        verify_csrf();

        if (!empty($_SESSION['login_block_until']) && time() < (int) $_SESSION['login_block_until']) {
            Logger::warning('Login blocked due to repeated failures', ['email' => (string) ($_POST['email'] ?? '')]);
            Session::flash('error', 'Zu viele Fehlversuche. Bitte kurz warten und erneut versuchen.');
            $this->redirect('/login');
        }

        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';

        if (!$email || $password === '') {
            Logger::warning('Login failed due to invalid input');
            Session::flash('error', 'Bitte gültige Zugangsdaten angeben.');
            $this->redirect('/login');
        }

        $userModel = new User($this->db);
        $user = $userModel->findByEmail($email);

        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            $_SESSION['login_attempts'] = (int) ($_SESSION['login_attempts'] ?? 0) + 1;
            if ($_SESSION['login_attempts'] >= 5) {
                $_SESSION['login_block_until'] = time() + 180;
                $_SESSION['login_attempts'] = 0;
            }
            Logger::warning('Login failed: invalid credentials', ['email' => (string) $email]);
            Session::flash('error', 'Login fehlgeschlagen. Bitte prüfe E-Mail und Passwort.');
            $this->redirect('/login');
        }

        if (!$this->ensureUserCanLogin($user, true)) {
            return;
        }

        $_SESSION['login_attempts'] = 0;
        unset($_SESSION['login_block_until']);

        $this->loginUser($user);
        Logger::info('Login successful', ['user_id' => (int) $user['id'], 'role' => (string) $user['role'], 'method' => 'password']);
        $this->redirect('/dashboard');
    }

    public function passkeyAuthenticationOptions(): void
    {
        $this->verifyJsonCsrf();

        $service = new WebAuthnService();
        $challenge = $service->generateChallenge();

        $_SESSION['passkey_auth_challenge'] = $challenge;
        $_SESSION['passkey_auth_expires'] = time() + 300;

        $this->json([
            'challenge' => $challenge,
            'timeout' => 60000,
        ]);
    }

    public function passkeyAuthenticationVerify(): void
    {
        $this->verifyJsonCsrf();

        $payload = $this->jsonBody();
        $challenge = (string) ($_SESSION['passkey_auth_challenge'] ?? '');
        $expiresAt = (int) ($_SESSION['passkey_auth_expires'] ?? 0);
        if ($challenge === '' || time() > $expiresAt) {
            $this->json(['error' => 'Passkey-Anmeldung abgelaufen. Bitte erneut starten.'], 422);
        }

        $credentialId = (string) ($payload['credentialId'] ?? '');
        $clientDataJson = base64_decode((string) ($payload['clientDataJSON'] ?? ''), true);
        $authenticatorData = base64_decode((string) ($payload['authenticatorData'] ?? ''), true);
        $signature = base64_decode((string) ($payload['signature'] ?? ''), true);

        if ($credentialId === '' || !$clientDataJson || !$authenticatorData || !$signature) {
            $this->json(['error' => 'Ungültige Passkey-Antwort.'], 422);
        }

        $passkeyModel = new UserPasskey($this->db);
        $passkey = $passkeyModel->findByCredentialId($credentialId);
        if (!$passkey) {
            $this->json(['error' => 'Passkey nicht bekannt.'], 422);
        }

        $user = (new User($this->db))->findById((int) $passkey['user_id']);
        if (!$user) {
            $this->json(['error' => 'Benutzer nicht gefunden.'], 422);
        }

        if (!$this->ensureUserCanLogin($user, false)) {
            return;
        }

        $service = new WebAuthnService();
        $clientData = json_decode($clientDataJson, true);
        $parsedAuthData = $service->parseAuthenticatorData($authenticatorData);
        $publicKeyDer = $service->base64UrlDecode((string) $passkey['public_key_spki']);

        if (!is_array($clientData) || !$parsedAuthData) {
            $this->json(['error' => 'Passkey-Daten konnten nicht geprüft werden.'], 422);
        }

        $origin = $this->expectedOrigin();
        $rpId = parse_url($origin, PHP_URL_HOST) ?: 'localhost';

        if (
            !$service->verifyType($clientData, 'webauthn.get')
            || !$service->verifyChallenge($clientData, $challenge)
            || !$service->verifyOrigin($clientData, $origin)
            || !$service->verifyRpIdHash((string) $parsedAuthData['rp_id_hash'], (string) $rpId)
            || !$service->isUserPresent((int) $parsedAuthData['flags'])
            || !$service->verifyAssertionSignature($authenticatorData, $clientDataJson, $signature, $publicKeyDer)
        ) {
            $this->json(['error' => 'Passkey-Prüfung fehlgeschlagen.'], 422);
        }

        if ((int) $parsedAuthData['sign_count'] > 0 && (int) $passkey['sign_count'] > 0 && (int) $parsedAuthData['sign_count'] <= (int) $passkey['sign_count']) {
            $this->json(['error' => 'Passkey-Sicherheitsprüfung fehlgeschlagen.'], 422);
        }

        $passkeyModel->touchUsage((int) $passkey['id'], (int) $parsedAuthData['sign_count']);

        unset($_SESSION['passkey_auth_challenge'], $_SESSION['passkey_auth_expires']);
        $_SESSION['login_attempts'] = 0;
        unset($_SESSION['login_block_until']);

        $this->loginUser($user);
        Logger::info('Login successful', ['user_id' => (int) $user['id'], 'role' => (string) $user['role'], 'method' => 'passkey']);

        $this->json(['success' => true, 'redirect' => app_base_path($this->config) . '/dashboard']);
    }

    public function showRegister(): void
    {
        $this->view('auth/register');
    }

    public function register(): void
    {
        verify_csrf();

        $name = trim($_POST['name'] ?? '');
        $displayName = trim($_POST['display_name'] ?? '');
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';

        if ($name === '' || $displayName === '' || !$email || strlen($password) < 10) {
            Session::flash('error', 'Bitte alle Pflichtfelder korrekt ausfüllen (Passwort min. 10 Zeichen).');
            $this->redirect('/register');
        }

        $userModel = new User($this->db);
        if ($userModel->findByEmail($email)) {
            Session::flash('error', 'E-Mail ist bereits registriert.');
            $this->redirect('/register');
        }

        $userId = $userModel->create([
            'name' => $name,
            'display_name' => $displayName,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'phone' => trim($_POST['phone'] ?? ''),
            'location' => trim($_POST['location'] ?? ''),
            'bio' => trim($_POST['bio'] ?? ''),
            'role' => 'member',
            'approval_status' => 'pending',
            'email_verified_at' => null,
        ]);

        $mailSent = $this->sendVerificationMail($userId, $email, $displayName);

        Logger::info('User registered', ['email' => (string) $email, 'verification_mail_sent' => $mailSent]);
        if ($mailSent) {
            Session::flash('success', 'Deine Registrierung war erfolgreich. Wir haben dir eine E-Mail mit einem Bestätigungslink gesendet. Bitte prüfe dein Postfach. Anschließend muss dein Konto ggf. noch von einem Administrator freigegeben werden.');
        } else {
            Session::flash('error', 'Dein Konto wurde erstellt, aber die Verifikations-E-Mail konnte derzeit nicht versendet werden. Bitte versuche es erneut oder kontaktiere den Support.');
        }
        $this->redirect('/login');
    }

    public function showResendVerification(): void
    {
        $this->view('auth/resend-verification', [
            'prefillEmail' => $_SESSION['verification_email_hint'] ?? '',
        ]);
        unset($_SESSION['verification_email_hint']);
    }

    public function resendVerification(): void
    {
        verify_csrf();

        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        if ($email) {
            $user = (new User($this->db))->findByEmail($email);
            if ($user && empty($user['email_verified_at'])) {
                $verificationModel = new EmailVerification($this->db);
                if (!$verificationModel->hasRecentOpenToken((int) $user['id'], self::EMAIL_VERIFICATION_RESEND_COOLDOWN_SECONDS)) {
                    $sent = $this->sendVerificationMail((int) $user['id'], (string) $user['email'], (string) $user['display_name']);
                    if (!$sent) {
                        Session::flash('error', 'Der Bestätigungslink konnte aktuell nicht versendet werden. Bitte versuche es später erneut.');
                        $this->redirect('/verification/resend');
                    }
                }
            }
        }

        Session::flash('success', 'Falls ein unverifiziertes Konto für diese E-Mail-Adresse existiert, wurde ein neuer Bestätigungslink versendet.');
        $this->redirect('/login');
    }

    public function verifyEmail(): void
    {
        $token = trim($_GET['token'] ?? '');
        if ($token === '') {
            Session::flash('error', 'Dieser Bestätigungslink ist ungültig oder abgelaufen. Bitte fordere einen neuen Link an.');
            $this->redirect('/verification/resend');
        }

        $tokenHash = hash('sha256', $token);
        $verificationModel = new EmailVerification($this->db);
        $verification = $verificationModel->consumeValidToken($tokenHash);

        if (!$verification) {
            Session::flash('error', 'Dieser Bestätigungslink ist ungültig oder abgelaufen. Bitte fordere einen neuen Link an.');
            $this->redirect('/verification/resend');
        }

        $userModel = new User($this->db);
        $applied = $userModel->applyVerifiedPendingEmail((int) $verification['user_id'], (string) $verification['email']);

        if (!$applied) {
            $userModel->markEmailVerified((int) $verification['user_id']);
            Session::flash('success', 'Deine E-Mail-Adresse wurde erfolgreich bestätigt. Du kannst dich jetzt anmelden.');
            $this->redirect('/login');
        }

        if ((int) ($_SESSION['user_id'] ?? 0) === (int) $verification['user_id']) {
            Session::flash('success', 'Deine neue E-Mail-Adresse wurde erfolgreich bestätigt.');
            $this->redirect('/settings');
        }

        Session::flash('success', 'Deine neue E-Mail-Adresse wurde erfolgreich bestätigt. Du kannst dich jetzt anmelden.');
        $this->redirect('/login');
    }

    public function showForgotPassword(): void
    {
        $this->view('auth/forgot-password');
    }

    public function sendReset(): void
    {
        verify_csrf();
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        if (!$email) {
            $this->redirect('/password/forgot');
        }

        $userModel = new User($this->db);
        $user = $userModel->findByEmail($email);
        if ($user) {
            $token = bin2hex(random_bytes(24));
            $userModel->setResetToken((int) $user['id'], hash('sha256', $token), date('Y-m-d H:i:s', time() + 3600));

            $resetLink = $this->buildAbsolutePath('/password/reset?token=' . urlencode($token));
            (new Notifier($this->db, $this->config))->notifyEmail(
                (int) $user['id'],
                'Passwort zurücksetzen',
                "Hallo {$user['display_name']},\n\nbitte setze dein Passwort über folgenden Link zurück:\n{$resetLink}\n\nDer Link ist 60 Minuten gültig."
            );
        }

        \App\Core\Session::flash('success', 'Wenn die E-Mail existiert, wurde ein Reset-Link versendet.');

        $this->redirect('/login');
    }

    public function showResetPassword(): void
    {
        $token = $_GET['token'] ?? '';
        if ($token === '') {
            $this->redirect('/login');
        }
        $this->view('auth/reset-password', ['token' => $token]);
    }

    public function resetPassword(): void
    {
        verify_csrf();
        $token = trim($_POST['token'] ?? '');
        $password = $_POST['password'] ?? '';

        if (strlen($password) < 10 || $token === '') {
            Session::flash('error', 'Ungültige Eingabe.');
            $this->redirect('/login');
        }

        $userModel = new User($this->db);
        $user = $userModel->findByResetToken(hash('sha256', $token));
        if (!$user) {
            Session::flash('error', 'Reset-Link ist ungültig oder abgelaufen.');
            $this->redirect('/login');
        }

        $userModel->updatePassword((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        Session::flash('success', 'Passwort wurde aktualisiert.');
        $this->redirect('/login');
    }

    public function logout(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        Logger::info('Logout', ['user_id' => $userId]);

        $_SESSION = [];
        session_destroy();
        $this->redirect('/login');
    }

    private function sendVerificationMail(int $userId, string $email, string $displayName): bool
    {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + self::EMAIL_VERIFICATION_TTL_SECONDS);

        (new EmailVerification($this->db))->issueToken($userId, $email, $tokenHash, $expiresAt);

        $verifyLink = $this->buildAbsolutePath('/verify-email?token=' . urlencode($token));

        $messageText = "Hallo {$displayName},\n\n";
        $messageText .= "bitte bestätige deine E-Mail-Adresse, um dein Konto zu aktivieren.\n";
        $messageText .= "Klicke dazu auf den folgenden Link:\n{$verifyLink}\n\n";
        $messageText .= 'Der Link ist 24 Stunden gültig. Falls du dich nicht registriert hast, kannst du diese E-Mail ignorieren.';

        $safeLink = e($verifyLink);
        $safeName = e($displayName);
        $messageHtml = <<<HTML
<!doctype html>
<html lang="de">
  <body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <p>Hallo {$safeName},</p>
    <p>bitte bestätige deine E-Mail-Adresse, um dein Konto zu aktivieren.</p>
    <p>
      <a href="{$safeLink}" style="display:inline-block;background:#2563eb;color:#ffffff;padding:10px 16px;border-radius:6px;text-decoration:none;">E-Mail-Adresse bestätigen</a>
    </p>
    <p>Falls der Button nicht funktioniert, nutze bitte diesen Link:</p>
    <p><a href="{$safeLink}">{$safeLink}</a></p>
    <p>Der Link ist 24 Stunden gültig.</p>
    <p>Falls du dich nicht registriert hast, kannst du diese E-Mail ignorieren.</p>
  </body>
</html>
HTML;

        $verificationModel = new EmailVerification($this->db);

        try {
            $sent = (new Notifier($this->db, $this->config))->notifyEmail($userId, 'Bitte bestätige deine E-Mail-Adresse', $messageText, $messageHtml);
            if (!$sent) {
                $verificationModel->invalidateOpenTokens($userId);
                Logger::error('Sending verification email failed', ['user_id' => $userId, 'email' => $email, 'reason' => 'smtp_delivery_failed']);
            }

            return $sent;
        } catch (Throwable $exception) {
            $verificationModel->invalidateOpenTokens($userId);
            Logger::error('Sending verification email failed', ['user_id' => $userId, 'email' => $email, 'exception' => $exception->getMessage()]);

            return false;
        }
    }

    private function ensureUserCanLogin(array $user, bool $redirectWithFlash): bool
    {
        if (empty($user['email_verified_at'])) {
            if ($redirectWithFlash) {
                Session::flash('error', 'Deine E-Mail-Adresse wurde noch nicht bestätigt. Bitte prüfe dein Postfach oder fordere einen neuen Bestätigungslink an.');
                $_SESSION['verification_email_hint'] = (string) $user['email'];
                $this->redirect('/verification/resend');
            }

            $this->json(['error' => 'E-Mail-Adresse nicht bestätigt.'], 403);
        }

        $approvalStatus = $user['approval_status'] ?? 'approved';
        if ($approvalStatus === 'pending') {
            if ($redirectWithFlash) {
                Session::flash('error', 'Dein Konto wurde registriert und wartet noch auf die Freigabe durch einen Administrator.');
                $this->redirect('/login');
            }

            $this->json(['error' => 'Konto wartet auf Freigabe.'], 403);
        }

        if ($approvalStatus === 'rejected') {
            if ($redirectWithFlash) {
                Session::flash('error', 'Deine Registrierung wurde aktuell nicht freigegeben. Bitte kontaktiere den Administrator.');
                $this->redirect('/login');
            }

            $this->json(['error' => 'Konto nicht freigegeben.'], 403);
        }

        return true;
    }

    private function loginUser(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['role'] = (string) $user['role'];
        $_SESSION['display_name'] = (string) $user['display_name'];
    }

    private function expectedOrigin(): string
    {
        $scheme = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $scheme . '://' . $host;
    }

    private function buildAbsolutePath(string $path): string
    {
        return $this->expectedOrigin() . app_base_path($this->config) . $path;
    }

    private function verifyJsonCsrf(): void
    {
        $payload = $this->jsonBody();
        $token = (string) ($payload['_csrf'] ?? '');
        if (!hash_equals((string) ($_SESSION['_csrf'] ?? ''), $token)) {
            $this->json(['error' => 'CSRF-Token ungültig.'], 419);
        }
    }

    private function jsonBody(): array
    {
        if ($this->jsonPayloadCache !== null) {
            return $this->jsonPayloadCache;
        }

        $raw = file_get_contents('php://input');
        $data = json_decode((string) $raw, true);
        $this->jsonPayloadCache = is_array($data) ? $data : [];

        return $this->jsonPayloadCache;
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
