<?php

declare(strict_types=1);

namespace Waffo\Tests\E2E;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Waffo\Model\Generated\AcqOrderCreatedResponse;
use Waffo\Model\Generated\AcqOrderInquiry;
use Waffo\Model\Generated\WalletInquiryResponse;
use Waffo\Response\ApiResponse;
use Waffo\Waffo;

#[Group('e2e')]
#[Group('x402')]
final class X402SandboxE2ETest extends TestCase
{
    private const AMOUNT = '0.01';
    private const ATOMIC_AMOUNT = '10000';

    private static Waffo $waffo;
    private static E2EArtifactRecorder $artifacts;

    public static function setUpBeforeClass(): void
    {
        if (getenv('WAFFO_E2E') !== '1' || getenv('WAFFO_X402_E2E') !== '1') {
            self::markTestSkipped('Set WAFFO_E2E=1 and WAFFO_X402_E2E=1 to run x402 E2E tests');
        }
        $config = E2EConfig::load();
        self::$waffo = new Waffo($config->toSdkConfig());
        self::$artifacts = new E2EArtifactRecorder();
        self::$artifacts->record('x402-session', 'start', [
            'sdk' => 'waffo-php',
            'environment' => $config->environment->value,
            'merchantId' => $config->merchantId,
            'phpVersion' => PHP_VERSION,
        ]);
    }

    public function testWalletInquiry(): void
    {
        $wallet = $this->inquireWallet();
        $expectedPayTo = getenv('WAFFO_X402_PAY_TO');
        self::assertIsString($expectedPayTo);
        self::assertSame(strtolower($expectedPayTo), strtolower((string) $wallet->payTo));
    }

    public function testHostedChallenge(): void
    {
        $this->verifyHostedMode($this->inquireWallet());
    }

    public function testDirectSettlement(): void
    {
        $this->verifyDirectMode($this->inquireWallet());
    }

    private function inquireWallet(): WalletInquiryResponse
    {
        $paymentRequestId = E2EFixtures::uniqueId('php_x402_wallet');
        $response = self::$waffo->wallet()->inquiry([
            'paymentRequestId' => $paymentRequestId,
            'orderCurrency' => 'USDC',
        ]);
        $this->assertApiSuccess('x402-wallet', 'inquiry', $response, [
            'paymentRequestId' => $paymentRequestId,
        ]);
        $debug = E2EArtifactRecorder::formatResponse($response);
        self::assertInstanceOf(WalletInquiryResponse::class, $response->data, $debug);
        self::assertSame('eip155:84532', $response->data->network, $debug);
        self::assertNotSame('', (string) $response->data->asset, $debug);
        self::assertNotSame('', (string) $response->data->payTo, $debug);

        return $response->data;
    }

    private function verifyHostedMode(WalletInquiryResponse $wallet): void
    {
        $case = 'x402-hosted-mode';
        $paymentRequestId = E2EFixtures::uniqueId('php_x402_hosted');
        $merchantOrderId = E2EFixtures::uniqueId('PHP_X402_HOSTED');
        $ids = compact('paymentRequestId', 'merchantOrderId');
        $create = self::$waffo->order()->create(E2EFixtures::x402OrderPayload(
            $paymentRequestId,
            $merchantOrderId,
            self::AMOUNT,
            E2EFixtures::notifyUrl(),
        ));
        $this->assertApiSuccess($case, 'create', $create, $ids);
        $debug = E2EArtifactRecorder::formatResponse($create);
        self::assertInstanceOf(AcqOrderCreatedResponse::class, $create->data, $debug);
        self::assertSame('AUTHORIZATION_REQUIRED', $create->data->orderStatus, $debug);

        $checkoutUrl = E2EFixtures::actionUrl($create->data->orderAction);
        self::assertStringContainsString('/cashier/api/v1/x402/', $checkoutUrl, $debug);
        [$status, $headers, $body] = $this->getChallenge($checkoutUrl);
        self::assertSame(402, $status, $body);
        $challenge = $this->decodeJson($body, 'x402 challenge body');
        $headerChallenge = $this->decodeBase64Json(
            (string) ($headers['payment-required'] ?? ''),
            'PAYMENT-REQUIRED header',
        );
        $this->assertChallenge($challenge, $checkoutUrl, $wallet);
        $this->assertChallenge($headerChallenge, $checkoutUrl, $wallet);
        self::$artifacts->record($case, 'challenge', [
            'paymentRequestId' => $paymentRequestId,
            'merchantOrderId' => $merchantOrderId,
            'acquiringOrderId' => $create->data->acquiringOrderId,
            'httpStatus' => $status,
            'paymentRequiredHeaderSha256' => hash('sha256', (string) $headers['payment-required']),
            'challenge' => $challenge,
        ]);

        $cancel = self::$waffo->order()->cancel(['paymentRequestId' => $paymentRequestId]);
        $this->assertApiSuccess($case, 'cancel', $cancel, $ids);
        $closed = $this->waitForOrder($case, $paymentRequestId, ['ORDER_CLOSE']);
        self::assertSame($create->data->acquiringOrderId, $closed->acquiringOrderId, $debug);
    }

