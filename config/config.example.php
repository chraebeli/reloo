<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'Reloo Sharing Kommune',
        'base_path' => '/sharing-app',
        'session_name' => 'reloo_session',
        'mail_from' => 'noreply@deine-domain.de',
    ],
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'deine_datenbank',
        'username' => 'dein_benutzer',
        'password' => 'dein_passwort',
        'charset' => 'utf8mb4',
    ],
    'mail' => [
        'driver' => 'smtp', // smtp oder mail
        'smtp_host' => 'smtp.deine-domain.de',
        'smtp_port' => 587,
        'smtp_user' => 'noreply@deine-domain.de',
        'smtp_pass' => 'dein_smtp_passwort',
        'smtp_encryption' => 'tls', // tls, ssl oder none
        'smtp_timeout' => 15,
        'smtp_from_name' => 'Reloo',
        'smtp_from_email' => 'noreply@deine-domain.de',
    ],
];
