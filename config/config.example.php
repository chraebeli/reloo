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
        'driver' => 'mail',
        'smtp_host' => '',
        'smtp_port' => 587,
        'smtp_user' => '',
        'smtp_pass' => '',
        'smtp_from_name' => 'Reloo',
    ],
];
