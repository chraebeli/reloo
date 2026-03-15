<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class Notifier
{
    public function __construct(private PDO $db, private array $config)
    {
    }

    public function notifyEmail(int $userId, string $subject, string $message): void
    {
        $stmt = $this->db->prepare('SELECT email FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $email = $stmt->fetchColumn();

        if (!is_string($email) || $email === '') {
            return;
        }

        $this->createInAppNotification($userId, $subject, $message, 'email');

        $from = (string) ($this->config['app']['mail_from'] ?? 'noreply@example.org');
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/plain; charset=UTF-8',
            'From: ' . $from,
            'X-Mailer: PHP/' . PHP_VERSION,
        ];

        @mail($email, $subject, $message, implode("\r\n", $headers));
    }

    public function createInAppNotification(int $userId, string $subject, string $message, string $channel = 'in_app'): void
    {
        $stmt = $this->db->prepare('INSERT INTO notifications (user_id, channel, subject, message, sent_at, created_at) VALUES (:user_id, :channel, :subject, :message, :sent_at, NOW())');
        $stmt->execute([
            'user_id' => $userId,
            'channel' => $channel,
            'subject' => mb_substr($subject, 0, 190),
            'message' => $message,
            'sent_at' => $channel === 'email' ? date('Y-m-d H:i:s') : null,
        ]);
    }
}
