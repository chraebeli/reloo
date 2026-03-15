<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;

final class AdminController extends Controller
{
    public function index(): void
    {
        $this->requireAdmin();

        $stats = [
            'mitglieder' => (int) $this->db->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'gegenstaende' => (int) $this->db->query('SELECT COUNT(*) FROM items')->fetchColumn(),
            'ausleihen' => (int) $this->db->query('SELECT COUNT(*) FROM loans')->fetchColumn(),
            'verschenkt' => (int) $this->db->query("SELECT COUNT(*) FROM item_requests WHERE request_type = 'geschenk' AND status IN ('abgeschlossen', 'reserviert')")->fetchColumn(),
            'reparaturen' => (int) $this->db->query('SELECT COUNT(*) FROM repairs')->fetchColumn(),
        ];

        $categories = $this->db->query('SELECT * FROM categories ORDER BY name')->fetchAll();

        $this->view('admin/index', [
            'stats' => $stats,
            'users' => (new User($this->db))->all(),
            'categories' => $categories,
        ]);
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
