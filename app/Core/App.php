<?php

declare(strict_types=1);

namespace App\Core;

use App\Services\Logger;

final class App
{
    public static function run(): void
    {
        Logger::registerHandlers();
        $config = require __DIR__ . '/../../config/config.php';

        Session::start($config['app']);
        $db = Database::connect($config['db']);

        $router = new Router();
        require __DIR__ . '/../../config/routes.php';

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $basePath = rtrim($config['app']['base_path'] ?? '', '/');

        if ($basePath !== '' && str_starts_with($uri, $basePath)) {
            $uri = substr($uri, strlen($basePath)) ?: '/';
        }

        $router->dispatch($method, $uri, $db, $config);
    }
}
