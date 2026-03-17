<?php

declare(strict_types=1);

namespace App\Services;

use Throwable;

final class Logger
{
    private const LEVELS = ['ERROR', 'WARNING', 'INFO', 'DEBUG'];
    private const LOG_RETENTION_SECONDS = 86400;

    private static ?string $logDirectory = null;
    private static ?string $logFile = null;

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        self::write('DEBUG', $message, $context);
    }

    public static function registerHandlers(): void
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }

            self::error('PHP runtime error', [
                'severity' => $severity,
                'message' => $message,
                'file' => $file,
                'line' => $line,
            ]);

            return true;
        });

        set_exception_handler(static function (Throwable $exception): void {
            self::error('Unhandled exception', [
                'exception' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);

            if (!headers_sent()) {
                http_response_code(500);
            }

            echo 'Interner Serverfehler.';
        });
    }

    private static function write(string $level, string $message, array $context): void
    {
        if (!in_array($level, self::LEVELS, true)) {
            $level = 'INFO';
        }

        $logFile = self::logFile();
        self::rotateLogIfNeeded($logFile);

        $entry = [
            'timestamp' => gmdate('c'),
            'level' => $level,
            'message' => $message,
            'context' => self::sanitize($context),
            'request' => self::sanitize(self::requestContext()),
        ];

        $payload = json_encode($entry, JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            $payload = '{"timestamp":"' . gmdate('c') . '","level":"ERROR","message":"Logger encoding failed"}';
        }

        file_put_contents($logFile, $payload . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private static function requestContext(): array
    {
        $request = [];

        if (!empty($_SERVER['REQUEST_URI'])) {
            $request['url'] = (string) $_SERVER['REQUEST_URI'];
        }

        if (!empty($_SERVER['REQUEST_METHOD'])) {
            $request['method'] = (string) $_SERVER['REQUEST_METHOD'];
        }

        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $request['ip'] = (string) $_SERVER['REMOTE_ADDR'];
        }

        if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
            $request['user_id'] = (int) $_SESSION['user_id'];
        }

        if (!empty($_SERVER['HTTP_USER_AGENT'])) {
            $request['user_agent'] = (string) $_SERVER['HTTP_USER_AGENT'];
        }

        return $request;
    }

    private static function sanitize(array $data): array
    {
        $clean = [];

        foreach ($data as $key => $value) {
            $normalizedKey = is_string($key) ? $key : (string) $key;

            if (preg_match('/pass|token|secret|authorization|cookie/i', $normalizedKey) === 1) {
                $clean[$normalizedKey] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $clean[$normalizedKey] = self::sanitize($value);
                continue;
            }

            if (is_object($value)) {
                $clean[$normalizedKey] = method_exists($value, '__toString') ? (string) $value : get_class($value);
                continue;
            }

            if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                $clean[$normalizedKey] = $value;
                continue;
            }

            $clean[$normalizedKey] = (string) $value;
        }

        return $clean;
    }

    private static function rotateLogIfNeeded(string $logFile): void
    {
        $directory = dirname($logFile);
        self::ensureLogDirectory($directory);
        self::protectDirectory($directory);

        if (!is_file($logFile)) {
            self::cleanupOldLogs($directory, basename($logFile));
            return;
        }

        $modifiedAt = filemtime($logFile);
        if ($modifiedAt === false) {
            return;
        }

        if (time() - $modifiedAt < self::LOG_RETENTION_SECONDS) {
            self::cleanupOldLogs($directory, basename($logFile));
            return;
        }

        file_put_contents($logFile, '', LOCK_EX);
        self::cleanupOldLogs($directory, basename($logFile));
    }

    private static function cleanupOldLogs(string $directory, string $activeLogName): void
    {
        $files = glob($directory . DIRECTORY_SEPARATOR . '*.log') ?: [];
        foreach ($files as $filePath) {
            if (!is_file($filePath)) {
                continue;
            }

            if (basename($filePath) === $activeLogName) {
                continue;
            }

            @unlink($filePath);
        }
    }

    private static function protectDirectory(string $directory): void
    {
        $htaccessFile = $directory . DIRECTORY_SEPARATOR . '.htaccess';

        if (is_file($htaccessFile)) {
            return;
        }

        file_put_contents($htaccessFile, "Require all denied\nDeny from all\n", LOCK_EX);
    }

    private static function ensureLogDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        mkdir($directory, 0750, true);
    }

    private static function logFile(): string
    {
        if (self::$logFile !== null) {
            return self::$logFile;
        }

        $directory = self::logDirectory();
        self::$logFile = $directory . DIRECTORY_SEPARATOR . 'app.log';

        return self::$logFile;
    }

    private static function logDirectory(): string
    {
        if (self::$logDirectory !== null) {
            return self::$logDirectory;
        }

        self::$logDirectory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';

        return self::$logDirectory;
    }
}
