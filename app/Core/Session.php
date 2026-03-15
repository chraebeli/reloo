<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(array $appConfig): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name($appConfig['session_name']);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();

        if (!isset($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = time();
        }
    }

    public static function flash(string $key, ?string $message = null): ?string
    {
        if ($message !== null) {
            $_SESSION['_flash'][$key] = $message;
            return null;
        }

        if (!isset($_SESSION['_flash'][$key])) {
            return null;
        }

        $msg = $_SESSION['_flash'][$key];
        unset($_SESSION['_flash'][$key]);

        return $msg;
    }
}
