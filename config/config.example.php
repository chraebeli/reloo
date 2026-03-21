<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'Reloo Sharing Kommune',
        'base_path' => '/sharing-app',
        'session_name' => 'reloo_session',
        'mail_from' => 'notify@reloo.ch',
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
        'host' => 'mandela.sui-inter.net',
        'port' => 465,
        'encryption' => 'ssl', // ssl, tls oder none
        'auth' => true,
        'username' => 'notify@reloo.ch',
        'password' => getenv('RELOO_SMTP_PASSWORD') ?: '', // Passwort hier oder per ENV setzen
        'from_address' => 'notify@reloo.ch',
        'from_name' => 'Reloo',
        'ehlo_domain' => 'reloo.ch',
        'timeout' => 15,
    ],
];
