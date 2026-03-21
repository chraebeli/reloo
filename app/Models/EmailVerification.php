<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class EmailVerification
{
    public function __construct(private PDO $db)
    {
    }

    public function issueToken(int $userId, string $email, string $tokenHash, string $expiresAt): void
    {
        $this->invalidateOpenTokens($userId);

        $stmt = $this->db->prepare('INSERT INTO email_verifications (user_id, email, token_hash, expires_at, created_at) VALUES (:user_id, :email, :token_hash, :expires_at, NOW())');
        $stmt->execute([
            'user_id' => $userId,
            'email' => $email,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
        ]);
    }

    public function consumeValidToken(string $tokenHash): ?array
    {
        $stmt = $this->db->prepare('SELECT ev.id, ev.user_id, ev.email, u.display_name FROM email_verifications ev INNER JOIN users u ON u.id = ev.user_id WHERE ev.token_hash = :token_hash AND ev.used_at IS NULL AND ev.expires_at > NOW() LIMIT 1');
        $stmt->execute(['token_hash' => $tokenHash]);
        $record = $stmt->fetch();

        if (!$record) {
            return null;
        }

        $markUsed = $this->db->prepare('UPDATE email_verifications SET used_at = NOW() WHERE id = :id AND used_at IS NULL');
        $markUsed->execute(['id' => (int) $record['id']]);

        if ($markUsed->rowCount() !== 1) {
            return null;
        }

        return $record;
    }

    public function hasRecentOpenToken(int $userId, int $cooldownSeconds): bool
    {
        $threshold = date('Y-m-d H:i:s', time() - $cooldownSeconds);
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM email_verifications WHERE user_id = :user_id AND used_at IS NULL AND created_at >= :threshold');
        $stmt->execute([
            'user_id' => $userId,
            'threshold' => $threshold,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function invalidateOpenTokens(int $userId): void
    {
        $stmt = $this->db->prepare('UPDATE email_verifications SET used_at = NOW() WHERE user_id = :user_id AND used_at IS NULL');
        $stmt->execute(['user_id' => $userId]);
    }
}
