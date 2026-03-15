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
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $issueDescription = trim($_POST['issue_description'] ?? '');
        $userId = current_user_id() ?? 0;

        if ($itemId < 1 || $issueDescription === '') {
            Session::flash('error', 'Gegenstand und Problembeschreibung sind Pflichtfelder.');
            $this->redirect('/repairs');
        }

        $model = new Repair($this->db);
        if (!$model->canUserAccessItem($itemId, $userId)) {
            Session::flash('error', 'Keine Berechtigung für diesen Gegenstand.');
            $this->redirect('/repairs');
        }

        $ok = $model->create([
            'item_id' => $itemId,
            'reported_by' => $userId,
            'status' => 'gemeldet',
            'issue_description' => $issueDescription,
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

        $repairId = (int) ($_POST['repair_id'] ?? 0);
        $userId = current_user_id() ?? 0;
        $model = new Repair($this->db);

        if ($repairId < 1 || !$model->canUserUpdateRepair($repairId, $userId)) {
            Session::flash('error', 'Keine Berechtigung für diese Statusänderung.');
            $this->redirect('/repairs');
        }

        $ok = $model->updateStatus($repairId, $status);
        Session::flash($ok ? 'success' : 'error', $ok ? 'Status aktualisiert.' : 'Statusänderung fehlgeschlagen.');
        $this->redirect('/repairs');
    }
}
