<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Models\User;

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
}
