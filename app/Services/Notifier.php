<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class Notifier
{
    public function __construct(private PDO $db, private array $config)
    {
    }

    public function notifyEmail(int $userId, string $subject, string $messageText, ?string $messageHtml = null): void
    {
        $stmt = $this->db->prepare('SELECT email FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $email = $stmt->fetchColumn();

        if (!is_string($email) || $email === '') {
            return;
        }

        $driver = strtolower((string) (($this->config['mail']['driver'] ?? 'smtp')));
        $success = $driver === 'mail'
            ? $this->sendViaMail($email, $subject, $messageText, $messageHtml)
            : $this->sendViaSmtp($email, $subject, $messageText, $messageHtml);

        if (!$success) {
            Logger::warning('Email could not be delivered', [
                'user_id' => $userId,
                'driver' => $driver,
                'recipient_domain' => $this->extractDomain($email),
                'subject' => mb_substr($subject, 0, 120),
            ]);
        }

        $this->createInAppNotification($userId, $subject, $messageText, 'email', $success ? date('Y-m-d H:i:s') : null);
    }

    public function createInAppNotification(int $userId, string $subject, string $message, string $channel = 'in_app', ?string $sentAt = null): void
    {
        $stmt = $this->db->prepare('INSERT INTO notifications (user_id, channel, subject, message, sent_at, created_at) VALUES (:user_id, :channel, :subject, :message, :sent_at, NOW())');
        $stmt->execute([
            'user_id' => $userId,
            'channel' => $channel,
            'subject' => mb_substr($subject, 0, 190),
            'message' => $message,
            'sent_at' => $sentAt,
        ]);
    }

    private function sendViaMail(string $to, string $subject, string $messageText, ?string $messageHtml = null): bool
    {
        [$headers, $body] = $this->buildMimePayload($to, $subject, $messageText, $messageHtml);
        return mail($to, $subject, $body, implode("\r\n", $headers));
    }

    private function sendViaSmtp(string $to, string $subject, string $messageText, ?string $messageHtml = null): bool
    {
        $mailConfig = $this->config['mail'] ?? [];
        $host = trim((string) ($mailConfig['host'] ?? $mailConfig['smtp_host'] ?? ''));
        if ($host === '') {
            Logger::warning('SMTP host missing; falling back to PHP mail()');
            return $this->sendViaMail($to, $subject, $messageText, $messageHtml);
        }

        $port = (int) ($mailConfig['port'] ?? $mailConfig['smtp_port'] ?? 587);
        $user = (string) ($mailConfig['username'] ?? $mailConfig['smtp_user'] ?? '');
        $password = (string) ($mailConfig['password'] ?? $mailConfig['smtp_pass'] ?? '');
        $encryption = strtolower((string) ($mailConfig['encryption'] ?? $mailConfig['smtp_encryption'] ?? 'tls'));
        $timeout = (int) ($mailConfig['timeout'] ?? $mailConfig['smtp_timeout'] ?? 15);
        $authEnabled = (bool) ($mailConfig['auth'] ?? true);
        if ($authEnabled && ($user === '' || $password === '')) {
            Logger::error('SMTP auth enabled but credentials are incomplete', ['host' => $host, 'port' => $port]);
            return false;
        }

        $from = $this->mailFrom();

        $transport = $encryption === 'ssl' ? 'ssl://' . $host : $host;

        $socket = @stream_socket_client($transport . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
        if (!is_resource($socket)) {
            Logger::error('SMTP connection failed', ['error' => $errstr, 'code' => $errno, 'host' => $host, 'port' => $port]);
            return false;
        }

        stream_set_timeout($socket, $timeout);

        try {
            $this->smtpExpect($socket, [220]);
            $this->smtpCommand($socket, 'EHLO localhost', [250]);

            if ($encryption === 'tls') {
                $this->smtpCommand($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('STARTTLS konnte nicht aktiviert werden.');
                }
                $this->smtpCommand($socket, 'EHLO localhost', [250]);
            }

            if ($authEnabled && $user !== '' && $password !== '') {
                $this->smtpCommand($socket, 'AUTH LOGIN', [334]);
                $this->smtpCommand($socket, base64_encode($user), [334]);
                $this->smtpCommand($socket, base64_encode($password), [235]);
            }

            $this->smtpCommand($socket, 'MAIL FROM:<' . $from . '>', [250]);
            $this->smtpCommand($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            $this->smtpCommand($socket, 'DATA', [354]);

            [$headers, $payloadBody] = $this->buildMimePayload($to, $subject, $messageText, $messageHtml);
            $body = $this->normalizeSmtpBody($payloadBody);
            $payload = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.";

            fwrite($socket, $payload . "\r\n");
            $this->smtpExpect($socket, [250]);
            $this->smtpCommand($socket, 'QUIT', [221]);

            fclose($socket);
            return true;
        } catch (RuntimeException $exception) {
            Logger::error('SMTP send failed', [
                'error' => $exception->getMessage(),
                'host' => $host,
                'port' => $port,
                'recipient_domain' => $this->extractDomain($to),
            ]);

            fclose($socket);
            return false;
        }
    }

    /**
     * @return array<int, string>
     */
    private function buildCommonHeaders(): array
    {
        $from = $this->mailFrom();
        $fromName = (string) ($this->config['mail']['from_name'] ?? $this->config['mail']['smtp_from_name'] ?? $this->config['app']['name'] ?? 'Reloo');
        $encodedFromName = mb_encode_mimeheader($fromName, 'UTF-8');

        return [
            'MIME-Version: 1.0',
            'From: ' . $encodedFromName . ' <' . $from . '>',
            'Reply-To: ' . $from,
            'X-Mailer: PHP/' . PHP_VERSION,
        ];
    }

    private function mailFrom(): string
    {
        return (string) ($this->config['mail']['from_address'] ?? $this->config['mail']['smtp_from_email'] ?? $this->config['app']['mail_from'] ?? 'noreply@example.org');
    }

    /**
     * @return array{0: array<int, string>, 1: string}
     */
    private function buildMimePayload(string $to, string $subject, string $messageText, ?string $messageHtml = null): array
    {
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'To: ' . $to,
            'Subject: ' . mb_encode_mimeheader($subject, 'UTF-8'),
            ...$this->buildCommonHeaders(),
        ];

        if ($messageHtml === null || trim($messageHtml) === '') {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: 8bit';

            return [$headers, $messageText];
        }

        $boundary = 'reloo-' . bin2hex(random_bytes(12));
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

        $bodyParts = [
            '--' . $boundary,
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            '',
            $messageText,
            '',
            '--' . $boundary,
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            '',
            $messageHtml,
            '',
            '--' . $boundary . '--',
        ];

        return [$headers, implode("\r\n", $bodyParts)];
    }

    private function smtpCommand($socket, string $command, array $expectedCodes): void
    {
        fwrite($socket, $command . "\r\n");
        $this->smtpExpect($socket, $expectedCodes);
    }

    private function smtpExpect($socket, array $expectedCodes): void
    {
        $response = '';

        while (($line = fgets($socket, 512)) !== false) {
            $response .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }

        if ($response === '') {
            throw new RuntimeException('Leere SMTP-Antwort erhalten.');
        }

        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            throw new RuntimeException('Unerwartete SMTP-Antwort: ' . trim($response));
        }
    }

    private function normalizeSmtpBody(string $message): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $message);
        $normalized = preg_replace('/^\./m', '..', $normalized) ?? $normalized;

        return str_replace("\n", "\r\n", $normalized);
    }

    private function extractDomain(string $email): string
    {
        $parts = explode('@', $email);
        return count($parts) === 2 ? strtolower($parts[1]) : 'unknown';
    }
}
