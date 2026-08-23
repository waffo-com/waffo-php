<?php

declare(strict_types=1);

namespace Waffo\Crypto;

use OpenSSLAsymmetricKey;
use Waffo\Exception\WaffoError;

final class Rsa
{
    public static function assertPrivateKey(string $privateKey): void
    {
        self::loadPrivateKey($privateKey);
    }

    public static function assertPublicKey(string $publicKey): void
    {
        self::loadPublicKey($publicKey);
    }

    public static function sign(string $data, string $privateKey): string
    {
        $key = self::loadPrivateKey($privateKey);

        $signature = '';
        if (!openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new WaffoError('S0003', 'Failed to sign data');
        }

        return base64_encode($signature);
    }

    public static function verify(string $data, string $signature, string $publicKey): bool
    {
        $key = self::loadPublicKey($publicKey);

        $decoded = base64_decode($signature, true);
        if ($decoded === false) {
            return false;
        }

        return openssl_verify($data, $decoded, $key, OPENSSL_ALGO_SHA256) === 1;
    }

    /** @return array{privateKey: string, publicKey: string} */
    public static function generateKeyPair(int $bits = 2048): array
    {
        $key = openssl_pkey_new([
            'private_key_bits' => $bits,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if (!$key instanceof OpenSSLAsymmetricKey) {
            throw new WaffoError('S0007', 'Failed to generate private key');
        }

        $privatePem = '';
        if (!openssl_pkey_export($key, $privatePem)) {
            throw new WaffoError('S0007', 'Failed to export private key');
        }
        $details = openssl_pkey_get_details($key);
        if (!is_array($details) || !isset($details['key'])) {
            throw new WaffoError('S0002', 'Failed to export public key');
        }

        return [
            'privateKey' => self::pemBody($privatePem),
            'publicKey' => self::pemBody((string) $details['key']),
        ];
    }

    private static function privatePem(string $key): string
    {
        $trimmed = trim($key);
        if (str_starts_with($trimmed, '-----BEGIN PRIVATE KEY-----')) {
            return $trimmed;
        }
        if (str_contains($trimmed, '-----BEGIN ')) {
            return '';
        }

        return self::wrapPem($trimmed, 'PRIVATE KEY');
    }

    private static function publicPem(string $key): string
    {
        $trimmed = trim($key);
        if (str_starts_with($trimmed, '-----BEGIN PUBLIC KEY-----')) {
            return $trimmed;
        }
        if (str_contains($trimmed, '-----BEGIN ')) {
            return '';
        }

        return self::wrapPem($trimmed, 'PUBLIC KEY');
    }

    private static function loadPrivateKey(string $privateKey): OpenSSLAsymmetricKey
    {
        $key = openssl_pkey_get_private(self::privatePem($privateKey));
        $details = $key instanceof OpenSSLAsymmetricKey ? openssl_pkey_get_details($key) : false;
        if (!$key instanceof OpenSSLAsymmetricKey
            || !is_array($details)
            || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_RSA) {
            throw new WaffoError('S0007', 'Invalid private key format; expected an unencrypted PKCS#8 RSA key');
        }

        return $key;
    }

    private static function loadPublicKey(string $publicKey): OpenSSLAsymmetricKey
    {
        $key = openssl_pkey_get_public(self::publicPem($publicKey));
        $details = $key instanceof OpenSSLAsymmetricKey ? openssl_pkey_get_details($key) : false;
        if (!$key instanceof OpenSSLAsymmetricKey
            || !is_array($details)
            || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_RSA) {
            throw new WaffoError('S0002', 'Invalid public key format; expected an X.509 RSA key');
        }

        return $key;
    }

    private static function wrapPem(string $body, string $label): string
    {
        $normalized = preg_replace('/\s+/', '', $body);
        if (!is_string($normalized) || base64_decode($normalized, true) === false) {
            return '';
        }

        return "-----BEGIN {$label}-----\n"
            . chunk_split($normalized, 64, "\n")
            . "-----END {$label}-----\n";
    }

    private static function pemBody(string $pem): string
    {
        return (string) preg_replace('/-----BEGIN [^-]+-----|-----END [^-]+-----|\s+/', '', $pem);
    }

    private function __construct()
    {
    }
}
