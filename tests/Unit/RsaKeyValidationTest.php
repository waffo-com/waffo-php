<?php

declare(strict_types=1);

namespace Waffo\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Waffo\Crypto\Rsa;
use Waffo\Exception\WaffoError;
use Waffo\Tests\Support\FixtureKeys;

final class RsaKeyValidationTest extends TestCase
{
    private const EC_PRIVATE_KEY = <<<'PEM'
-----BEGIN PRIVATE KEY-----
MIGHAgEAMBMGByqGSM49AgEGCCqGSM49AwEHBG0wawIBAQQgYnRIGzX4kUfM272F
pT5Hvv+YvACeGiGpUoiCT2cDXq6hRANCAAT54WCfdvdy/IVbCH9O/UnvgkZfa/FG
8HI4dGP+kwGuOBWujXpesG+HgWIy9LNGI4epxknboZcXsiQJS+rrpzbw
-----END PRIVATE KEY-----
PEM;

    /** @return iterable<string, array{string}> */
    public static function unsupportedPrivateKeyPemLabels(): iterable
    {
        $privateKey = FixtureKeys::load()['privateKey'];

        yield 'PKCS#1 PEM label' => [self::pem($privateKey, 'RSA PRIVATE KEY')];
        yield 'encrypted PKCS#8 PEM label' => [self::pem($privateKey, 'ENCRYPTED PRIVATE KEY')];
    }

    /** @dataProvider unsupportedPrivateKeyPemLabels */
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
        $this->expectException(WaffoError::class);
        $this->expectExceptionMessage('expected an unencrypted PKCS#8 RSA key');
        Rsa::assertPrivateKey(self::EC_PRIVATE_KEY);
    }

    private static function pem(string $base64Body, string $label): string
    {
        return "-----BEGIN {$label}-----\n"
            . chunk_split($base64Body, 64, "\n")
            . "-----END {$label}-----\n";
    }
}
