<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class Item
{
    public function __construct(private PDO $db)
    {
    }

    public function create(array $data): bool
    {
        $stmt = $this->db->prepare('INSERT INTO items (group_id, owner_id, category_id, title, description, item_condition, ownership_type, location_text, availability_status, deposit_note, tags, visibility, created_at) VALUES (:group_id, :owner_id, :category_id, :title, :description, :item_condition, :ownership_type, :location_text, :availability_status, :deposit_note, :tags, :visibility, NOW())');
        return $stmt->execute($data);
    }

    public function addImage(int $itemId, string $path): void
    {
        $stmt = $this->db->prepare('INSERT INTO item_images (item_id, file_path, created_at) VALUES (:item_id, :file_path, NOW())');
        $stmt->execute(['item_id' => $itemId, 'file_path' => $path]);
    }

    public function lastInsertId(): int
    {
        return (int) $this->db->lastInsertId();
    }

    public function searchForUser(int $userId, ?string $q = null, ?int $groupId = null): array
    {
        $sql = 'SELECT i.*, c.name AS category_name, u.display_name AS owner_name,
                (SELECT file_path FROM item_images img WHERE img.item_id = i.id ORDER BY img.id LIMIT 1) AS image
                FROM items i
                JOIN group_members gm ON gm.group_id = i.group_id AND gm.user_id = :user_id
                LEFT JOIN categories c ON c.id = i.category_id
                JOIN users u ON u.id = i.owner_id
                WHERE i.deleted_at IS NULL';

        $params = ['user_id' => $userId];

        if ($q) {
            $sql .= ' AND (i.title LIKE :q OR i.description LIKE :q OR i.tags LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        if ($groupId) {
            $sql .= ' AND i.group_id = :group_id';
            $params['group_id'] = $groupId;
        }

        $sql .= ' ORDER BY i.created_at DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findForUser(int $itemId, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT i.*, c.name AS category_name, u.display_name AS owner_name
            FROM items i
            JOIN group_members gm ON gm.group_id = i.group_id AND gm.user_id = :user_id
            LEFT JOIN categories c ON c.id = i.category_id
            JOIN users u ON u.id = i.owner_id
            WHERE i.id = :item_id AND i.deleted_at IS NULL LIMIT 1');
        $stmt->execute(['item_id' => $itemId, 'user_id' => $userId]);
        $item = $stmt->fetch();
        if (!$item) {
            return null;
        }
        $imagesStmt = $this->db->prepare('SELECT * FROM item_images WHERE item_id = :item_id');
        $imagesStmt->execute(['item_id' => $itemId]);
        $item['images'] = $imagesStmt->fetchAll();
        return $item;
    }

    public function findDeletionCandidateById(int $itemId): ?array
    {
        $stmt = $this->db->prepare('SELECT i.*, u.display_name AS owner_name
            FROM items i
            JOIN users u ON u.id = i.owner_id
            WHERE i.id = :item_id
            LIMIT 1');
        $stmt->execute(['item_id' => $itemId]);

        return $stmt->fetch() ?: null;
    }

    public function findBlockingState(int $itemId): ?string
    {
        $loanStmt = $this->db->prepare('SELECT 1 FROM loans WHERE item_id = :item_id AND status = :status LIMIT 1');
        $loanStmt->execute(['item_id' => $itemId, 'status' => 'ausgeliehen']);
        if ((bool) $loanStmt->fetchColumn()) {
            return 'loan';
        }

        $requestStmt = $this->db->prepare('SELECT 1 FROM item_requests WHERE item_id = :item_id AND status IN (:angefragt, :reserviert) LIMIT 1');
        $requestStmt->execute([
            'item_id' => $itemId,
            'angefragt' => 'angefragt',
            'reserviert' => 'reserviert',
        ]);
        if ((bool) $requestStmt->fetchColumn()) {
            return 'request';
        }

        $repairStmt = $this->db->prepare('SELECT 1 FROM repairs WHERE item_id = :item_id AND status IN (:gemeldet, :in_pruefung, :in_reparatur) LIMIT 1');
        $repairStmt->execute([
            'item_id' => $itemId,
            'gemeldet' => 'gemeldet',
            'in_pruefung' => 'in_pruefung',
            'in_reparatur' => 'in_reparatur',
        ]);
        if ((bool) $repairStmt->fetchColumn()) {
            return 'repair';
        }

        return null;
    }

    public function softDelete(int $itemId, int $deletedBy, string $deletedByRole, ?string $reason): void
    {
        $stmt = $this->db->prepare('UPDATE items SET availability_status = :availability_status, deleted_at = NOW(), deleted_by = :deleted_by, deleted_by_role = :deleted_by_role, deletion_reason = :deletion_reason, updated_at = NOW() WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute([
            'availability_status' => 'deaktiviert',
            'deleted_by' => $deletedBy,
            'deleted_by_role' => $deletedByRole,
            'deletion_reason' => $reason,
            'id' => $itemId,
        ]);
    }

    public function logDeletion(array $data): void
    {
        $stmt = $this->db->prepare('INSERT INTO item_deletion_log (item_id, item_title, ownership_type, deleted_by, deleted_by_role, owner_id, admin_reason, created_at) VALUES (:item_id, :item_title, :ownership_type, :deleted_by, :deleted_by_role, :owner_id, :admin_reason, NOW())');
        $stmt->execute($data);
    }

    public function categories(): array
    {
        return $this->db->query('SELECT * FROM categories WHERE is_active = 1 ORDER BY name')->fetchAll();
    }
}
