<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Models\Activity;
use App\Models\User;
use App\Services\BackupService;
use App\Services\Logger;
use RuntimeException;
use Throwable;

final class AdminController extends Controller
{
    public function index(): void
    {
        $this->requireAdmin();

        $userModel = new User($this->db);
        $statusFilter = $_GET['status'] ?? '';
        if (!in_array($statusFilter, ['pending', 'approved', 'rejected'], true)) {
            $statusFilter = null;
        }

        $stats = [
            'mitglieder' => (int) $this->db->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'offene_freigaben' => (int) $this->db->query("SELECT COUNT(*) FROM users WHERE approval_status = 'pending'")->fetchColumn(),
            'gegenstaende' => (int) $this->db->query('SELECT COUNT(*) FROM items')->fetchColumn(),
            'ausleihen' => (int) $this->db->query('SELECT COUNT(*) FROM loans')->fetchColumn(),
            'verschenkt' => (int) $this->db->query("SELECT COUNT(*) FROM item_requests WHERE request_type = 'geschenk' AND status IN ('abgeschlossen', 'reserviert')")->fetchColumn(),
            'reparaturen' => (int) $this->db->query('SELECT COUNT(*) FROM repairs')->fetchColumn(),
        ];

        $categories = $this->db->query('SELECT * FROM categories ORDER BY name')->fetchAll();

        $this->view('admin/index', [
            'stats' => $stats,
            'users' => $userModel->all($statusFilter),
            'statusFilter' => $statusFilter,
            'categories' => $categories,
        ]);
    }

    public function backups(): void
    {
        $this->requireAdmin();

        $backupService = new BackupService($this->db, $this->config);
        $this->view('admin/backups', [
            'backups' => $backupService->listBackups(),
        ]);
    }

    public function createBackup(): void
    {
        $this->requireAdmin();
        verify_csrf();

        $adminId = (int) ($_SESSION['user_id'] ?? 0);
        $adminEmail = $this->currentAdminEmail($adminId);

        try {
            $backupService = new BackupService($this->db, $this->config);
            $backup = $backupService->createBackup($adminId, $adminEmail);
            Logger::info('Backup created', ['admin_id' => $adminId, 'file' => (string) $backup['filename']]);
            $this->audit($adminId, 'backup_created', 'Backup erstellt: ' . $backup['filename']);
            Session::flash('success', 'Neues Backup erfolgreich erstellt.');
        } catch (Throwable $exception) {
            Logger::error('Backup creation failed', ['admin_id' => $adminId, 'exception' => $exception->getMessage()]);
            $this->audit($adminId, 'backup_create_failed', 'Backup-Erstellung fehlgeschlagen: ' . $exception->getMessage());
            Session::flash('error', 'Backup konnte nicht erstellt werden.');
        }

        $this->redirect('/admin/backups');
    }

