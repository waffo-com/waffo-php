<?php

declare(strict_types=1);

namespace Waffo\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Waffo\Response\ApiResponse;
use Waffo\Tests\E2E\E2EArtifactRecorder;

final class E2EArtifactRecorderTest extends TestCase
{
    public function testRedactsCredentialsTokensAndUrlsFromEvidence(): void
    {
        $directory = sys_get_temp_dir() . '/waffo-evidence-' . bin2hex(random_bytes(8));
        putenv('WAFFO_E2E_ARTIFACT_DIR=' . $directory);
        try {
            (new E2EArtifactRecorder())->record('sanitization', 'unit', [
                'apiKey' => 'api-key-secret',
                'paymentTokenData' => 'payment-token-secret',
                'paymentInfo' => [
                    'payMethodProperties' => '{"x402PaymentSignature":"serialized-signature-secret"}',
                    'payMethodResponse' => '{"cardToken":"serialized-token-secret"}',
                ],
                'notifyUrl' => 'https://callback.example.test/private-path',
                'nested' => ['responseSignature' => 'signature-secret'],
            ]);
            $contents = file_get_contents($directory . '/events.ndjson');
            self::assertNotFalse($contents);
            self::assertStringNotContainsString('api-key-secret', $contents);
            self::assertStringNotContainsString('payment-token-secret', $contents);
            self::assertStringNotContainsString('serialized-signature-secret', $contents);
            self::assertStringNotContainsString('serialized-token-secret', $contents);
            self::assertStringNotContainsString('private-path', $contents);
            self::assertStringNotContainsString('signature-secret', $contents);
            self::assertStringContainsString('[REDACTED]', $contents);
            self::assertStringContainsString('callback.example.test', $contents);
            self::assertStringContainsString('sha256', $contents);
        } finally {
            putenv('WAFFO_E2E_ARTIFACT_DIR');
            if (is_file($directory . '/events.ndjson')) {
                unlink($directory . '/events.ndjson');
            }
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }

    public function testFormatsCompleteSanitizedResponseForFailureDiagnostics(): void
    {
        $formatted = E2EArtifactRecorder::formatResponse(new ApiResponse('A0001', 'Rejected', [
            'paymentRequestId' => 'request-1',
            'notifyUrl' => 'https://callback.example.test/private-path',
            'paymentTokenData' => 'payment-token-secret',
        ]));

        self::assertStringContainsString('"code":"A0001"', $formatted);
        self::assertStringContainsString('"message":"Rejected"', $formatted);
        self::assertStringContainsString('"paymentRequestId":"request-1"', $formatted);
        self::assertStringContainsString('callback.example.test', $formatted);
        self::assertStringNotContainsString('private-path', $formatted);
        self::assertStringNotContainsString('payment-token-secret', $formatted);
    }
}
