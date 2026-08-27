<?php

declare(strict_types=1);

namespace Waffo\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Waffo\Config\WaffoConfig;
use Waffo\Http\HttpResponse;
use Waffo\Http\NetworkException;
use Waffo\Tests\Support\FakeHttpTransport;
use Waffo\Tests\Support\FixtureKeys;
use Waffo\Tests\Support\TestAsset;
use Waffo\Waffo;

final class ResourceTest extends TestCase
{
    /** @return iterable<string, array{non-empty-string, non-empty-string, non-empty-string, non-empty-string, bool, 'nested'|'top'|'none'}> */
    public static function operations(): iterable
    {
        yield 'order create' => ['order', 'create', 'orderCreate', '/order/create', true, 'nested'];
        yield 'order inquiry' => ['order', 'inquiry', 'orderInquiry', '/order/inquiry', false, 'none'];
        yield 'order cancel' => ['order', 'cancel', 'orderCancel', '/order/cancel', true, 'top'];
        yield 'order refund' => ['order', 'refund', 'orderRefund', '/order/refund', true, 'top'];
        yield 'order capture' => ['order', 'capture', 'orderCapture', '/order/capture', true, 'top'];
        yield 'refund inquiry' => ['refund', 'inquiry', 'refundInquiry', '/refund/inquiry', false, 'none'];
        yield 'subscription create' => ['subscription', 'create', 'createSubscription', '/subscription/create', true, 'nested'];
        yield 'subscription inquiry' => ['subscription', 'inquiry', 'subscriptionInquiry', '/subscription/inquiry', false, 'none'];
        yield 'subscription cancel' => ['subscription', 'cancel', 'merchantCancel', '/subscription/cancel', true, 'top'];
        yield 'subscription manage' => ['subscription', 'manage', 'subscriptionManage', '/subscription/manage', false, 'none'];
        yield 'subscription update' => ['subscription', 'update', 'subscriptionUpdate', '/subscription/update', false, 'none'];
        yield 'subscription change' => ['subscription', 'change', 'changeSubscription', '/subscription/change', true, 'nested'];
        yield 'subscription change inquiry' => ['subscription', 'changeInquiry', 'subscriptionChangeInquiry', '/subscription/change/inquiry', false, 'none'];
        yield 'merchant config' => ['merchantConfig', 'inquiry', 'merchantConfigInquiry', '/merchantconfig/inquiry', false, 'top'];
        yield 'pay method config' => ['payMethodConfig', 'inquiry', 'payMethodConfigInquiry', '/paymethodconfig/inquiry', false, 'top'];
        yield 'wallet inquiry' => ['wallet', 'inquiry', 'walletInquiry', '/wallet/inquiry', false, 'nested'];
    }

    /** @dataProvider operations */
    public function testPublicOperationUsesExpectedPath(
        string $capability,
        string $operation,
        string $operationId,
        string $path,
        bool $expectsTimestamp,
        string $merchantIdLocation,
    ): void {
        if ($path === '') {
            self::fail('Operation path must not be empty');
        }

        $keys = FixtureKeys::load();
        $transport = new FakeHttpTransport(new HttpResponse(200, [], '{"code":"0","data":{}}'));
        $waffo = new Waffo(new WaffoConfig(
            'api-key',
            $keys['privateKey'],
            $keys['publicKey'],
            merchantId: 'MID-1',
            httpTransport: $transport,
        ));

        $resource = $waffo->{$capability}();
        $response = $resource->{$operation}([]);

        self::assertTrue($response->isSuccess());
        self::assertStringEndsWith($path, $transport->requests[0]->url);
        self::assertSame($path, self::openApiPath($operationId));
        $payload = json_decode($transport->requests[0]->body, true, 512, JSON_THROW_ON_ERROR);
        match ($merchantIdLocation) {
            'nested' => self::assertSame('MID-1', $payload['merchantInfo']['merchantId']),
            'top' => self::assertSame('MID-1', $payload['merchantId']),
            'none' => self::assertSame([], array_intersect_key($payload, ['merchantId' => true, 'merchantInfo' => true])),
            default => self::fail("Unsupported merchantId location: {$merchantIdLocation}"),
        };
        if ($expectsTimestamp) {
            self::assertMatchesRegularExpression(
                '/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z/',
                json_encode($payload, JSON_THROW_ON_ERROR),
            );
        }
    }

    public function testInquiryPreservesNetworkUnknownStatusCode(): void
    {
        $keys = FixtureKeys::load();
        $waffo = new Waffo(new WaffoConfig(
            'api-key',
            $keys['privateKey'],
            $keys['publicKey'],
            httpTransport: new FakeHttpTransport(new NetworkException('timeout')),
        ));

        $response = $waffo->order()->inquiry(['acquiringOrderId' => 'A1']);

        self::assertSame('S0001', $response->code);
        self::assertStringContainsString('payment status unknown', (string) $response->message);
    }

    public function testInquiryPreservesServerUnknownStatusCode(): void
    {
        $keys = FixtureKeys::load();
        $waffo = new Waffo(new WaffoConfig(
            'api-key',
            $keys['privateKey'],
            $keys['publicKey'],
            httpTransport: new FakeHttpTransport(new HttpResponse(200, [], '{"code":"E0001","message":"unknown"}')),
        ));

        $response = $waffo->order()->inquiry(['acquiringOrderId' => 'A1']);

        self::assertSame('E0001', $response->code);
        self::assertSame('unknown', $response->message);
    }

    private static function openApiPath(string $operationId): string
    {
        $raw = file_get_contents(TestAsset::path(__DIR__, '../../../../openapi.json', '../../openapi.json'));
        self::assertNotFalse($raw);
        $openApi = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        foreach ($openApi['paths'] as $path => $methods) {
            if (($methods['post']['operationId'] ?? null) === $operationId) {
                return (string) preg_replace('#^/api/v1#', '', $path);
            }
        }

        self::fail("OpenAPI operation not found: {$operationId}");
    }
}
