<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Router
{
    private array $routes = [];

    public function get(string $path, array $action): void
    {
        $this->map('GET', $path, $action);
    }

    public function post(string $path, array $action): void
    {
        $this->map('POST', $path, $action);
    }

    private function map(string $method, string $path, array $action): void
    {
        $this->routes[$method][$path] = $action;
    }

    public function dispatch(string $method, string $uri, PDO $db, array $config): void
    {
        $action = $this->routes[$method][$uri] ?? null;

        if ($action === null) {
            http_response_code(404);
            echo 'Seite nicht gefunden';
            return;
        }

        [$controllerClass, $controllerMethod] = $action;
        $controller = new $controllerClass($db, $config);
        $controller->{$controllerMethod}();
    }
}
