<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class Repair
{
    public function __construct(private PDO $db)
    {
    }

    public function create(array $data): bool
    {
        $stmt = $this->db->prepare('INSERT INTO repairs (item_id, reported_by, status, issue_description, part_notes, effort_notes, created_at) VALUES (:item_id, :reported_by, :status, :issue_description, :part_notes, :effort_notes, NOW())');
        return $stmt->execute($data);
    }

    public function listForUser(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT r.*, i.title FROM repairs r JOIN items i ON i.id = r.item_id JOIN group_members gm ON gm.group_id = i.group_id WHERE gm.user_id = :user_id ORDER BY r.created_at DESC');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare('UPDATE repairs SET status = :status, updated_at = NOW() WHERE id = :id');
        return $stmt->execute(['id' => $id, 'status' => $status]);
    }
}
