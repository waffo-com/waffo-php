<?php

declare(strict_types=1);

namespace Waffo\Tests\E2E;

use PHPUnit\Framework\TestCase;
use Waffo\Model\Generated\AcqOrderCreatedResponse;
use Waffo\Model\Generated\AcqOrderInquiry;
use Waffo\Model\Generated\PayMethodConfigInquiryResponse;
use Waffo\Model\Generated\WalletInquiryResponse;
use Waffo\Response\ApiResponse;
use Waffo\Waffo;

/** @group e2e */
final class SandboxApiE2ETest extends TestCase
{
    private static Waffo $waffo;
    private static E2EArtifactRecorder $artifacts;

    public static function setUpBeforeClass(): void
    {
        if (getenv('WAFFO_E2E') !== '1') {
            self::markTestSkipped('Set WAFFO_E2E=1 to run sandbox E2E tests');
        }
        $config = E2EConfig::load();
        self::$waffo = new Waffo($config->toSdkConfig());
        self::$artifacts = new E2EArtifactRecorder();
        self::$artifacts->record('session', 'start', [
            'sdk' => 'waffo-php',
            'environment' => $config->environment->value,
            'merchantId' => $config->merchantId,
            'phpVersion' => PHP_VERSION,
        ]);
    }

    public function testMerchantAndPayMethodConfigurationInquiry(): void
    {
        $case = 'configuration-inquiry';
        $merchant = self::$waffo->merchantConfig()->inquiry();
        $this->assertApiSuccess($case, 'merchant-config', $merchant);
        self::assertNotNull($merchant->data, E2EArtifactRecorder::formatResponse($merchant));

        $payMethods = self::$waffo->payMethodConfig()->inquiry();
        $this->assertApiSuccess($case, 'pay-method-config', $payMethods);
        $payMethodsDebug = E2EArtifactRecorder::formatResponse($payMethods);
        self::assertInstanceOf(PayMethodConfigInquiryResponse::class, $payMethods->data, $payMethodsDebug);
        self::assertNotEmpty($payMethods->data->payMethodDetails, $payMethodsDebug);
    }

    public function testSignedOrderCreateInquiryAndCancel(): void
    {
        $case = 'order-create-inquiry-cancel';
        $paymentRequestId = E2EFixtures::uniqueId('php_cancel');
        $merchantOrderId = E2EFixtures::uniqueId('PHP_CANCEL');
        $create = self::$waffo->order()->create(E2EFixtures::danaOrderPayload(
            $paymentRequestId,
            $merchantOrderId,
            '10000',
            'https://httpbin.org/post',
        ));
        $ids = ['paymentRequestId' => $paymentRequestId, 'merchantOrderId' => $merchantOrderId];
        $this->assertApiSuccess($case, 'create', $create, $ids);
        $createDebug = E2EArtifactRecorder::formatResponse($create);
        self::assertInstanceOf(AcqOrderCreatedResponse::class, $create->data, $createDebug);
        self::assertNotSame('', (string) $create->data->acquiringOrderId, $createDebug);
        self::assertNotSame('', (string) $create->data->orderAction, $createDebug);

        $inquiry = self::$waffo->order()->inquiry(['paymentRequestId' => $paymentRequestId]);
        $this->assertApiSuccess($case, 'inquiry-before-cancel', $inquiry, $ids);
        $inquiryDebug = E2EArtifactRecorder::formatResponse($inquiry);
        self::assertInstanceOf(AcqOrderInquiry::class, $inquiry->data, $inquiryDebug);
        self::assertSame($paymentRequestId, $inquiry->data->paymentRequestId, $inquiryDebug);

        $cancel = self::$waffo->order()->cancel(['paymentRequestId' => $paymentRequestId]);
        $this->assertApiSuccess($case, 'cancel', $cancel, $ids);

        $closed = $this->waitForOrderStatus($case, $paymentRequestId, 'ORDER_CLOSE');
        self::assertSame(
            $create->data->acquiringOrderId,
            $closed->acquiringOrderId,
            json_encode($ids, JSON_THROW_ON_ERROR),
        );
    }

    public function testWalletAddressInquiry(): void
    {
        $case = 'wallet-inquiry';
        $paymentRequestId = E2EFixtures::uniqueId('php_wallet');
        $response = self::$waffo->wallet()->inquiry([
            'paymentRequestId' => $paymentRequestId,
            'orderCurrency' => 'USDC',
        ]);
        self::$artifacts->recordResponse($case, 'inquiry', $response);
        if ($response->code === 'C0001') {
            self::markTestSkipped('Sandbox merchant is not enabled for x402 wallet inquiry (C0001)');
        }
        self::assertTrue(
            $response->isSuccess(),
            "{$case}/inquiry failed: paymentRequestId={$paymentRequestId}, response="
                . E2EArtifactRecorder::formatResponse($response),
        );
        $responseDebug = E2EArtifactRecorder::formatResponse($response);
        self::assertInstanceOf(WalletInquiryResponse::class, $response->data, $responseDebug);
        self::assertNotSame('', (string) $response->data->network, $responseDebug);
        self::assertNotSame('', (string) $response->data->asset, $responseDebug);
        self::assertNotSame('', (string) $response->data->payTo, $responseDebug);
    }

    /** @param array<string, string> $requestIds */
    private function assertApiSuccess(
        string $case,
        string $phase,
        ApiResponse $response,
        array $requestIds = [],
    ): void {
        self::$artifacts->recordResponse($case, $phase, $response);
        self::assertTrue(
            $response->isSuccess(),
            "{$case}/{$phase} failed: requestIds="
                . json_encode($requestIds, JSON_THROW_ON_ERROR)
                . ', response=' . E2EArtifactRecorder::formatResponse($response),
        );
    }

    private function waitForOrderStatus(string $case, string $paymentRequestId, string $expected): AcqOrderInquiry
    {
        $deadline = microtime(true) + 90;
        do {
            $response = self::$waffo->order()->inquiry(['paymentRequestId' => $paymentRequestId]);
            $lastResponse = $response;
            self::$artifacts->recordResponse($case, 'poll-' . $expected, $response);
            if ($response->isSuccess() && $response->data instanceof AcqOrderInquiry) {
                if ($response->data->orderStatus === $expected) {
                    return $response->data;
                }
            }
            usleep(2_000_000);
        } while (microtime(true) < $deadline);

        self::fail(
            "Timed out waiting for order {$paymentRequestId} status {$expected}; response="
                . E2EArtifactRecorder::formatResponse($lastResponse),
        );
    }
}
