<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class Notifier
{
    private const DEFAULT_EHLO_HOST = 'localhost';

    public function __construct(private PDO $db, private array $config)
    {
    }

    public function notifyEmail(int $userId, string $subject, string $messageText, ?string $messageHtml = null): bool
    {
        $stmt = $this->db->prepare('SELECT email FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $email = $stmt->fetchColumn();

        if (!is_string($email) || $email === '') {
            Logger::warning('Email delivery skipped because recipient address is missing', [
                'user_id' => $userId,
                'subject' => mb_substr($subject, 0, 120),
            ]);

            $this->createInAppNotification($userId, $subject, $messageText, 'email', null);
            return false;
        }

        try {
            $success = $this->sendViaSmtp($email, $subject, $messageText, $messageHtml);
        } catch (RuntimeException $exception) {
            Logger::error('SMTP delivery aborted due to invalid configuration', [
                'user_id' => $userId,
                'error' => $exception->getMessage(),
                'subject' => mb_substr($subject, 0, 120),
            ]);
            $success = false;
        }

        if (!$success) {
            Logger::warning('Email could not be delivered', [
                'user_id' => $userId,
                'driver' => 'smtp',
                'recipient_domain' => $this->extractDomain($email),
                'subject' => mb_substr($subject, 0, 120),
            ]);
        }

        $this->createInAppNotification($userId, $subject, $messageText, 'email', $success ? date('Y-m-d H:i:s') : null);

        return $success;
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

    private function sendViaSmtp(string $to, string $subject, string $messageText, ?string $messageHtml = null): bool
    {
        $mailConfig = $this->mailConfig();
        $host = $mailConfig['host'];
        $port = $mailConfig['port'];
        $user = $mailConfig['username'];
        $password = $mailConfig['password'];
        $encryption = $mailConfig['encryption'];
        $timeout = $mailConfig['timeout'];
        $from = $mailConfig['from_address'];
        $fromName = $mailConfig['from_name'];
        $replyTo = $mailConfig['reply_to'];
        $sender = $mailConfig['sender'];
        $ehloHost = $this->ehloHost($host);

        Logger::info('Preparing authenticated SMTP delivery', [
            'transport' => 'smtp',
            'host' => $host,
            'port' => $port,
            'encryption' => $encryption,
            'timeout' => $timeout,
            'auth' => true,
            'from_address' => $from,
            'sender' => $sender,
            'reply_to' => $replyTo,
            'recipient_domain' => $this->extractDomain($to),
        ]);

        $transport = $encryption === 'ssl' ? 'ssl://' . $host : $host;
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'peer_name' => $host,
                'SNI_enabled' => true,
            ],
        ]);

        $socket = @stream_socket_client($transport . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        if (!is_resource($socket)) {
            Logger::error('SMTP connection failed', [
                'error' => $errstr,
                'code' => $errno,
                'host' => $host,
                'port' => $port,
                'encryption' => $encryption,
            ]);
            return false;
        }

        stream_set_timeout($socket, $timeout);

        try {
            $this->smtpExpect($socket, [220]);
            $capabilities = $this->smtpEhlo($socket, $ehloHost);

            if ($encryption === 'tls') {
                $this->smtpCommand($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('STARTTLS konnte nicht aktiviert werden.');
                }
                $capabilities = $this->smtpEhlo($socket, $ehloHost);
            }

            $this->smtpAuthenticate($socket, $capabilities, $user, $password);

            Logger::info('SMTP authentication succeeded', [
                'host' => $host,
                'port' => $port,
                'encryption' => $encryption,
                'username' => $user,
            ]);

            $this->smtpCommand($socket, 'MAIL FROM:<' . $sender . '>', [250]);
            $this->smtpCommand($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            $this->smtpCommand($socket, 'DATA', [354]);

            [$headers, $payloadBody] = $this->buildMimePayload($to, $subject, $messageText, $messageHtml, $from, $fromName, $replyTo, $sender);
            $body = $this->normalizeSmtpBody($payloadBody);
            $payload = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.";

            fwrite($socket, $payload . "\r\n");
            $this->smtpExpect($socket, [250]);
            $this->smtpCommand($socket, 'QUIT', [221]);

            fclose($socket);

            Logger::info('SMTP message accepted by remote server', [
                'host' => $host,
                'port' => $port,
                'encryption' => $encryption,
                'recipient_domain' => $this->extractDomain($to),
                'subject' => mb_substr($subject, 0, 120),
            ]);

            return true;
        } catch (RuntimeException $exception) {
            Logger::error('SMTP send failed', [
                'error' => $exception->getMessage(),
                'host' => $host,
                'port' => $port,
                'encryption' => $encryption,
                'recipient_domain' => $this->extractDomain($to),
                'subject' => mb_substr($subject, 0, 120),
            ]);

            fclose($socket);
            return false;
        }
    }

    /**
     * @return array{host: string, port: int, username: string, password: string, encryption: string, timeout: int, from_address: string, from_name: string, sender: string, reply_to: string}
     */
    private function mailConfig(): array
    {
        $mailConfig = $this->config['mail'] ?? [];
        $host = trim((string) ($mailConfig['host'] ?? ''));
        $port = (int) ($mailConfig['port'] ?? 465);
        $username = trim((string) ($mailConfig['username'] ?? ''));
        $password = (string) ($mailConfig['password'] ?? '');
        $encryption = strtolower(trim((string) ($mailConfig['encryption'] ?? 'ssl')));
        $timeout = max(1, (int) ($mailConfig['timeout'] ?? 15));
        $fromAddress = trim((string) ($mailConfig['from_address'] ?? $this->config['app']['mail_from'] ?? ''));
        $fromName = trim((string) ($mailConfig['from_name'] ?? $this->config['app']['name'] ?? 'Reloo'));
        $sender = trim((string) ($mailConfig['sender'] ?? $fromAddress));
        $replyTo = trim((string) ($mailConfig['reply_to'] ?? $fromAddress));
        $driver = strtolower(trim((string) ($mailConfig['driver'] ?? 'smtp')));
        $auth = (bool) ($mailConfig['auth'] ?? true);

        if ($driver !== 'smtp') {
            throw new RuntimeException('Nur authentifizierter SMTP-Versand ist erlaubt.');
        }

        if ($auth !== true) {
            throw new RuntimeException('SMTP-Authentifizierung muss aktiviert sein.');
        }

        if ($host === '' || $username === '' || $password === '' || $fromAddress === '' || $sender === '' || $replyTo === '') {
            throw new RuntimeException('SMTP-Konfiguration ist unvollständig.');
        }

        if (strtolower($host) !== 'mandela.sui-inter.net') {
            throw new RuntimeException('SMTP-Host muss mandela.sui-inter.net sein.');
        }

        if ($port !== 465) {
            throw new RuntimeException('SMTP-Port muss auf 465 gesetzt sein.');
        }

        if ($encryption !== 'ssl') {
            throw new RuntimeException('SMTP-Verschlüsselung muss ssl/SMTPS sein.');
        }

        $normalizedUsername = strtolower($username);
        $normalizedFrom = strtolower($fromAddress);
        $normalizedSender = strtolower($sender);
        $normalizedReplyTo = strtolower($replyTo);
        if ($normalizedUsername !== 'notify@reloo.ch' || $normalizedFrom !== 'notify@reloo.ch' || $normalizedSender !== 'notify@reloo.ch' || $normalizedReplyTo !== 'notify@reloo.ch') {
            throw new RuntimeException('Absender, Sender, Reply-To und SMTP-Benutzer müssen einheitlich notify@reloo.ch sein.');
        }

        return [
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'password' => $password,
            'encryption' => $encryption,
            'timeout' => $timeout,
            'from_address' => $fromAddress,
            'from_name' => $fromName,
            'sender' => $sender,
            'reply_to' => $replyTo,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function buildCommonHeaders(string $from, string $fromName, string $replyTo, string $sender): array
    {
        $encodedFromName = mb_encode_mimeheader($fromName, 'UTF-8');

        return [
            'MIME-Version: 1.0',
            'From: ' . $encodedFromName . ' <' . $from . '>',
            'Sender: ' . $sender,
            'Reply-To: ' . $replyTo,
            'X-Mailer: Reloo SMTP Mailer',
        ];
    }

    /**
     * @return array{0: array<int, string>, 1: string}
     */
    private function buildMimePayload(string $to, string $subject, string $messageText, ?string $messageHtml, string $from, string $fromName, string $replyTo, string $sender): array
    {
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'To: ' . $to,
            'Subject: ' . mb_encode_mimeheader($subject, 'UTF-8'),
            'Message-ID: ' . $this->messageId(),
            ...$this->buildCommonHeaders($from, $fromName, $replyTo, $sender),
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

    /**
     * @return array<int, string>
     */
    private function smtpEhlo($socket, string $ehloHost): array
    {
        fwrite($socket, 'EHLO ' . $ehloHost . "\r\n");
        $response = $this->smtpReadResponse($socket);
        $code = (int) substr($response, 0, 3);
        if ($code !== 250) {
            throw new RuntimeException('Unerwartete SMTP-Antwort: ' . trim($response));
        }

        return $this->parseEhloCapabilities($response);
    }

    /**
     * @param array<int, string> $capabilities
     */
    private function smtpAuthenticate($socket, array $capabilities, string $user, string $password): void
    {
        $authLine = null;
        foreach ($capabilities as $capability) {
            if (str_starts_with(strtoupper($capability), 'AUTH ')) {
                $authLine = strtoupper(substr($capability, 5));
                break;
            }
        }

        if ($authLine === null) {
            throw new RuntimeException('SMTP-Server bietet keine AUTH-Erweiterung an.');
        }

        $methods = preg_split('/\s+/', trim($authLine)) ?: [];
        if (in_array('PLAIN', $methods, true)) {
            $payload = base64_encode("\0" . $user . "\0" . $password);
            $this->smtpCommand($socket, 'AUTH PLAIN ' . $payload, [235]);
            return;
        }

        if (in_array('LOGIN', $methods, true)) {
            $this->smtpCommand($socket, 'AUTH LOGIN', [334]);
            $this->smtpCommand($socket, base64_encode($user), [334]);
            $this->smtpCommand($socket, base64_encode($password), [235]);
            return;
        }

        throw new RuntimeException('Kein unterstütztes SMTP-AUTH-Verfahren verfügbar: ' . implode(', ', $methods));
    }

    private function smtpCommand($socket, string $command, array $expectedCodes): void
    {
        fwrite($socket, $command . "\r\n");
        $this->smtpExpect($socket, $expectedCodes);
    }

    private function smtpExpect($socket, array $expectedCodes): void
    {
        $response = $this->smtpReadResponse($socket);
        if ($response === '') {
            throw new RuntimeException('Leere SMTP-Antwort erhalten.');
        }

        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            throw new RuntimeException('Unerwartete SMTP-Antwort: ' . trim($response));
        }
    }

    private function smtpReadResponse($socket): string
    {
        $response = '';

        while (($line = fgets($socket, 512)) !== false) {
            $response .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }

        return $response;
    }

    /**
     * @return array<int, string>
     */
    private function parseEhloCapabilities(string $response): array
    {
        $lines = preg_split("/\r\n|\n|\r/", trim($response)) ?: [];
        $capabilities = [];

        foreach ($lines as $index => $line) {
            if ($index === 0) {
                continue;
            }

            if (strlen($line) <= 4) {
                continue;
            }

            $capabilities[] = trim(substr($line, 4));
        }

        return $capabilities;
    }

    private function ehloHost(string $host): string
    {
        $configured = trim((string) ($this->config['mail']['ehlo_domain'] ?? ''));
        if ($configured !== '') {
            return $configured;
        }

        $parts = explode('@', 'notify@reloo.ch');
        if (count($parts) === 2 && $parts[1] !== '') {
            return $parts[1];
        }

        return $host !== '' ? $host : self::DEFAULT_EHLO_HOST;
    }

    private function messageId(): string
    {
        $domain = $this->ehloHost(self::DEFAULT_EHLO_HOST);
        return '<' . bin2hex(random_bytes(16)) . '@' . $domain . '>';
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
