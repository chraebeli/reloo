<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use Throwable;

final class User
{
    private ?bool $pendingEmailSupported = null;

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
        if ($this->supportsPendingEmail()) {
            $stmt = $this->db->prepare('SELECT id, name, display_name, email, pending_email, role, approval_status, email_verified_at, password_hash FROM users WHERE id = :id LIMIT 1');
        } else {
            $stmt = $this->db->prepare('SELECT id, name, display_name, email, NULL AS pending_email, role, approval_status, email_verified_at, password_hash FROM users WHERE id = :id LIMIT 1');
        }

        $stmt->execute(['id' => $userId]);
        return $stmt->fetch() ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        return $stmt->fetch() ?: null;
    }


    public function pendingEmailFeatureEnabled(): bool
    {
        return $this->supportsPendingEmail();
    }

    public function isEmailInUse(string $email, ?int $ignoreUserId = null): bool
    {
        if ($this->supportsPendingEmail()) {
            $sql = 'SELECT COUNT(*) FROM users WHERE (email = :email OR pending_email = :pending_email)';
        } else {
            $sql = 'SELECT COUNT(*) FROM users WHERE email = :email';
        }

        $params = ['email' => $email];
        if ($this->supportsPendingEmail()) {
            $params['pending_email'] = $email;
        }

        if ($ignoreUserId !== null) {
            $sql .= ' AND id != :ignore_user_id';
            $params['ignore_user_id'] = $ignoreUserId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function markEmailVerified(int $userId): void
    {
        try {
            $stmt = $this->db->prepare('UPDATE users SET email_verified_at = COALESCE(email_verified_at, NOW()), updated_at = NOW() WHERE id = :id');
            $stmt->execute(['id' => $userId]);
        } catch (Throwable) {
            $stmt = $this->db->prepare('UPDATE users SET email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = :id');
            $stmt->execute(['id' => $userId]);
        }
    }

    public function setPendingEmail(int $userId, string $email): void
    {
        if (!$this->supportsPendingEmail()) {
            return;
        }

        $stmt = $this->db->prepare('UPDATE users SET pending_email = :email, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['email' => $email, 'id' => $userId]);
    }

    public function applyVerifiedPendingEmail(int $userId, string $email): bool
    {
        if (!$this->supportsPendingEmail()) {
            return false;
        }

        $stmt = $this->db->prepare('UPDATE users SET email = :new_email, pending_email = NULL, email_verified_at = NOW(), updated_at = NOW() WHERE id = :id AND pending_email = :pending_email');
        $stmt->execute([
            'new_email' => $email,
            'pending_email' => $email,
            'id' => $userId,
        ]);

        return $stmt->rowCount() === 1;
    }

    public function clearPendingEmail(int $userId): void
    {
        if (!$this->supportsPendingEmail()) {
            return;
        }

        $stmt = $this->db->prepare('UPDATE users SET pending_email = NULL, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $userId]);
    }

    public function updateDisplayName(int $userId, string $displayName): void
    {
        $stmt = $this->db->prepare('UPDATE users SET display_name = :display_name, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['display_name' => $displayName, 'id' => $userId]);
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

    private function supportsPendingEmail(): bool
    {
        if ($this->pendingEmailSupported !== null) {
            return $this->pendingEmailSupported;
        }

        try {
            $stmt = $this->db->query("SHOW COLUMNS FROM users LIKE 'pending_email'");
            $this->pendingEmailSupported = (bool) $stmt?->fetch();
        } catch (Throwable) {
            $this->pendingEmailSupported = false;
        }

        return $this->pendingEmailSupported;
    }
}
