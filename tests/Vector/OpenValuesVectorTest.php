<?php

declare(strict_types=1);

namespace Waffo\Tests\Vector;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Waffo\Config\WaffoConfig;
use Waffo\Crypto\Rsa;
use Waffo\Exception\WaffoError;
use Waffo\Model\Generated\PaymentDetail;
use Waffo\Model\Generated\ProductInfo;
use Waffo\Tests\Support\FixtureKeys;
use Waffo\Tests\Support\TestAsset;
use Waffo\Util\Json;
use Waffo\Webhook\WebhookHandler;
use Waffo\Webhook\WebhookResult;

final class OpenValuesVectorTest extends TestCase
{
    private const REGISTRATION_ERROR_CODE = 'S0006';

    /** @return array<mixed> */
    private function vector(): array
    {
        $raw = file_get_contents(TestAsset::path(
            __DIR__,
            '../../../../sdk-spec/test-vectors/open-values.json',
            '../../sdk-spec/test-vectors/open-values.json',
        ));
        if ($raw === false) {
            throw new RuntimeException('Unable to read open-values.json');
        }

        $vector = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($vector)) {
            throw new RuntimeException('open-values.json must decode to a JSON object');
        }
        self::assertSame('open-values', $vector['module'] ?? null);

        return $vector;
    }

    /** @param array{privateKey: string, publicKey: string} $keys */
    private function createHandler(array $keys): WebhookHandler
    {
        return new WebhookHandler(new WaffoConfig('api-key', $keys['privateKey'], $keys['publicKey']));
    }

    /** @param array<mixed> $values */
    private function stringField(array $values, string $key): string
    {
        $value = $values[$key] ?? null;
        if (!is_string($value)) {
            throw new RuntimeException('open-values.json field ' . $key . ' must be a string');
        }

        return $value;
    }

    /** @param array<mixed> $event */
    private function knownEventBody(array $event): string
    {
        return Json::encode([
            'eventType' => $event['eventType'],
            'result' => $event['result'],
        ]);
    }

    /**
     * @param array<mixed> $custom
     * @return array<string, mixed>
     */
    private function customPayload(array $custom): array
    {
        return [
            'eventType' => $custom['eventType'],
            'result' => $custom['result'],
            'futureField' => $custom['futureField'],
        ];
    }

    /** @param array{privateKey: string, publicKey: string} $keys */
    private function deliver(WebhookHandler $handler, string $body, array $keys): WebhookResult
    {
        return $handler->handleWebhook($body, Rsa::sign($body, $keys['privateKey']));
    }

    /** @param array{privateKey: string, publicKey: string} $keys */
    private function assertSignedSuccess(WebhookResult $result, array $keys, string $context): void
    {
        self::assertTrue($result->success, $context . ': ' . ($result->error ?? ''));
        self::assertSame('{"message":"success"}', $result->responseBody, $context);
        self::assertTrue(
            Rsa::verify($result->responseBody, $result->responseSignature, $keys['publicKey']),
            $context,
        );
    }

    /** @param array{privateKey: string, publicKey: string} $keys */
    private function assertSignedFailure(WebhookResult $result, array $keys, string $context): void
    {
        self::assertFalse($result->success, $context);
        self::assertSame('{"message":"failed"}', $result->responseBody, $context);
        self::assertTrue(
            Rsa::verify($result->responseBody, $result->responseSignature, $keys['publicKey']),
            $context,
        );
    }

    private function assertRegistrationRejected(
        WebhookHandler $handler,
        string $eventType,
        callable $callback,
        string $context,
    ): void {
        try {
            $handler->onEvent($eventType, $callback);
        } catch (WaffoError $error) {
            self::assertSame(self::REGISTRATION_ERROR_CODE, $error->errorCode, $context);

            return;
        }

        self::fail($context . ': expected WaffoError ' . self::REGISTRATION_ERROR_CODE);
    }

    public function testKnownEventsRouteAndPreserveBusinessValues(): void
    {
        $vector = $this->vector();
        $keys = FixtureKeys::load();
        $fieldMap = [
            'PAYMENT_NOTIFICATION' => ['acquiringOrderId', 'orderStatus'],
            'REFUND_NOTIFICATION' => ['acquiringRefundOrderId', 'refundStatus'],
            'SUBSCRIPTION_STATUS_NOTIFICATION' => ['subscriptionId', 'subscriptionStatus'],
            'SUBSCRIPTION_PERIOD_CHANGED_NOTIFICATION' => ['subscriptionId', 'subscriptionStatus'],
            'SUBSCRIPTION_CHANGE_NOTIFICATION' => ['subscriptionId', 'subscriptionChangeStatus'],
        ];
        self::assertEqualsCanonicalizing(
            array_keys($fieldMap),
            array_column($vector['knownEvents'], 'eventType'),
        );

        foreach ($vector['knownEvents'] as $event) {
            $eventType = $this->stringField($event, 'eventType');
            $expected = $event['result'];
            $received = [];
            $recorder = static function (array $notification) use (&$received): void {
                $received[] = $notification;
            };
            $handler = $this->createHandler($keys);
            switch ($eventType) {
                case 'PAYMENT_NOTIFICATION':
                    $handler->onPayment($recorder);
                    break;
                case 'REFUND_NOTIFICATION':
                    $handler->onRefund($recorder);
                    break;
                case 'SUBSCRIPTION_STATUS_NOTIFICATION':
                    $handler->onSubscriptionStatus($recorder);
                    break;
                case 'SUBSCRIPTION_PERIOD_CHANGED_NOTIFICATION':
                    $handler->onSubscriptionPeriodChanged($recorder);
                    break;
                case 'SUBSCRIPTION_CHANGE_NOTIFICATION':
                    $handler->onSubscriptionChange($recorder);
                    break;
                default:
                    self::fail('unexpected known event in vector: ' . $eventType);
            }
            $body = $this->knownEventBody($event);

            $result = $this->deliver($handler, $body, $keys);

            $this->assertSignedSuccess($result, $keys, $eventType);
            self::assertCount(1, $received, $eventType);
            self::assertSame(json_decode($body, true), $received[0], $eventType);
            [$idField, $statusField] = $fieldMap[$eventType];
            self::assertSame($expected[$idField], $received[0]['result'][$idField], $eventType);
            self::assertSame($expected[$statusField], $received[0]['result'][$statusField], $eventType);
            if (array_key_exists('paymentDetails', $expected)) {
                self::assertSame(
                    $expected['paymentDetails'][0]['orderStatus'],
                    $received[0]['result']['paymentDetails'][0]['orderStatus'],
                    $eventType,
                );
            }
            if ($eventType === 'SUBSCRIPTION_STATUS_NOTIFICATION') {
                self::assertSame('ACTIVE', $expected[$statusField], $eventType);
                self::assertSame('INIT', $expected['paymentDetails'][0]['orderStatus'], $eventType);
            }
        }
    }

    public function testKnownEventsWithoutHandlerStillSucceed(): void
    {
        $vector = $this->vector();
        $keys = FixtureKeys::load();

        foreach ($vector['knownEvents'] as $event) {
            $body = $this->knownEventBody($event);
            $result = $this->deliver($this->createHandler($keys), $body, $keys);

            $this->assertSignedSuccess($result, $keys, $this->stringField($event, 'eventType'));
        }
    }

    public function testSubscriptionStatusPriorityAndPaymentFallback(): void
    {
        $vector = $this->vector();
        $keys = FixtureKeys::load();
        $statusEvent = null;
        foreach ($vector['knownEvents'] as $event) {
            if (($event['eventType'] ?? null) === 'SUBSCRIPTION_STATUS_NOTIFICATION') {
                $statusEvent = $event;
                break;
            }
        }
        if (!is_array($statusEvent)) {
            throw new RuntimeException('open-values.json is missing SUBSCRIPTION_STATUS_NOTIFICATION');
        }
        $body = $this->knownEventBody($statusEvent);

        $statusCalls = [];
        $paymentCalls = [];
        $handler = $this->createHandler($keys)
            ->onSubscriptionStatus(static function (array $notification) use (&$statusCalls): void {
                $statusCalls[] = $notification;
            })
            ->onSubscriptionPayment(static function (array $notification) use (&$paymentCalls): void {
                $paymentCalls[] = $notification;
            });
        $result = $this->deliver($handler, $body, $keys);

        $this->assertSignedSuccess($result, $keys, 'subscription status priority');
        self::assertCount(1, $statusCalls);
        self::assertSame([], $paymentCalls);

        $fallbackCalls = [];
        $fallbackHandler = $this->createHandler($keys)
            ->onSubscriptionPayment(static function (array $notification) use (&$fallbackCalls): void {
                $fallbackCalls[] = $notification;
            });
        $fallbackResult = $this->deliver($fallbackHandler, $body, $keys);

        $this->assertSignedSuccess($fallbackResult, $keys, 'subscription payment fallback');
        self::assertCount(1, $fallbackCalls);
    }

    public function testCustomEventRequiresExactRegisteredName(): void
    {
        $vector = $this->vector();
        $keys = FixtureKeys::load();
        $custom = $vector['customEvent'];
        if (!is_array($custom)) {
            throw new RuntimeException('open-values.json customEvent must be an object');
        }
        $eventType = $this->stringField($custom, 'eventType');
        $body = Json::encode($this->customPayload($custom));

        $sentinelCalls = [];
        $sentinelHandler = $this->createHandler($keys)->onEvent(
            $eventType . '_OTHER',
            static function (array $notification) use (&$sentinelCalls): void {
                $sentinelCalls[] = $notification;
            },
        );
        $unregisteredResult = $this->deliver($sentinelHandler, $body, $keys);

        $this->assertSignedFailure($unregisteredResult, $keys, 'unregistered custom event');
        self::assertSame([], $sentinelCalls);

        $registeredCalls = [];
        $handler = $this->createHandler($keys)->onEvent(
            $eventType,
            static function (array $notification) use (&$registeredCalls): void {
                $registeredCalls[] = $notification;
            },
        );
        $lowerName = strtolower($eventType);
        self::assertNotSame($eventType, $lowerName);
        $mismatchedBody = Json::encode([
            'eventType' => $lowerName,
            'result' => $custom['result'],
            'futureField' => $custom['futureField'],
        ]);
        $mismatchedResult = $this->deliver($handler, $mismatchedBody, $keys);

        $this->assertSignedFailure($mismatchedResult, $keys, 'custom event name casing');
        self::assertSame([], $registeredCalls);

        $exactResult = $this->deliver($handler, $body, $keys);
        $this->assertSignedSuccess($exactResult, $keys, 'exact custom event name');
        self::assertSame([json_decode($body, true)], $registeredCalls);
    }

    public function testRegisteredCustomEventReceivesFullPayload(): void
    {
        $vector = $this->vector();
        $keys = FixtureKeys::load();
        $custom = $vector['customEvent'];
        if (!is_array($custom)) {
            throw new RuntimeException('open-values.json customEvent must be an object');
        }
        $eventType = $this->stringField($custom, 'eventType');
        $body = Json::encode($this->customPayload($custom));
        $received = [];

        $handler = $this->createHandler($keys);
        self::assertSame($handler, $handler->onEvent(
            $eventType,
            static function (array $notification) use (&$received): void {
                $received[] = $notification;
            },
        ));
        $result = $this->deliver($handler, $body, $keys);

        $this->assertSignedSuccess($result, $keys, 'registered custom event');
        self::assertSame([json_decode($body, true)], $received);
        self::assertSame($custom['eventType'], $received[0]['eventType']);
        self::assertSame($custom['result'], $received[0]['result']);
        self::assertSame($custom['futureField'], $received[0]['futureField']);
    }

    public function testDuplicateCustomRegistrationReplacesPrevious(): void
    {
        $vector = $this->vector();
        $keys = FixtureKeys::load();
        $custom = $vector['customEvent'];
        if (!is_array($custom)) {
            throw new RuntimeException('open-values.json customEvent must be an object');
        }
        $eventType = $this->stringField($custom, 'eventType');
        $body = Json::encode($this->customPayload($custom));
        $firstCalls = [];
        $secondCalls = [];
        $handler = $this->createHandler($keys);
        $handler->onEvent($eventType, static function (array $notification) use (&$firstCalls): void {
            $firstCalls[] = $notification;
        });
        $handler->onEvent($eventType, static function (array $notification) use (&$secondCalls): void {
            $secondCalls[] = $notification;
        });

        $result = $this->deliver($handler, $body, $keys);

        $this->assertSignedSuccess($result, $keys, 'duplicate custom registration');
        self::assertSame([], $firstCalls);
        self::assertSame([json_decode($body, true)], $secondCalls);
    }

    public function testCustomRegistrationsAreInstanceScoped(): void
    {
        $vector = $this->vector();
        $keys = FixtureKeys::load();
        $custom = $vector['customEvent'];
        if (!is_array($custom)) {
            throw new RuntimeException('open-values.json customEvent must be an object');
        }
        $eventType = $this->stringField($custom, 'eventType');
        $body = Json::encode($this->customPayload($custom));
        $registeredCalls = [];

        $registeredHandler = $this->createHandler($keys)->onEvent(
            $eventType,
            static function (array $notification) use (&$registeredCalls): void {
                $registeredCalls[] = $notification;
            },
        );
        $idleHandler = $this->createHandler($keys);

        $idleResult = $this->deliver($idleHandler, $body, $keys);
        $this->assertSignedFailure($idleResult, $keys, 'custom event on isolated handler');
        self::assertSame([], $registeredCalls);

        $registeredResult = $this->deliver($registeredHandler, $body, $keys);
        $this->assertSignedSuccess($registeredResult, $keys, 'custom event on registered handler');
        self::assertSame([json_decode($body, true)], $registeredCalls);
    }

    public function testSignatureVerificationPrecedesCustomCallback(): void
    {
        $vector = $this->vector();
        $keys = FixtureKeys::load();
        $custom = $vector['customEvent'];
        if (!is_array($custom)) {
            throw new RuntimeException('open-values.json customEvent must be an object');
        }
        $eventType = $this->stringField($custom, 'eventType');
        $body = Json::encode($this->customPayload($custom));
        $received = [];

        $handler = $this->createHandler($keys)->onEvent(
            $eventType,
            static function (array $notification) use (&$received): void {
                $received[] = $notification;
            },
        );
        foreach ([null, '', 'invalid-signature'] as $signature) {
            $result = $handler->handleWebhook($body, $signature);

            $this->assertSignedFailure($result, $keys, 'signature ' . var_export($signature, true));
            self::assertSame([], $received);
        }

        $malformed = '{not-json';
        $malformedResult = $handler->handleWebhook(
            $malformed,
            Rsa::sign($malformed, $keys['privateKey']),
        );

        $this->assertSignedFailure($malformedResult, $keys, 'valid signature with invalid JSON');
        self::assertSame([], $received);
    }

    public function testCustomCallbackExceptionReturnsSignedFailure(): void
    {
        $vector = $this->vector();
        $keys = FixtureKeys::load();
        $custom = $vector['customEvent'];
        if (!is_array($custom)) {
            throw new RuntimeException('open-values.json customEvent must be an object');
        }
        $eventType = $this->stringField($custom, 'eventType');
        $body = Json::encode($this->customPayload($custom));
        $calls = [];

        $handler = $this->createHandler($keys)->onEvent(
            $eventType,
            static function (array $notification) use (&$calls): void {
                $calls[] = $notification;
                throw new RuntimeException('custom handler failed');
            },
        );
        $result = $this->deliver($handler, $body, $keys);

        $this->assertSignedFailure($result, $keys, 'custom callback exception');
        self::assertCount(1, $calls);
    }

    public function testOnEventRejectsInvalidConfiguration(): void
    {
        $vector = $this->vector();
        $keys = FixtureKeys::load();
        $custom = $vector['customEvent'];
        if (!is_array($custom)) {
            throw new RuntimeException('open-values.json customEvent must be an object');
        }
        $eventType = $this->stringField($custom, 'eventType');
        $body = Json::encode($this->customPayload($custom));
        $customCalls = [];
        $recorder = static function (array $notification) use (&$customCalls): void {
            $customCalls[] = $notification;
        };

        $handler = $this->createHandler($keys);
        self::assertSame($handler, $handler->onEvent($eventType, $recorder));

        foreach ($vector['invalidEventNames'] as $invalidName) {
            self::assertIsString($invalidName);
            $this->assertRegistrationRejected(
                $handler,
                $invalidName,
                $recorder,
                'invalid event name ' . var_export($invalidName, true),
            );
        }

        foreach ($vector['knownEvents'] as $event) {
            $builtinName = $this->stringField($event, 'eventType');
            $this->assertRegistrationRejected(
                $handler,
                $builtinName,
                $recorder,
                'builtin event name ' . $builtinName,
            );
        }

        $result = $this->deliver($handler, $body, $keys);
        $this->assertSignedSuccess($result, $keys, 'custom mapping after rejected registrations');
        self::assertSame([json_decode($body, true)], $customCalls);
    }

    public function testInvalidHandlerIsRejectedByCallableContract(): void
    {
        $this->expectException(\TypeError::class);
        $method = new \ReflectionMethod(WebhookHandler::class, 'onEvent');
        $method->invoke($this->createHandler(FixtureKeys::load()), 'EXAMPLE_CUSTOM_NOTIFICATION', null);
    }

    public function testGeneratedModelsPreserveBusinessValues(): void
    {
        $vector = $this->vector();

        foreach ($vector['businessValues'] as $value) {
            if (!is_string($value)) {
                throw new RuntimeException('open-values.json businessValues must be strings');
            }

            $detail = PaymentDetail::fromArray(
                Json::decodeObject(Json::encode(['orderStatus' => $value])),
            );
            self::assertSame($value, $detail->orderStatus);
            self::assertSame(
                $value,
                Json::decodeObject(Json::encode($detail->toArray()))['orderStatus'],
            );

            $product = ProductInfo::fromArray(Json::decodeObject(Json::encode([
                'description' => 'open-values',
                'periodType' => $value,
                'periodInterval' => '1',
                'trialPeriodType' => $value,
            ])));
            self::assertSame($value, $product->periodType);
            self::assertSame($value, $product->trialPeriodType);
            $serialized = Json::decodeObject(Json::encode($product->toArray()));
            self::assertSame($value, $serialized['periodType']);
            self::assertSame($value, $serialized['trialPeriodType']);
        }

        $missing = PaymentDetail::fromArray(Json::decodeObject('{}'));
        self::assertNull($missing->orderStatus);
        self::assertArrayNotHasKey('orderStatus', $missing->toArray());

        $nullStatus = PaymentDetail::fromArray(Json::decodeObject('{"orderStatus":null}'));
        self::assertNull($nullStatus->orderStatus);
        self::assertArrayNotHasKey('orderStatus', $nullStatus->toArray());
    }

    public function testBuiltinFailurePayloadsReachCallbacks(): void
    {
        $vector = $this->vector();
        self::assertCount(4, $vector['failureEvents']);
        $keys = FixtureKeys::load();
        foreach ($vector['failureEvents'] as $event) {
            $received = null;
            $record = static function (array $notification) use (&$received): void {
                $received = $notification;
            };
            $handler = $this->createHandler($keys)->onPayment($record)->onRefund($record)
                ->onSubscriptionStatus($record)->onSubscriptionChange($record);
            $body = Json::encode($event);
            $result = $this->deliver($handler, $body, $keys);
            $this->assertSignedSuccess($result, $keys, $event['eventType']);
            self::assertSame($event, $received);
        }
    }
}
