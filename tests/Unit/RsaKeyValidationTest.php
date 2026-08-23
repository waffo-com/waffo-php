<?php

declare(strict_types=1);

namespace Waffo\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Waffo\Crypto\Rsa;
use Waffo\Exception\WaffoError;
use Waffo\Tests\Support\FixtureKeys;

final class RsaKeyValidationTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function unsupportedPrivateKeyPemLabels(): iterable
    {
        $privateKey = FixtureKeys::load()['privateKey'];

        yield 'PKCS#1 PEM label' => [self::pem($privateKey, 'RSA PRIVATE KEY')];
        yield 'encrypted PKCS#8 PEM label' => [self::pem($privateKey, 'ENCRYPTED PRIVATE KEY')];
    }

    #[DataProvider('unsupportedPrivateKeyPemLabels')]
    public function testRejectsUnsupportedPrivateKeyPemLabels(string $privateKey): void
    {
        try {
            Rsa::assertPrivateKey($privateKey);
            self::fail('Unsupported private key format was accepted');
        } catch (WaffoError $error) {
            self::assertSame('S0007', $error->errorCode);
            self::assertStringNotContainsString('ASN.1', $error->getMessage());
            self::assertStringNotContainsString('wrong tag', $error->getMessage());
        }
    }

    public function testRejectsNonRsaPrivateKey(): void
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        self::assertNotFalse($key);
        $pem = '';
        self::assertTrue(openssl_pkey_export($key, $pem));

        $this->expectException(WaffoError::class);
        $this->expectExceptionMessage('expected an unencrypted PKCS#8 RSA key');
        Rsa::assertPrivateKey($pem);
    }

    private static function pem(string $base64Body, string $label): string
    {
        return "-----BEGIN {$label}-----\n"
            . chunk_split($base64Body, 64, "\n")
            . "-----END {$label}-----\n";
    }
}
