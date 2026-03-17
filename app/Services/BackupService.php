<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;
use ZipArchive;

final class BackupService
{
    private const APP_TABLES = [
        'users',
        'groups',
        'group_members',
        'categories',
        'items',
        'item_images',
        'item_requests',
        'loans',
        'repairs',
        'notifications',
        'activity_log',
    ];

    private const FILE_PATHS = [
        'uploads/items',
    ];

    public function __construct(private PDO $db, private array $config)
    {
    }

    public function createBackup(int $adminId, string $adminEmail): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Die PHP-Erweiterung ZipArchive ist nicht verfügbar.');
        }

        $backupDir = $this->backupDirectory();
        $this->ensureDirectory($backupDir);

        $timestamp = (new DateTimeImmutable('now'))->format('Y-m-d-His');
        $fileName = sprintf('backup-%s.zip', $timestamp);
        $zipPath = $backupDir . DIRECTORY_SEPARATOR . $fileName;

        $tables = $this->resolveAppTables();
        if ($tables === []) {
            throw new RuntimeException('Es wurden keine anwendungsrelevanten Tabellen für das Backup gefunden.');
        }

        $sqlPath = tempnam(sys_get_temp_dir(), 'reloo-sql-');
        if ($sqlPath === false) {
            throw new RuntimeException('Temporäre SQL-Datei konnte nicht erstellt werden.');
        }

        try {
            $this->writeSqlDump($sqlPath, $tables);

            $manifest = [
                'created_at' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
                'app_name' => (string) ($this->config['app']['name'] ?? 'Reloo'),
                'app_version' => (string) ($this->config['app']['version'] ?? 'unbekannt'),
                'backup_type' => 'manual_full',
                'created_by' => [
                    'admin_id' => $adminId,
                    'email' => $adminEmail,
                ],
                'contains' => [
                    'database_tables' => $tables,
                    'paths' => self::FILE_PATHS,
                    'database_file' => 'database.sql',
                ],
            ];

            $zip = new ZipArchive();
            $result = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            if ($result !== true) {
                throw new RuntimeException('Backup-Archiv konnte nicht erstellt werden.');
            }

            if (!$zip->addFile($sqlPath, 'database.sql')) {
                throw new RuntimeException('SQL-Dump konnte nicht zum Backup hinzugefügt werden.');
            }

            if (!$zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
                throw new RuntimeException('Manifest konnte nicht erzeugt werden.');
            }

            foreach (self::FILE_PATHS as $relativePath) {
                $fullPath = $this->projectRoot() . DIRECTORY_SEPARATOR . $relativePath;
                if (!is_dir($fullPath)) {
                    continue;
                }

                $this->addDirectoryToZip($zip, $fullPath, $relativePath);
            }

            $zip->close();

            $manifestSummary = [
                'filename' => $fileName,
                'created_at' => $manifest['created_at'],
                'backup_type' => $manifest['backup_type'],
                'created_by' => $manifest['created_by'],
                'contains' => $manifest['contains'],
            ];

            return [
                'filename' => $fileName,
                'path' => $zipPath,
                'manifest' => $manifestSummary,
            ];
        } catch (Throwable $exception) {
            if (is_file($zipPath)) {
                @unlink($zipPath);
            }

            throw $exception;
        } finally {
            @unlink($sqlPath);
        }
    }

    public function listBackups(): array
    {
        $backupDir = $this->backupDirectory();
        $this->ensureDirectory($backupDir);

        $items = [];
        $files = glob($backupDir . DIRECTORY_SEPARATOR . 'backup-*.zip') ?: [];

        foreach ($files as $filePath) {
            if (!is_file($filePath)) {
                continue;
            }

            $fileName = basename($filePath);
            $manifest = $this->readManifest($filePath);
            $createdAt = $manifest['created_at'] ?? null;

            if (!is_string($createdAt) || $createdAt === '') {
                $createdAt = date(DATE_ATOM, filemtime($filePath) ?: time());
            }

            $items[] = [
                'filename' => $fileName,
                'path' => $filePath,
                'size_bytes' => filesize($filePath) ?: 0,
                'created_at' => $createdAt,
                'backup_type' => (string) ($manifest['backup_type'] ?? 'unbekannt'),
                'created_by' => $manifest['created_by'] ?? null,
                'contains' => $manifest['contains'] ?? null,
            ];
        }

        usort($items, static fn (array $a, array $b): int => strcmp((string) $b['created_at'], (string) $a['created_at']));

        return $items;
    }

    public function resolveBackupPath(string $fileName): string
    {
        $safeName = basename($fileName);
        if (!preg_match('/^backup-\d{4}-\d{2}-\d{2}-\d{6}\.zip$/', $safeName)) {
            throw new RuntimeException('Ungültiger Dateiname.');
        }

        $path = $this->backupDirectory() . DIRECTORY_SEPARATOR . $safeName;
        if (!is_file($path)) {
            throw new RuntimeException('Backup-Datei wurde nicht gefunden.');
        }

        return $path;
    }

    public function deleteBackup(string $fileName): void
    {
        $path = $this->resolveBackupPath($fileName);
        if (!@unlink($path)) {
            throw new RuntimeException('Backup-Datei konnte nicht gelöscht werden.');
        }
    }

    public function restoreBackup(string $fileName, bool $createSafetyBackup, int $adminId, string $adminEmail): void
    {
        $backupPath = $this->resolveBackupPath($fileName);
        $tempDir = $this->createTempDirectory();

        try {
            $manifest = $this->extractValidatedBackup($backupPath, $tempDir);
            $tables = $manifest['contains']['database_tables'] ?? [];

            if (!is_array($tables) || $tables === []) {
                throw new RuntimeException('Manifest enthält keine gültigen Datenbanktabellen.');
            }

            $allowedTables = $this->resolveAppTables();
            $restoreTables = array_values(array_intersect($allowedTables, array_map('strval', $tables)));

            if ($restoreTables === []) {
                throw new RuntimeException('Backup enthält keine erlaubten App-Tabellen für die Wiederherstellung.');
            }

            $sqlFile = $tempDir . DIRECTORY_SEPARATOR . 'database.sql';
            if (!is_file($sqlFile)) {
                throw new RuntimeException('SQL-Datei im Backup fehlt.');
            }

            if ($createSafetyBackup) {
                $this->createBackup($adminId, $adminEmail);
            }

            $this->db->beginTransaction();
            try {
                $this->db->exec('SET FOREIGN_KEY_CHECKS=0');
                $this->truncateTables($restoreTables);
                $this->executeSqlFile($sqlFile);
                $this->db->exec('SET FOREIGN_KEY_CHECKS=1');
                $this->db->commit();
            } catch (Throwable $exception) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }

                $this->db->exec('SET FOREIGN_KEY_CHECKS=1');
                throw $exception;
            }

            $this->restoreFiles($tempDir);
        } finally {
            $this->deleteDirectory($tempDir);
        }
    }

    public function backupDirectory(): string
    {
        return $this->projectRoot() . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'backups';
    }

    private function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    private function ensureDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (!mkdir($path, 0750, true) && !is_dir($path)) {
            throw new RuntimeException('Verzeichnis konnte nicht erstellt werden: ' . $path);
        }
    }

    private function resolveAppTables(): array
    {
        $allTables = $this->db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $allTables = array_map('strval', $allTables ?: []);

        $exact = array_values(array_intersect(self::APP_TABLES, $allTables));
        if (count($exact) >= 3) {
            return $exact;
        }

        $prefixScores = [];
        foreach ($allTables as $table) {
            foreach (self::APP_TABLES as $appTable) {
                $needle = '_' . $appTable;
                if (str_ends_with($table, $needle)) {
                    $prefix = substr($table, 0, -strlen($needle));
                    $prefixScores[$prefix] = ($prefixScores[$prefix] ?? 0) + 1;
                }
            }
        }

        if ($prefixScores === []) {
            return $exact;
        }

        arsort($prefixScores);
        $prefix = (string) array_key_first($prefixScores);

        if (($prefixScores[$prefix] ?? 0) < 3) {
            return $exact;
        }

        $resolved = [];
        foreach (self::APP_TABLES as $baseTable) {
            $prefixed = $prefix . '_' . $baseTable;
            if (in_array($prefixed, $allTables, true)) {
                $resolved[] = $prefixed;
            }
        }

        return $resolved;
    }

    private function writeSqlDump(string $targetFile, array $tables): void
    {
        $handle = fopen($targetFile, 'wb');
        if ($handle === false) {
            throw new RuntimeException('SQL-Zieldatei konnte nicht geöffnet werden.');
        }

        fwrite($handle, "-- Reloo Backup SQL\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

        foreach ($tables as $table) {
            $createStmt = $this->db->query('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`')->fetch();
            $createSql = $createStmt['Create Table'] ?? null;
            if (!is_string($createSql) || $createSql === '') {
                throw new RuntimeException('CREATE TABLE konnte nicht gelesen werden: ' . $table);
            }

            fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
            fwrite($handle, $createSql . ";\n\n");

            $rows = $this->db->query('SELECT * FROM `' . str_replace('`', '``', $table) . '`', PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $columns = array_map(static fn (string $column): string => '`' . str_replace('`', '``', $column) . '`', array_keys($row));
                $values = array_map(fn (mixed $value): string => $this->sqlValue($value), array_values($row));
                fwrite($handle, 'INSERT INTO `' . $table . '` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ");\n");
            }

            fwrite($handle, "\n");
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($handle);
    }

    private function sqlValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $this->db->quote((string) $value);
    }

    private function addDirectoryToZip(ZipArchive $zip, string $sourceDir, string $zipPrefix): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $entry) {
            $realPath = $entry->getPathname();
            $relative = substr($realPath, strlen($sourceDir));
            $relative = ltrim(str_replace('\\', '/', $relative), '/');
            $zipPath = rtrim(str_replace('\\', '/', $zipPrefix), '/') . '/' . $relative;

            if ($entry->isDir()) {
                $zip->addEmptyDir(rtrim($zipPath, '/'));
                continue;
            }

            if ($entry->isFile()) {
                $zip->addFile($realPath, $zipPath);
            }
        }
    }

    private function readManifest(string $zipPath): ?array
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return null;
        }

        $manifestRaw = $zip->getFromName('manifest.json');
        $zip->close();

        if (!is_string($manifestRaw) || $manifestRaw === '') {
            return null;
        }

        try {
            $manifest = json_decode($manifestRaw, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        return is_array($manifest) ? $manifest : null;
    }

    private function createTempDirectory(): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'reloo-restore-' . bin2hex(random_bytes(8));
        $this->ensureDirectory($path);

        return $path;
    }

    private function extractValidatedBackup(string $backupPath, string $targetDir): array
    {
        $zip = new ZipArchive();
        if ($zip->open($backupPath) !== true) {
            throw new RuntimeException('Backup-ZIP konnte nicht geöffnet werden.');
        }

        $allowedRoots = ['manifest.json', 'database.sql', 'uploads/'];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!is_string($name) || $name === '') {
                throw new RuntimeException('Backup enthält einen ungültigen Eintrag.');
            }

            if (str_contains($name, "\0") || str_starts_with($name, '/') || preg_match('#(^|/)\.\.(?:/|$)#', $name) === 1) {
                throw new RuntimeException('Backup enthält unsichere Pfade und wurde abgelehnt.');
            }

            $isAllowed = false;
            foreach ($allowedRoots as $root) {
                if ($name === $root || str_starts_with($name, $root)) {
                    $isAllowed = true;
                    break;
                }
            }

            if (!$isAllowed) {
                throw new RuntimeException('Backup enthält nicht erlaubte Inhalte.');
            }
        }

        if (!$zip->extractTo($targetDir)) {
            throw new RuntimeException('Backup konnte nicht extrahiert werden.');
        }

        $zip->close();

        $manifestPath = $targetDir . DIRECTORY_SEPARATOR . 'manifest.json';
        if (!is_file($manifestPath)) {
            throw new RuntimeException('manifest.json fehlt im Backup.');
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($manifest)) {
            throw new RuntimeException('manifest.json ist ungültig.');
        }

        return $manifest;
    }

    private function truncateTables(array $tables): void
    {
        foreach ($tables as $table) {
            $safe = str_replace('`', '``', $table);
            $this->db->exec('TRUNCATE TABLE `' . $safe . '`');
        }
    }

    private function executeSqlFile(string $sqlFile): void
    {
        $content = (string) file_get_contents($sqlFile);
        foreach ($this->splitSqlStatements($content) as $statement) {
            $trimmed = trim($statement);
            if ($trimmed === '') {
                continue;
            }

            $this->db->exec($trimmed);
        }
    }

    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $inSingle = false;
        $inDouble = false;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if (!$inSingle && !$inDouble && $char === '-' && $next === '-') {
                while ($i < $length && $sql[$i] !== "\n") {
                    $i++;
                }

                continue;
            }

            if (!$inSingle && !$inDouble && $char === '/' && $next === '*') {
                $i += 2;
                while ($i < $length - 1 && !($sql[$i] === '*' && $sql[$i + 1] === '/')) {
                    $i++;
                }

                $i++;
                continue;
            }

            if ($char === "'" && !$inDouble) {
                $escaped = $i > 0 && $sql[$i - 1] === '\\';
                if (!$escaped) {
                    $inSingle = !$inSingle;
                }
            }

            if ($char === '"' && !$inSingle) {
                $escaped = $i > 0 && $sql[$i - 1] === '\\';
                if (!$escaped) {
                    $inDouble = !$inDouble;
                }
            }

            if ($char === ';' && !$inSingle && !$inDouble) {
                $statements[] = $buffer;
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $statements[] = $buffer;
        }

        return $statements;
    }

    private function restoreFiles(string $tempDir): void
    {
        foreach (self::FILE_PATHS as $relativePath) {
            $source = $tempDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $target = $this->projectRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

            if (!is_dir($source)) {
                continue;
            }

            $this->deleteDirectory($target);
            $this->ensureDirectory($target);
            $this->copyDirectory($source, $target);
        }
    }

    private function copyDirectory(string $source, string $target): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $entry) {
            $relative = substr($entry->getPathname(), strlen($source));
            $destination = rtrim($target, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($relative, DIRECTORY_SEPARATOR);

            if ($entry->isDir()) {
                $this->ensureDirectory($destination);
                continue;
            }

            $parentDir = dirname($destination);
            $this->ensureDirectory($parentDir);

            if (!copy($entry->getPathname(), $destination)) {
                throw new RuntimeException('Datei konnte nicht wiederhergestellt werden: ' . $destination);
            }
        }
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $entry) {
            if ($entry->isDir()) {
                @rmdir($entry->getPathname());
            } else {
                @unlink($entry->getPathname());
            }
        }

        @rmdir($path);
    }
}
