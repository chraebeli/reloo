<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class UserPasskey
{
    public function __construct(private PDO $db)
    {
    }

    public function create(array $data): void
    {
        $stmt = $this->db->prepare('INSERT INTO user_passkeys (user_id, label, credential_id, public_key_spki, sign_count, transports, created_at, last_used_at) VALUES (:user_id, :label, :credential_id, :public_key_spki, :sign_count, :transports, NOW(), NULL)');
        $stmt->execute($data);
    }

    public function findByCredentialId(string $credentialId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM user_passkeys WHERE credential_id = :credential_id LIMIT 1');
        $stmt->execute(['credential_id' => $credentialId]);

        return $stmt->fetch() ?: null;
    }

    public function listByUserId(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT id, label, created_at, last_used_at FROM user_passkeys WHERE user_id = :user_id ORDER BY created_at DESC');
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    public function listCredentialIdsByUserId(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT credential_id FROM user_passkeys WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);

        return array_map(static fn (array $row): string => (string) $row['credential_id'], $stmt->fetchAll());
    }

    public function deleteForUser(int $passkeyId, int $userId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM user_passkeys WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['id' => $passkeyId, 'user_id' => $userId]);

        return $stmt->rowCount() === 1;
    }

    public function touchUsage(int $id, int $signCount): void
    {
        $stmt = $this->db->prepare('UPDATE user_passkeys SET sign_count = :sign_count, last_used_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id, 'sign_count' => $signCount]);
    }
}
