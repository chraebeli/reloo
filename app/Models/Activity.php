<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class Activity
{
    public function __construct(private PDO $db)
    {
    }

    public function log(int $userId, ?int $groupId, string $type, string $message): void
    {
        $stmt = $this->db->prepare('INSERT INTO activity_log (user_id, group_id, activity_type, message, created_at) VALUES (:user_id, :group_id, :activity_type, :message, NOW())');
        $stmt->execute([
            'user_id' => $userId,
            'group_id' => $groupId,
            'activity_type' => $type,
            'message' => $message,
        ]);
    }

    public function recentForUser(int $userId, int $limit = 20): array
    {
        $stmt = $this->db->prepare('SELECT al.*, u.display_name FROM activity_log al LEFT JOIN users u ON u.id = al.user_id LEFT JOIN group_members gm ON gm.group_id = al.group_id WHERE al.group_id IS NULL OR gm.user_id = :user_id GROUP BY al.id ORDER BY al.created_at DESC LIMIT ' . (int) $limit);
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }
}
