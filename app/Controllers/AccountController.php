<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Models\EmailVerification;
use App\Models\User;
use App\Models\UserPasskey;
use App\Services\Notifier;
use App\Services\WebAuthnService;
use Throwable;

final class AccountController extends Controller
{
    private ?array $jsonPayloadCache = null;
    private const EMAIL_VERIFICATION_TTL_SECONDS = 86400;
    private const EMAIL_VERIFICATION_RESEND_COOLDOWN_SECONDS = 60;

    public function index(): void
    {
        $this->requireAuth();

        $user = (new User($this->db))->findById((int) $_SESSION['user_id']);
        if (!$user) {
            $this->redirect('/logout');
        }

        $passkeys = (new UserPasskey($this->db))->listByUserId((int) $user['id']);

        $this->view('account/settings', ['user' => $user, 'passkeys' => $passkeys]);
    }

    public function updateDisplayName(): void
    {
        $this->requireAuth();
        verify_csrf();

        $displayName = trim((string) ($_POST['display_name'] ?? ''));
        if ($displayName === '' || mb_strlen($displayName) < 2 || mb_strlen($displayName) > 120) {
            Session::flash('error', 'Bitte gib einen Anzeige-Namen mit 2 bis 120 Zeichen ein.');
            $this->redirect('/settings');
        }

        (new User($this->db))->updateDisplayName((int) $_SESSION['user_id'], $displayName);
        $_SESSION['display_name'] = $displayName;

        Session::flash('success', 'Dein Anzeige-Name wurde aktualisiert.');
        $this->redirect('/settings');
    }

    public function updateEmail(): void
    {
        $this->requireAuth();
        verify_csrf();

        $newEmail = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        if (!$newEmail) {
            Session::flash('error', 'Bitte gib eine gültige E-Mail-Adresse an.');
            $this->redirect('/settings');
        }

        $userModel = new User($this->db);
        if (!$userModel->pendingEmailFeatureEnabled()) {
            Session::flash('error', 'E-Mail-Änderung ist erst nach Einspielen der neuesten Datenbank-Migration verfügbar.');
            $this->redirect('/settings');
        }
        $user = $userModel->findById((int) $_SESSION['user_id']);
        if (!$user) {
            $this->redirect('/logout');
        }

        if (hash_equals((string) $user['email'], (string) $newEmail)) {
            Session::flash('success', 'Diese E-Mail-Adresse ist bereits hinterlegt.');
            $this->redirect('/settings');
        }

        if ($userModel->isEmailInUse((string) $newEmail, (int) $user['id'])) {
            Session::flash('error', 'Diese E-Mail-Adresse ist bereits vergeben.');
            $this->redirect('/settings');
        }

        $userModel->setPendingEmail((int) $user['id'], (string) $newEmail);

        if (!$this->sendPendingEmailVerification((int) $user['id'], (string) $newEmail, (string) $user['display_name'])) {
            Session::flash('error', 'Bestätigungslink konnte gerade nicht versendet werden. Bitte später erneut versuchen.');
            $this->redirect('/settings');
        }

        Session::flash('success', 'Wir haben dir einen Bestätigungslink an deine neue E-Mail-Adresse gesendet.');
        $this->redirect('/settings');
    }

    public function resendPendingEmail(): void
    {
        $this->requireAuth();
        verify_csrf();

        $userModel = new User($this->db);
        if (!$userModel->pendingEmailFeatureEnabled()) {
            Session::flash('error', 'E-Mail-Änderung ist erst nach Einspielen der neuesten Datenbank-Migration verfügbar.');
            $this->redirect('/settings');
        }

        $user = $userModel->findById((int) $_SESSION['user_id']);
        if (!$user || empty($user['pending_email'])) {
            Session::flash('error', 'Es ist aktuell keine E-Mail-Änderung offen.');
            $this->redirect('/settings');
        }

        $verificationModel = new EmailVerification($this->db);
        if ($verificationModel->hasRecentOpenToken((int) $user['id'], self::EMAIL_VERIFICATION_RESEND_COOLDOWN_SECONDS)) {
            Session::flash('success', 'Der letzte Bestätigungslink ist noch sehr frisch. Bitte prüfe dein Postfach.');
            $this->redirect('/settings');
        }

        if (!$this->sendPendingEmailVerification((int) $user['id'], (string) $user['pending_email'], (string) $user['display_name'])) {
            Session::flash('error', 'Bestätigungslink konnte gerade nicht versendet werden. Bitte später erneut versuchen.');
            $this->redirect('/settings');
        }

        Session::flash('success', 'Wir haben dir einen neuen Bestätigungslink gesendet.');
        $this->redirect('/settings');
    }

