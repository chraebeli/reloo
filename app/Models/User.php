<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class User
{
    public function __construct(private PDO $db)
    {
    }

    public function create(array $data): bool
    {
        $stmt = $this->db->prepare('INSERT INTO users (name, display_name, email, password_hash, phone, location, bio, role, created_at) VALUES (:name, :display_name, :email, :password_hash, :phone, :location, :bio, :role, NOW())');
        return $stmt->execute($data);
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        return $stmt->fetch() ?: null;
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

    public function all(): array
    {
        return $this->db->query('SELECT id, name, display_name, email, role, location, created_at FROM users ORDER BY created_at DESC')->fetchAll();
    }
}
