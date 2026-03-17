<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use App\Services\Logger;

final class Database
{
    public static function connect(array $dbConfig): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $dbConfig['host'],
            (int) $dbConfig['port'],
            $dbConfig['database'],
            $dbConfig['charset']
        );

        try {
            return new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            Logger::error('Database connection failed', [
                'exception' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            http_response_code(500);
            exit('Datenbankverbindung fehlgeschlagen. Bitte Konfiguration prüfen.');
        }
    }
}