    public function downloadBackup(): void
    {
        $this->requireAdmin();

        $fileName = (string) ($_GET['file'] ?? '');
        $backupService = new BackupService($this->db, $this->config);

        try {
            $path = $backupService->resolveBackupPath($fileName);
        } catch (Throwable) {
            Logger::warning('Backup download failed: file not found', ['file' => $fileName]);
            http_response_code(404);
            exit('Backup nicht gefunden.');
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . (string) (filesize($path) ?: 0));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    public function deleteBackup(): void
    {
        $this->requireAdmin();
        verify_csrf();

        $fileName = (string) ($_POST['file'] ?? '');
        $adminId = (int) ($_SESSION['user_id'] ?? 0);

        try {
            $backupService = new BackupService($this->db, $this->config);
            $backupService->deleteBackup($fileName);
            Logger::info('Backup deleted', ['admin_id' => $adminId, 'file' => $fileName]);
            $this->audit($adminId, 'backup_deleted', 'Backup gelöscht: ' . $fileName);
            Session::flash('success', 'Backup wurde gelöscht.');
        } catch (Throwable $exception) {
            Logger::error('Backup delete failed', ['admin_id' => $adminId, 'file' => $fileName, 'exception' => $exception->getMessage()]);
            $this->audit($adminId, 'backup_delete_failed', 'Backup-Löschen fehlgeschlagen: ' . $exception->getMessage());
            Session::flash('error', 'Backup konnte nicht gelöscht werden.');
        }

        $this->redirect('/admin/backups');
    }

    public function restoreBackup(): void
    {
        $this->requireAdmin();
        verify_csrf();

        $fileName = (string) ($_POST['file'] ?? '');
        $confirmationText = trim((string) ($_POST['confirmation_text'] ?? ''));
        $password = (string) ($_POST['current_password'] ?? '');
        $createSafetyBackup = isset($_POST['create_safety_backup']) && $_POST['create_safety_backup'] === '1';
        $adminId = (int) ($_SESSION['user_id'] ?? 0);
        $adminEmail = $this->currentAdminEmail($adminId);

        if ($confirmationText !== 'Ich verstehe, dass die aktuellen Daten überschrieben werden') {
            Session::flash('error', 'Bitte bestätige die Wiederherstellung ausdrücklich.');
            $this->redirect('/admin/backups');
        }

        if (!$this->verifyAdminPassword($adminId, $password)) {
            Session::flash('error', 'Das eingegebene Admin-Passwort ist ungültig.');
            $this->redirect('/admin/backups');
        }

        $backupService = new BackupService($this->db, $this->config);
        Logger::info('Backup restore started', ['admin_id' => $adminId, 'file' => $fileName, 'safety_backup' => $createSafetyBackup]);
        $this->audit($adminId, 'backup_restore_started', 'Restore gestartet: ' . $fileName);

        try {
            $backupService->restoreBackup($fileName, $createSafetyBackup, $adminId, $adminEmail);
            Logger::info('Backup restore succeeded', ['admin_id' => $adminId, 'file' => $fileName]);
            Session::flash('success', 'Backup erfolgreich wiederhergestellt.');
            $this->audit($adminId, 'backup_restore_success', 'Restore erfolgreich: ' . $fileName);
        } catch (Throwable $exception) {
            Logger::error('Backup restore failed', ['admin_id' => $adminId, 'file' => $fileName, 'exception' => $exception->getMessage()]);
            Session::flash('error', 'Backup konnte nicht verarbeitet werden.');
            $this->audit($adminId, 'backup_restore_failed', 'Restore fehlgeschlagen: ' . $exception->getMessage());
        }

        $this->redirect('/admin/backups');
    }

    public function updateUserApproval(): void
    {
        $this->requireAdmin();
        verify_csrf();

        $userId = (int) ($_POST['user_id'] ?? 0);
        $status = $_POST['status'] ?? '';

        if ($userId <= 0 || !in_array($status, ['pending', 'approved', 'rejected'], true)) {
            Session::flash('error', 'Ungültige Freigabe-Aktion.');
            $this->redirect('/admin');
        }

        (new User($this->db))->updateApprovalStatus($userId, $status, (int) $_SESSION['user_id']);
        Logger::info('User approval status updated', ['admin_id' => (int) $_SESSION['user_id'], 'target_user_id' => $userId, 'status' => $status]);

        $statusLabel = [
            'pending' => 'wartend',
            'approved' => 'freigegeben',
            'rejected' => 'abgelehnt',
        ][$status];

        Session::flash('success', 'Benutzerstatus wurde auf "' . $statusLabel . '" gesetzt.');
        $this->redirect('/admin');
    }

    public function createCategory(): void
    {
        $this->requireAdmin();
        verify_csrf();

        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $this->redirect('/admin');
        }

        $stmt = $this->db->prepare('INSERT INTO categories (name, is_active, created_at) VALUES (:name, 1, NOW())');
        $stmt->execute(['name' => $name]);
        $this->redirect('/admin');
    }

    public function exportCsv(): void
    {
        $this->requireAdmin();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="sharing-statistik.csv"');

        $output = fopen('php://output', 'wb');
        fputcsv($output, ['ID', 'Titel', 'Status', 'Eigentumsform', 'Erstellt am'], ';');

        foreach ($this->db->query('SELECT id, title, availability_status, ownership_type, created_at FROM items ORDER BY id DESC')->fetchAll() as $row) {
            fputcsv($output, $row, ';');
        }

        fclose($output);
        exit;
    }

    private function activity(): Activity
    {
        return new Activity($this->db);
    }


    private function audit(int $adminId, string $type, string $message): void
    {
        try {
            $stmt = $this->db->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $adminId]);
            $userId = $stmt->fetchColumn();
            $safeUserId = is_numeric($userId) ? (int) $userId : null;

            $this->activity()->log($safeUserId, null, $type, $message);
        } catch (Throwable) {
            // Audit-Logging darf nie den Hauptprozess blockieren.
        }
    }

    private function currentAdminEmail(int $adminId): string
    {
        if ($adminId <= 0) {
            throw new RuntimeException('Admin-Benutzer ist ungültig.');
        }

        $stmt = $this->db->prepare('SELECT email FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $adminId]);
        $email = $stmt->fetchColumn();

        if (!is_string($email) || $email === '') {
            throw new RuntimeException('Admin-E-Mail konnte nicht ermittelt werden.');
        }

        return $email;
    }

    private function verifyAdminPassword(int $adminId, string $password): bool
    {
        if ($adminId <= 0 || $password === '') {
            return false;
        }

        $stmt = $this->db->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $adminId]);
        $hash = $stmt->fetchColumn();

        if (!is_string($hash) || $hash === '') {
            return false;
        }

        return password_verify($password, $hash);
    }
}
