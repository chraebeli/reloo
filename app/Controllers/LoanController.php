<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Models\Loan;

final class LoanController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $model = new Loan($this->db);
        $this->view('loans/index', [
            'pending' => $model->pendingForOwner(current_user_id() ?? 0),
            'loans' => $model->activeForUser(current_user_id() ?? 0),
        ]);
    }

    public function request(): void
    {
        $this->requireAuth();
        verify_csrf();
        $model = new Loan($this->db);
        $ok = $model->request([
            'item_id' => (int) ($_POST['item_id'] ?? 0),
            'requester_id' => current_user_id(),
            'request_type' => trim($_POST['request_type'] ?? 'ausleihe'),
            'status' => 'angefragt',
            'message' => trim($_POST['message'] ?? ''),
            'requested_start_date' => $_POST['start_date'] ?? null,
            'requested_end_date' => $_POST['end_date'] ?? null,
        ]);

        Session::flash($ok ? 'success' : 'error', $ok ? 'Anfrage gesendet.' : 'Anfrage fehlgeschlagen.');
        $this->redirect('/items/show?id=' . (int) ($_POST['item_id'] ?? 0));
    }

    public function approve(): void
    {
        $this->requireAuth();
        verify_csrf();
        $model = new Loan($this->db);
        $ok = $model->approveRequest((int) ($_POST['request_id'] ?? 0), current_user_id() ?? 0);
        Session::flash($ok ? 'success' : 'error', $ok ? 'Anfrage bestätigt und Ausleihe gestartet.' : 'Freigabe nicht möglich.');
        $this->redirect('/loans');
    }

    public function return(): void
    {
        $this->requireAuth();
        verify_csrf();
        $model = new Loan($this->db);
        $ok = $model->returnLoan((int) ($_POST['loan_id'] ?? 0), current_user_id() ?? 0);
        Session::flash($ok ? 'success' : 'error', $ok ? 'Rückgabe erfasst.' : 'Rückgabe nicht möglich.');
        $this->redirect('/loans');
    }
}
