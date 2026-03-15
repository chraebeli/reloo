<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Models\Repair;

final class RepairController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $model = new Repair($this->db);
        $this->view('repairs/index', ['repairs' => $model->listForUser(current_user_id() ?? 0)]);
    }

    public function create(): void
    {
        $this->requireAuth();
        verify_csrf();
        $model = new Repair($this->db);
        $ok = $model->create([
            'item_id' => (int) ($_POST['item_id'] ?? 0),
            'reported_by' => current_user_id(),
            'status' => 'gemeldet',
            'issue_description' => trim($_POST['issue_description'] ?? ''),
            'part_notes' => trim($_POST['part_notes'] ?? ''),
            'effort_notes' => trim($_POST['effort_notes'] ?? ''),
        ]);

        Session::flash($ok ? 'success' : 'error', $ok ? 'Reparaturfall erstellt.' : 'Reparaturfall konnte nicht erstellt werden.');
        $this->redirect('/repairs');
    }

    public function updateStatus(): void
    {
        $this->requireAuth();
        verify_csrf();
        $status = trim($_POST['status'] ?? 'gemeldet');
        $allowed = ['gemeldet', 'in_pruefung', 'in_reparatur', 'repariert', 'nicht_reparierbar'];
        if (!in_array($status, $allowed, true)) {
            Session::flash('error', 'Ungültiger Status.');
            $this->redirect('/repairs');
        }

        $ok = (new Repair($this->db))->updateStatus((int) ($_POST['repair_id'] ?? 0), $status);
        Session::flash($ok ? 'success' : 'error', $ok ? 'Status aktualisiert.' : 'Statusänderung fehlgeschlagen.');
        $this->redirect('/repairs');
    }
}
