<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

abstract class Controller
{
    public function __construct(protected PDO $db, protected array $config)
    {
    }

    protected function view(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $config = $this->config;
        require __DIR__ . '/../../views/' . $view . '.php';
    }

    protected function redirect(string $path): void
    {
        $base = rtrim($this->config['app']['base_path'], '/');
        header('Location: ' . $base . $path);
        exit;
    }

    protected function requireAuth(): void
    {
        if (empty($_SESSION['user_id'])) {
            Session::flash('error', 'Bitte zuerst einloggen.');
            $this->redirect('/login');
        }
    }

    protected function requireAdmin(): void
    {
        $this->requireAuth();
        if (($_SESSION['role'] ?? 'member') !== 'admin') {
            http_response_code(403);
            exit('Keine Berechtigung.');
        }
    }
}
