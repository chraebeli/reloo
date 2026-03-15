<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class Group
{
    public function __construct(private PDO $db)
    {
    }

    public function create(array $data): bool
    {
        $stmt = $this->db->prepare('INSERT INTO `groups` (name, description, invite_code, created_by, created_at) VALUES (:name, :description, :invite_code, :created_by, NOW())');
        return $stmt->execute($data);
    }

    public function allForUser(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT g.* FROM `groups` g JOIN group_members gm ON gm.group_id = g.id WHERE gm.user_id = :user_id ORDER BY g.name');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function joinByInviteCode(int $userId, string $inviteCode): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM `groups` WHERE invite_code = :code LIMIT 1');
        $stmt->execute(['code' => $inviteCode]);
        $group = $stmt->fetch();

        if (!$group) {
            return false;
        }

        $join = $this->db->prepare('INSERT IGNORE INTO group_members (group_id, user_id, role, joined_at) VALUES (:group_id, :user_id, :role, NOW())');
        return $join->execute([
            'group_id' => $group['id'],
            'user_id' => $userId,
            'role' => 'member',
        ]);
    }

    public function addCreatorAsAdmin(int $groupId, int $userId): void
    {
        $stmt = $this->db->prepare('INSERT INTO group_members (group_id, user_id, role, joined_at) VALUES (:group_id, :user_id, :role, NOW())');
        $stmt->execute(['group_id' => $groupId, 'user_id' => $userId, 'role' => 'admin']);
    }

    public function isMember(int $userId, int $groupId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM group_members WHERE user_id = :user_id AND group_id = :group_id LIMIT 1');
        $stmt->execute(['user_id' => $userId, 'group_id' => $groupId]);

        return (bool) $stmt->fetchColumn();
    }

    public function lastInsertId(): int
    {
        return (int) $this->db->lastInsertId();
    }
}