    private function verifyDirectMode(WalletInquiryResponse $wallet): void
    {
        $signature = getenv('WAFFO_X402_PAYMENT_SIGNATURE');
        self::assertIsString($signature);
        self::assertNotSame('', $signature);

        $case = 'x402-direct-mode';
        $paymentRequestId = E2EFixtures::uniqueId('php_x402_direct');
        $merchantOrderId = E2EFixtures::uniqueId('PHP_X402_DIRECT');
        $ids = compact('paymentRequestId', 'merchantOrderId');
        $create = self::$waffo->order()->create(E2EFixtures::x402OrderPayload(
            $paymentRequestId,
            $merchantOrderId,
            self::AMOUNT,
            E2EFixtures::notifyUrl(),
            $signature,
        ));
        $this->assertApiSuccess($case, 'create', $create, $ids);
        $debug = E2EArtifactRecorder::formatResponse($create);
        self::assertInstanceOf(AcqOrderCreatedResponse::class, $create->data, $debug);
        self::assertSame('', E2EFixtures::actionUrl($create->data->orderAction), $debug);

        $settled = $create->data->orderStatus === 'PAY_SUCCESS'
            ? $this->inquireOrder($case, $paymentRequestId, 'settled-inquiry')
            : $this->waitForOrder($case, $paymentRequestId, ['PAY_SUCCESS', 'ORDER_CLOSE']);
        self::assertSame('PAY_SUCCESS', $settled->orderStatus, E2EArtifactRecorder::formatResponse(
            self::$waffo->order()->inquiry(['paymentRequestId' => $paymentRequestId]),
        ));
        self::assertTrue($settled->x402Info?->x402Request);
        self::assertNotSame('', (string) $settled->x402Info->x402Response);

        $settlement = $this->decodeBase64Json((string) $settled->x402Info->x402Response, 'x402Response');
        self::assertTrue($settlement['success'] ?? false, json_encode($settlement, JSON_THROW_ON_ERROR));
        self::assertNotSame('', (string) ($settlement['payer'] ?? ''), json_encode($settlement, JSON_THROW_ON_ERROR));
        self::assertNotSame('', (string) ($settlement['transaction'] ?? ''), json_encode($settlement, JSON_THROW_ON_ERROR));
        self::assertSame($wallet->network, $settlement['network'] ?? null, json_encode($settlement, JSON_THROW_ON_ERROR));
        self::$artifacts->record($case, 'settlement', [
            'paymentRequestId' => $paymentRequestId,
            'merchantOrderId' => $merchantOrderId,
            'acquiringOrderId' => $settled->acquiringOrderId,
            'orderStatus' => $settled->orderStatus,
            'settlement' => $settlement,
        ]);
    }

    /** @param array<string, string> $requestIds */
    private function assertApiSuccess(
        string $case,
        string $phase,
        ApiResponse $response,
        array $requestIds,
    ): void {
        self::$artifacts->recordResponse($case, $phase, $response);
        self::assertTrue(
            $response->isSuccess(),
            "{$case}/{$phase} failed: requestIds="
                . json_encode($requestIds, JSON_THROW_ON_ERROR)
                . ', response=' . E2EArtifactRecorder::formatResponse($response),
        );
    }

