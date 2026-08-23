<?php

declare(strict_types=1);

namespace Waffo\Tests\Vector;

use PHPUnit\Framework\TestCase;
use Waffo\Crypto\Rsa;
use Waffo\Tests\Support\TestAsset;

final class RsaSigningVectorTest extends TestCase
{
    public function testAllRsaSigningAndVerificationCases(): void
    {
        $raw = file_get_contents(TestAsset::path(__DIR__, '../../../../sdk-spec/test-vectors/rsa-signing.json', '../../sdk-spec/test-vectors/rsa-signing.json'));
        self::assertNotFalse($raw);
        $vector = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $privateKey = $vector['testKeys']['privateKey'];
        $publicKey = $vector['testKeys']['publicKey'];

        foreach ($vector['signingTests'] as $case) {
            self::assertSame(
                $case['output']['signature'],
                Rsa::sign($case['input']['data'], $privateKey),
                $case['id'],
            );
        }
        foreach ($vector['verificationTests'] as $case) {
            $input = $case['input'];
            $data = $input['data'] ?? $input['tamperedData'];
            $signature = $input['signature'] ?? $input['signatureOfOriginal'];
            self::assertSame($case['expected']['valid'], Rsa::verify($data, $signature, $publicKey), $case['id']);
        }
    }
}
