<?php

declare(strict_types=1);

use App\Core\App;

require __DIR__ . '/../app/Helpers/functions.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = __DIR__ . '/../app/' . $relative . '.php';

    if (is_file($file)) {
        require $file;
    }
});

App::run();
