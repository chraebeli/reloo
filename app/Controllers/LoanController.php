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

        $itemId = (int) ($_POST['item_id'] ?? 0);
        $requestType = trim($_POST['request_type'] ?? 'ausleihe');
        $startDate = trim($_POST['start_date'] ?? '');
        $endDate = trim($_POST['end_date'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $allowedTypes = ['ausleihe', 'tausch', 'geschenk'];
        $userId = current_user_id() ?? 0;

        if ($itemId < 1 || !in_array($requestType, $allowedTypes, true)) {
            Session::flash('error', 'Ungültige Anfrage.');
            $this->redirect('/items');
        }

        if ($requestType === 'ausleihe') {
            $start = \DateTimeImmutable::createFromFormat('Y-m-d', $startDate);
            $end = \DateTimeImmutable::createFromFormat('Y-m-d', $endDate);
            if (!$start || !$end || $start->format('Y-m-d') !== $startDate || $end->format('Y-m-d') !== $endDate || $start > $end) {
                Session::flash('error', 'Bitte gültige Datumsangaben angeben.');
                $this->redirect('/items/show?id=' . $itemId);
            }
        } else {
            $startDate = null;
            $endDate = null;
        }

        $model = new Loan($this->db);
        if (!$model->canRequestItem($itemId, $userId)) {
            Session::flash('error', 'Keine Berechtigung für diese Anfrage.');
            $this->redirect('/items/show?id=' . $itemId);
        }

        $ok = $model->request([
            'item_id' => $itemId,
            'requester_id' => $userId,
            'request_type' => $requestType,
            'status' => 'angefragt',
            'message' => $message,
            'requested_start_date' => $startDate ?: null,
            'requested_end_date' => $endDate ?: null,
        ]);

        Session::flash($ok ? 'success' : 'error', $ok ? 'Anfrage gesendet.' : 'Anfrage fehlgeschlagen.');
        $this->redirect('/items/show?id=' . $itemId);
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
