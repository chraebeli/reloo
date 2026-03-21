<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class User
{
    public function __construct(private PDO $db)
    {
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('INSERT INTO users (name, display_name, email, password_hash, phone, location, bio, role, approval_status, email_verified_at, created_at) VALUES (:name, :display_name, :email, :password_hash, :phone, :location, :bio, :role, :approval_status, :email_verified_at, NOW())');
        $stmt->execute($data);

        return (int) $this->db->lastInsertId();
    }

    public function findById(int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT id, name, display_name, email, role FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        return $stmt->fetch() ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        return $stmt->fetch() ?: null;
    }

    public function markEmailVerified(int $userId): void
    {
        $stmt = $this->db->prepare('UPDATE users SET email_verified_at = COALESCE(email_verified_at, NOW()), updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $userId]);
    }

    public function setResetToken(int $userId, string $token, string $expiresAt): void
    {
        $stmt = $this->db->prepare('UPDATE users SET password_reset_token = :token, password_reset_expires_at = :expires_at WHERE id = :id');
        $stmt->execute(['token' => $token, 'expires_at' => $expiresAt, 'id' => $userId]);
    }

    public function findByResetToken(string $token): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE password_reset_token = :token AND password_reset_expires_at > NOW() LIMIT 1');
        $stmt->execute(['token' => $token]);
        return $stmt->fetch() ?: null;
    }

    public function updatePassword(int $userId, string $hash): void
    {
        $stmt = $this->db->prepare('UPDATE users SET password_hash = :hash, password_reset_token = NULL, password_reset_expires_at = NULL, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['hash' => $hash, 'id' => $userId]);
    }

    public function all(?string $statusFilter = null): array
    {
        $sql = 'SELECT id, name, display_name, email, role, location, approval_status, approved_at, approved_by, rejected_at, rejected_by, email_verified_at, created_at FROM users';
        $params = [];

        if (in_array($statusFilter, ['pending', 'approved', 'rejected'], true)) {
            $sql .= ' WHERE approval_status = :approval_status';
            $params['approval_status'] = $statusFilter;
        }

        $sql .= ' ORDER BY created_at DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function updateApprovalStatus(int $userId, string $status, int $adminId): void
    {
        if ($status === 'approved') {
            $stmt = $this->db->prepare('UPDATE users SET approval_status = :status, approved_at = NOW(), approved_by = :admin_id, rejected_at = NULL, rejected_by = NULL, updated_at = NOW() WHERE id = :id');
            $stmt->execute(['status' => $status, 'admin_id' => $adminId, 'id' => $userId]);
            return;
        }

        if ($status === 'rejected') {
            $stmt = $this->db->prepare('UPDATE users SET approval_status = :status, rejected_at = NOW(), rejected_by = :admin_id, approved_at = NULL, approved_by = NULL, updated_at = NOW() WHERE id = :id');
            $stmt->execute(['status' => $status, 'admin_id' => $adminId, 'id' => $userId]);
            return;
        }

        $stmt = $this->db->prepare('UPDATE users SET approval_status = :status, approved_at = NULL, approved_by = NULL, rejected_at = NULL, rejected_by = NULL, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['status' => 'pending', 'id' => $userId]);
    }
}