    /** @param list<string> $terminalStatuses */
    private function waitForOrder(string $case, string $paymentRequestId, array $terminalStatuses): AcqOrderInquiry
    {
        $deadline = microtime(true) + 180;
        do {
            $response = self::$waffo->order()->inquiry(['paymentRequestId' => $paymentRequestId]);
            $lastResponse = $response;
            self::$artifacts->recordResponse($case, 'poll-terminal', $response);
            if ($response->isSuccess()
                && $response->data instanceof AcqOrderInquiry
                && in_array($response->data->orderStatus, $terminalStatuses, true)) {
                return $response->data;
            }
            usleep(2_000_000);
        } while (microtime(true) < $deadline);

        self::fail(
            "Timed out waiting for x402 paymentRequestId={$paymentRequestId}, statuses="
                . implode(',', $terminalStatuses)
                . '; response=' . E2EArtifactRecorder::formatResponse($lastResponse),
        );
    }

    private function inquireOrder(string $case, string $paymentRequestId, string $phase): AcqOrderInquiry
    {
        $response = self::$waffo->order()->inquiry(['paymentRequestId' => $paymentRequestId]);
        $this->assertApiSuccess($case, $phase, $response, ['paymentRequestId' => $paymentRequestId]);
        $debug = E2EArtifactRecorder::formatResponse($response);
        self::assertInstanceOf(AcqOrderInquiry::class, $response->data, $debug);

        return $response->data;
    }

    /** @return array{int, array<string, string>, string} */
    private function getChallenge(string $url): array
    {
        $headers = [];
        $handle = curl_init($url);
        self::assertInstanceOf(\CurlHandle::class, $handle);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT_MS => 15_000,
            CURLOPT_TIMEOUT_MS => 60_000,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_HEADERFUNCTION => static function (\CurlHandle $_handle, string $line) use (&$headers): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return strlen($line);
            },
        ]);
        $body = curl_exec($handle);
        self::assertIsString($body, curl_error($handle));
        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        return [$status, $headers, $body];
    }

    /** @return array<string, mixed> */
    private function decodeJson(string $value, string $label): array
    {
        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded, $label);

        return $decoded;
    }

    /** @return array<string, mixed> */
    private function decodeBase64Json(string $value, string $label): array
    {
        self::assertNotSame('', $value, "{$label} is empty");
        $decoded = base64_decode($value, true);
        self::assertIsString($decoded, "{$label} is not valid base64");

        return $this->decodeJson($decoded, $label);
    }

    /** @param array<string, mixed> $challenge */
    private function assertChallenge(
        array $challenge,
        string $checkoutUrl,
        WalletInquiryResponse $wallet,
    ): void {
        $debug = json_encode($challenge, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        self::assertSame(2, $challenge['x402Version'] ?? null, $debug);
        self::assertSame($checkoutUrl, $challenge['resource']['url'] ?? null, $debug);
        self::assertNotSame('', (string) ($challenge['resource']['description'] ?? ''), $debug);
        self::assertArrayHasKey('accepts', $challenge, $debug);
        self::assertIsArray($challenge['accepts'], $debug);
        self::assertNotEmpty($challenge['accepts'], $debug);
        $accepted = $challenge['accepts'][0];
        self::assertSame('exact', $accepted['scheme'] ?? null, $debug);
        self::assertSame($wallet->network, $accepted['network'] ?? null, $debug);
        self::assertSame(self::ATOMIC_AMOUNT, (string) ($accepted['amount'] ?? ''), $debug);
        self::assertSame($wallet->asset, $accepted['asset'] ?? null, $debug);
        self::assertSame(strtolower((string) $wallet->payTo), strtolower((string) ($accepted['payTo'] ?? '')), $debug);
        self::assertGreaterThan(0, (int) ($accepted['maxTimeoutSeconds'] ?? 0), $debug);
        self::assertSame('USDC', $accepted['extra']['name'] ?? null, $debug);
        self::assertSame('2', (string) ($accepted['extra']['version'] ?? ''), $debug);
        self::assertArrayNotHasKey('maxAmountRequired', $accepted, $debug);
    }
}
