<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Models\EmailVerification;
use App\Models\User;
use App\Services\Logger;
use App\Services\Notifier;
use Throwable;

final class AuthController extends Controller
{
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

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $_SESSION['login_attempts'] = (int) ($_SESSION['login_attempts'] ?? 0) + 1;
            if ($_SESSION['login_attempts'] >= 5) {
                $_SESSION['login_block_until'] = time() + 180;
                $_SESSION['login_attempts'] = 0;
            }
            Logger::warning('Login failed: invalid credentials', ['email' => (string) $email]);
            Session::flash('error', 'Login fehlgeschlagen. Bitte prüfe E-Mail und Passwort.');
            $this->redirect('/login');
        }

        if (empty($user['email_verified_at'])) {
            Logger::info('Login denied: email not verified', ['user_id' => (int) $user['id']]);
            Session::flash('error', 'Deine E-Mail-Adresse wurde noch nicht bestätigt. Bitte prüfe dein Postfach oder fordere einen neuen Bestätigungslink an.');
            $_SESSION['verification_email_hint'] = (string) $user['email'];
            $this->redirect('/verification/resend');
        }

        $approvalStatus = $user['approval_status'] ?? 'approved';
        if ($approvalStatus === 'pending') {
            Logger::info('Login denied: account pending approval', ['user_id' => (int) $user['id']]);
            Session::flash('error', 'Dein Konto wurde registriert und wartet noch auf die Freigabe durch einen Administrator.');
            $this->redirect('/login');
        }

        if ($approvalStatus === 'rejected') {
            Logger::warning('Login denied: account rejected', ['user_id' => (int) $user['id']]);
            Session::flash('error', 'Deine Registrierung wurde aktuell nicht freigegeben. Bitte kontaktiere den Administrator.');
            $this->redirect('/login');
        }

        $_SESSION['login_attempts'] = 0;
        unset($_SESSION['login_block_until']);

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['display_name'] = $user['display_name'];

        Logger::info('Login successful', ['user_id' => (int) $user['id'], 'role' => (string) $user['role']]);

        $this->redirect('/dashboard');
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

        (new User($this->db))->markEmailVerified((int) $verification['user_id']);

        Session::flash('success', 'Deine E-Mail-Adresse wurde erfolgreich bestätigt. Du kannst dich jetzt anmelden.');
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

    private function buildAbsolutePath(string $path): string
    {
        $scheme = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $scheme . '://' . $host . app_base_path($this->config) . $path;
    }
}