    public function changePassword(): void
    {
        $this->requireAuth();
        verify_csrf();

        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $newPasswordConfirm = (string) ($_POST['new_password_confirm'] ?? '');

        if ($currentPassword === '' || $newPassword === '' || $newPasswordConfirm === '') {
            Session::flash('error', 'Bitte fülle alle Passwortfelder aus.');
            $this->redirect('/settings');
        }

        if ($newPassword !== $newPasswordConfirm) {
            Session::flash('error', 'Die neue Passwort-Bestätigung stimmt nicht überein.');
            $this->redirect('/settings');
        }

        if (strlen($newPassword) < 10 || strlen($newPassword) > 255) {
            Session::flash('error', 'Das neue Passwort muss zwischen 10 und 255 Zeichen lang sein.');
            $this->redirect('/settings');
        }

        $user = (new User($this->db))->findById((int) $_SESSION['user_id']);
        if (!$user || !password_verify($currentPassword, (string) $user['password_hash'])) {
            Session::flash('error', 'Das aktuelle Passwort ist nicht korrekt.');
            $this->redirect('/settings');
        }

        if (password_verify($newPassword, (string) $user['password_hash'])) {
            Session::flash('error', 'Das neue Passwort muss sich vom aktuellen unterscheiden.');
            $this->redirect('/settings');
        }

        (new User($this->db))->updatePassword((int) $user['id'], password_hash($newPassword, PASSWORD_DEFAULT));

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['role'] = (string) $user['role'];
        $_SESSION['display_name'] = (string) $user['display_name'];

        Session::flash('success', 'Dein Passwort wurde erfolgreich geändert.');
        $this->redirect('/settings');
    }

    public function passkeyOptions(): void
    {
        $this->requireAuth();
        $this->verifyJsonCsrf();

        $service = new WebAuthnService();
        $challenge = $service->generateChallenge();
        $_SESSION['passkey_register_challenge'] = $challenge;
        $_SESSION['passkey_register_expires'] = time() + 300;

        $credentialIds = (new UserPasskey($this->db))->listCredentialIdsByUserId((int) $_SESSION['user_id']);
        $excludeCredentials = [];
        foreach ($credentialIds as $id) {
            $excludeCredentials[] = ['type' => 'public-key', 'id' => $id];
        }

        $this->json(['challenge' => $challenge, 'excludeCredentials' => $excludeCredentials]);
    }

    public function passkeyRegister(): void
    {
        $this->requireAuth();
        $this->verifyJsonCsrf();

        $payload = $this->jsonBody();
        $challenge = (string) ($_SESSION['passkey_register_challenge'] ?? '');
        $expiresAt = (int) ($_SESSION['passkey_register_expires'] ?? 0);

        if ($challenge === '' || time() > $expiresAt) {
            $this->json(['error' => 'Registrierung abgelaufen. Bitte erneut starten.'], 422);
        }

        $label = trim((string) ($payload['label'] ?? 'Mein Passkey'));
        if ($label === '') {
            $label = 'Mein Passkey';
        }
        $label = mb_substr($label, 0, 120);

        $clientDataJson = base64_decode((string) ($payload['clientDataJSON'] ?? ''), true);
        $credentialId = (string) ($payload['credentialId'] ?? '');
        $publicKey = base64_decode((string) ($payload['publicKey'] ?? ''), true);

        if (!$clientDataJson || $credentialId === '' || !$publicKey) {
            $this->json(['error' => 'Ungültige Passkey-Daten.'], 422);
        }

        $clientData = json_decode($clientDataJson, true);
        if (!is_array($clientData)) {
            $this->json(['error' => 'Ungültige Clientdaten.'], 422);
        }

        $service = new WebAuthnService();
        $origin = $this->expectedOrigin();
        if (!$service->verifyType($clientData, 'webauthn.create') || !$service->verifyChallenge($clientData, $challenge) || !$service->verifyOrigin($clientData, $origin)) {
            $this->json(['error' => 'Passkey-Registrierung konnte nicht verifiziert werden.'], 422);
        }

        $model = new UserPasskey($this->db);
        if ($model->findByCredentialId($credentialId)) {
            $this->json(['error' => 'Dieser Passkey ist bereits hinterlegt.'], 422);
        }

        $model->create([
            'user_id' => (int) $_SESSION['user_id'],
            'label' => $label,
            'credential_id' => $credentialId,
            'public_key_spki' => $service->base64UrlEncode($publicKey),
            'sign_count' => 0,
            'transports' => json_encode($payload['transports'] ?? [], JSON_UNESCAPED_UNICODE),
        ]);

        unset($_SESSION['passkey_register_challenge'], $_SESSION['passkey_register_expires']);

        $this->json(['success' => true]);
    }

    public function removePasskey(): void
    {
        $this->requireAuth();
        verify_csrf();

        $passkeyId = (int) ($_POST['passkey_id'] ?? 0);
        if ($passkeyId <= 0) {
            Session::flash('error', 'Ungültiger Passkey.');
            $this->redirect('/settings');
        }

        $deleted = (new UserPasskey($this->db))->deleteForUser($passkeyId, (int) $_SESSION['user_id']);
        Session::flash($deleted ? 'success' : 'error', $deleted ? 'Passkey wurde entfernt.' : 'Passkey konnte nicht entfernt werden.');
        $this->redirect('/settings');
    }

    private function sendPendingEmailVerification(int $userId, string $email, string $displayName): bool
    {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + self::EMAIL_VERIFICATION_TTL_SECONDS);

        (new EmailVerification($this->db))->issueToken($userId, $email, $tokenHash, $expiresAt);

        $verifyLink = $this->buildAbsolutePath('/verify-email?token=' . urlencode($token));

        $messageText = "Hallo {$displayName},\n\n";
        $messageText .= "bitte bestätige deine neue E-Mail-Adresse über folgenden Link:\n{$verifyLink}\n\n";
        $messageText .= 'Der Link ist 24 Stunden gültig.';

        try {
            return (new Notifier($this->db, $this->config))->notifyEmail($userId, 'Neue E-Mail-Adresse bestätigen', $messageText);
        } catch (Throwable) {
            return false;
        }
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
