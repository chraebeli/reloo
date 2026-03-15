<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Activity;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $userId = current_user_id() ?? 0;

        $statsSql = 'SELECT
            (SELECT COUNT(*) FROM items i JOIN group_members gm ON gm.group_id = i.group_id WHERE gm.user_id = :uid_items) AS total_items,
            (SELECT COUNT(*) FROM loans l WHERE l.status = "ausgeliehen" AND (l.lender_id = :uid_lender OR l.borrower_id = :uid_borrower)) AS active_loans,
            (SELECT COUNT(*) FROM item_requests ir JOIN items i ON i.id = ir.item_id WHERE ir.status = "angefragt" AND i.owner_id = :uid_requests) AS open_requests,
            (SELECT COUNT(*) FROM repairs r JOIN items i ON i.id = r.item_id JOIN group_members gm ON gm.group_id = i.group_id WHERE r.status IN ("gemeldet", "in_pruefung", "in_reparatur") AND gm.user_id = :uid_repairs) AS repairs_open';

        $stmt = $this->db->prepare($statsSql);
        $stmt->execute([
            'uid_items' => $userId,
            'uid_lender' => $userId,
            'uid_borrower' => $userId,
            'uid_requests' => $userId,
            'uid_repairs' => $userId,
        ]);
        $stats = $stmt->fetch() ?: [];

        $recentItemsStmt = $this->db->prepare('SELECT i.id, i.title, i.availability_status, i.created_at FROM items i JOIN group_members gm ON gm.group_id = i.group_id WHERE gm.user_id = :uid ORDER BY i.created_at DESC LIMIT 8');
        $recentItemsStmt->execute(['uid' => $userId]);

        $activityModel = new Activity($this->db);

        $this->view('dashboard/index', [
            'stats' => $stats,
            'recentItems' => $recentItemsStmt->fetchAll(),
            'activities' => $activityModel->recentForUser($userId),
        ]);
    }
}
