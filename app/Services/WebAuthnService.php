<?php

declare(strict_types=1);

namespace App\Services;

final class WebAuthnService
{
    public function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder > 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return (string) base64_decode(strtr($data, '-_', '+/'), true);
    }

    public function generateChallenge(int $bytes = 32): string
    {
        return $this->base64UrlEncode(random_bytes($bytes));
    }

    public function parseAuthenticatorData(string $authenticatorData): ?array
    {
        if (strlen($authenticatorData) < 37) {
            return null;
        }

        $rpIdHash = substr($authenticatorData, 0, 32);
        $flags = ord($authenticatorData[32]);
        $signCount = unpack('N', substr($authenticatorData, 33, 4));

        return [
            'rp_id_hash' => $rpIdHash,
            'flags' => $flags,
            'sign_count' => (int) ($signCount[1] ?? 0),
        ];
    }

    public function isUserPresent(int $flags): bool
    {
        return (bool) ($flags & 0x01);
    }

    public function verifyChallenge(array $clientData, string $expectedChallenge): bool
    {
        if (($clientData['challenge'] ?? '') !== $expectedChallenge) {
            return false;
        }

        return true;
    }

    public function verifyOrigin(array $clientData, string $expectedOrigin): bool
    {
        return ($clientData['origin'] ?? '') === $expectedOrigin;
    }

    public function verifyType(array $clientData, string $expectedType): bool
    {
        return ($clientData['type'] ?? '') === $expectedType;
    }

    public function verifyRpIdHash(string $rpIdHashBinary, string $rpId): bool
    {
        return hash_equals(hash('sha256', $rpId, true), $rpIdHashBinary);
    }

    public function derToPem(string $der): string
    {
        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
    }

    public function verifyAssertionSignature(string $authenticatorData, string $clientDataJson, string $signature, string $publicKeyDer): bool
    {
        $signedPayload = $authenticatorData . hash('sha256', $clientDataJson, true);
        $pem = $this->derToPem($publicKeyDer);

        return openssl_verify($signedPayload, $signature, $pem, OPENSSL_ALGO_SHA256) === 1;
    }
}
