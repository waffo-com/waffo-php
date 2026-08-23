<?php

declare(strict_types=1);

namespace Waffo\Tests\Vector;

use PHPUnit\Framework\TestCase;
use Waffo\Config\WaffoConfig;
use Waffo\Crypto\Rsa;
use Waffo\Tests\Support\FixtureKeys;
use Waffo\Tests\Support\TestAsset;
use Waffo\Util\Json;
use Waffo\Webhook\WebhookEventType;
use Waffo\Webhook\WebhookHandler;

final class WebhookVerifyVectorTest extends TestCase
{
    public function testAllWebhookEventsVerifyDispatchAndReturnSignedResponse(): void
    {
        $raw = file_get_contents(TestAsset::path(__DIR__, '../../../../sdk-spec/test-vectors/webhook-verify.json', '../../sdk-spec/test-vectors/webhook-verify.json'));
        self::assertNotFalse($raw);
        $vector = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $keys = FixtureKeys::load();
        $received = [];
        $handler = (new WebhookHandler(new WaffoConfig('api-key', $keys['privateKey'], $keys['publicKey'])))
            ->onPayment(static function (array $value) use (&$received): void {
                $received[] = $value;
            })
            ->onRefund(static function (array $value) use (&$received): void {
                $received[] = $value;
            })
            ->onSubscriptionStatus(static function (array $value) use (&$received): void {
                $received[] = $value;
            })
            ->onSubscriptionPeriodChanged(static function (array $value) use (&$received): void {
                $received[] = $value;
            })
            ->onSubscriptionChange(static function (array $value) use (&$received): void {
                $received[] = $value;
            });

        self::assertSame(array_column($vector['eventTypes'], 'type'), array_column(WebhookEventType::cases(), 'value'));
        foreach ($vector['unitTests'] as $case) {
            $body = Json::encode([
                'eventType' => $case['expected']['eventType'],
                'result' => $case['input']['payload'],
            ]);
            $result = $handler->handleWebhook($body, Rsa::sign($body, $keys['privateKey']));
            self::assertTrue($result->success, $case['id']);
            self::assertSame($vector['responseSignature']['format']['body'], $result->responseBody, $case['id']);
            self::assertTrue(Rsa::verify($result->responseBody, $result->responseSignature, $keys['publicKey']));
        }
        self::assertCount(count($vector['unitTests']), $received);

        $invalid = $handler->handleWebhook('{"eventType":"PAYMENT_NOTIFICATION"}', 'invalid');
        self::assertFalse($invalid->success);
        $missing = $handler->handleWebhook('{"eventType":"PAYMENT_NOTIFICATION"}', null);
        self::assertFalse($missing->success);
        $malformed = '{invalid json';
        $malformedResult = $handler->handleWebhook($malformed, Rsa::sign($malformed, $keys['privateKey']));
        self::assertFalse($malformedResult->success);
    }

    public function testSubscriptionStatusFallsBackToSubscriptionPaymentHandler(): void
    {
        $raw = file_get_contents(TestAsset::path(__DIR__, '../../../../sdk-spec/test-vectors/webhook-verify.json', '../../sdk-spec/test-vectors/webhook-verify.json'));
        self::assertNotFalse($raw);
        $keys = FixtureKeys::load();
        $called = false;
        $handler = (new WebhookHandler(new WaffoConfig('api-key', $keys['privateKey'], $keys['publicKey'])))
            ->onSubscriptionPayment(static function () use (&$called): void {
                $called = true;
            });
        $body = '{"eventType":"SUBSCRIPTION_STATUS_NOTIFICATION","result":{"subscriptionStatus":"ACTIVE"}}';

        $result = $handler->handleWebhook($body, Rsa::sign($body, $keys['privateKey']));

        self::assertTrue($result->success);
        self::assertTrue($called);
    }
}
