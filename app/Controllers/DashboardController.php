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

        $userId = current_user_id();

        $statsSql = 'SELECT
            (SELECT COUNT(*) FROM items i JOIN group_members gm ON gm.group_id = i.group_id WHERE gm.user_id = :uid) AS total_items,
            (SELECT COUNT(*) FROM loans l WHERE l.status = "ausgeliehen" AND (l.lender_id = :uid OR l.borrower_id = :uid)) AS active_loans,
            (SELECT COUNT(*) FROM item_requests ir JOIN items i ON i.id = ir.item_id WHERE ir.status = "angefragt" AND i.owner_id = :uid) AS open_requests,
            (SELECT COUNT(*) FROM repairs r JOIN items i ON i.id = r.item_id JOIN group_members gm ON gm.group_id = i.group_id WHERE r.status IN ("gemeldet", "in_pruefung", "in_reparatur") AND gm.user_id = :uid) AS repairs_open';

        $stmt = $this->db->prepare($statsSql);
        $stmt->execute(['uid' => $userId]);
        $stats = $stmt->fetch() ?: [];

        $recentItemsStmt = $this->db->prepare('SELECT i.id, i.title, i.availability_status, i.created_at FROM items i JOIN group_members gm ON gm.group_id = i.group_id WHERE gm.user_id = :uid ORDER BY i.created_at DESC LIMIT 8');
        $recentItemsStmt->execute(['uid' => $userId]);

        $activityModel = new Activity($this->db);

        $this->view('dashboard/index', [
            'stats' => $stats,
            'recentItems' => $recentItemsStmt->fetchAll(),
            'activities' => $activityModel->recentForUser($userId ?? 0),
        ]);
    }
}
