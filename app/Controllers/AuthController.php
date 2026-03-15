<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Models\User;
use App\Services\Notifier;

final class AuthController extends Controller
{
    public function showLogin(): void
    {
        $this->view('auth/login');
    }

    public function login(): void
    {
        verify_csrf();

        if (!empty($_SESSION['login_block_until']) && time() < (int) $_SESSION['login_block_until']) {
            Session::flash('error', 'Zu viele Fehlversuche. Bitte kurz warten und erneut versuchen.');
            $this->redirect('/login');
        }

        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';

        if (!$email || $password === '') {
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
            Session::flash('error', 'Login fehlgeschlagen.');
            $this->redirect('/login');
        }

        $_SESSION['login_attempts'] = 0;
        unset($_SESSION['login_block_until']);

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['display_name'] = $user['display_name'];

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

        $userModel->create([
            'name' => $name,
            'display_name' => $displayName,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'phone' => trim($_POST['phone'] ?? ''),
            'location' => trim($_POST['location'] ?? ''),
            'bio' => trim($_POST['bio'] ?? ''),
            'role' => 'member',
        ]);

        Session::flash('success', 'Registrierung erfolgreich. Bitte einloggen.');
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

            $scheme = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $resetLink = $scheme . '://' . $host . app_base_path($this->config) . '/password/reset?token=' . urlencode($token);
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
        $_SESSION = [];
        session_destroy();
        $this->redirect('/login');
    }
}
