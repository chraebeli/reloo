<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class Loan
{
    public function __construct(private PDO $db)
    {
    }

    public function request(array $data): bool
    {
        $stmt = $this->db->prepare('INSERT INTO item_requests (item_id, requester_id, request_type, status, message, requested_start_date, requested_end_date, created_at) VALUES (:item_id, :requester_id, :request_type, :status, :message, :requested_start_date, :requested_end_date, NOW())');
        return $stmt->execute($data);
    }

    public function canRequestItem(int $itemId, int $requesterId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM items i JOIN group_members gm ON gm.group_id = i.group_id WHERE i.id = :item_id AND gm.user_id = :requester_id AND i.owner_id <> :owner_check_id LIMIT 1');
        $stmt->execute([
            'item_id' => $itemId,
            'requester_id' => $requesterId,
            'owner_check_id' => $requesterId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public function pendingForOwner(int $ownerId): array
    {
        $stmt = $this->db->prepare('SELECT ir.*, i.title, u.display_name AS requester_name FROM item_requests ir JOIN items i ON i.id = ir.item_id JOIN users u ON u.id = ir.requester_id WHERE i.owner_id = :owner_id AND ir.status = :status ORDER BY ir.created_at DESC');
        $stmt->execute(['owner_id' => $ownerId, 'status' => 'angefragt']);
        return $stmt->fetchAll();
    }

    public function approveRequest(int $requestId, int $ownerId): bool
    {
        $this->db->beginTransaction();
        $reqStmt = $this->db->prepare('SELECT ir.*, i.owner_id FROM item_requests ir JOIN items i ON i.id = ir.item_id WHERE ir.id = :id LIMIT 1 FOR UPDATE');
        $reqStmt->execute(['id' => $requestId]);
        $req = $reqStmt->fetch();

        if (!$req || (int) $req['owner_id'] !== $ownerId || $req['status'] !== 'angefragt') {
            $this->db->rollBack();
            return false;
        }

        $updateReq = $this->db->prepare('UPDATE item_requests SET status = :status, updated_at = NOW() WHERE id = :id');
        $updateReq->execute(['status' => 'reserviert', 'id' => $requestId]);

        $loan = $this->db->prepare('INSERT INTO loans (item_id, request_id, lender_id, borrower_id, start_date, end_date, status, created_at) VALUES (:item_id, :request_id, :lender_id, :borrower_id, :start_date, :end_date, :status, NOW())');
        $loan->execute([
            'item_id' => $req['item_id'],
            'request_id' => $requestId,
            'lender_id' => $ownerId,
            'borrower_id' => $req['requester_id'],
            'start_date' => $req['requested_start_date'],
            'end_date' => $req['requested_end_date'],
            'status' => 'ausgeliehen',
        ]);

        $item = $this->db->prepare('UPDATE items SET availability_status = :status WHERE id = :id');
        $item->execute(['status' => 'ausgeliehen', 'id' => $req['item_id']]);

        $this->db->commit();
        return true;
    }

    public function returnLoan(int $loanId, int $userId): bool
    {
        $stmt = $this->db->prepare('SELECT * FROM loans WHERE id = :id AND (lender_id = :lender_user_id OR borrower_id = :borrower_user_id) AND status = :status LIMIT 1');
        $stmt->execute([
            'id' => $loanId,
            'lender_user_id' => $userId,
            'borrower_user_id' => $userId,
            'status' => 'ausgeliehen',
        ]);
        $loan = $stmt->fetch();
        if (!$loan) {
            return false;
        }

        $this->db->beginTransaction();
        $upd = $this->db->prepare('UPDATE loans SET status = :status, returned_at = NOW() WHERE id = :id');
        $upd->execute(['status' => 'zurückgegeben', 'id' => $loanId]);
        $item = $this->db->prepare('UPDATE items SET availability_status = :status WHERE id = :item_id');
        $item->execute(['status' => 'verfügbar', 'item_id' => $loan['item_id']]);
        $this->db->commit();

        return true;
    }

    public function activeForUser(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT l.*, i.title, b.display_name AS borrower_name FROM loans l JOIN items i ON i.id = l.item_id JOIN users b ON b.id = l.borrower_id WHERE l.lender_id = :lender_user_id OR l.borrower_id = :borrower_user_id ORDER BY l.created_at DESC');
        $stmt->execute([
            'lender_user_id' => $userId,
            'borrower_user_id' => $userId,
        ]);
        return $stmt->fetchAll();
    }

    public function requestNotificationData(int $itemId): ?array
    {
        $stmt = $this->db->prepare('SELECT i.title, i.owner_id, u.display_name AS owner_name FROM items i JOIN users u ON u.id = i.owner_id WHERE i.id = :id LIMIT 1');
        $stmt->execute(['id' => $itemId]);
        return $stmt->fetch() ?: null;
    }

    public function loanNotificationData(int $loanId): ?array
    {
        $stmt = $this->db->prepare('SELECT l.id, l.lender_id, l.borrower_id, i.title FROM loans l JOIN items i ON i.id = l.item_id WHERE l.id = :id LIMIT 1');
        $stmt->execute(['id' => $loanId]);
        return $stmt->fetch() ?: null;
    }
}
