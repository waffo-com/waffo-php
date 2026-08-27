<?php

declare(strict_types=1);

namespace Waffo\Webhook;

use Closure;
use JsonException;
use Throwable;
use Waffo\Config\WaffoConfig;
use Waffo\Crypto\Rsa;
use Waffo\Util\Json;

final class WebhookHandler
{
    public const SUCCESS_RESPONSE_BODY = '{"message":"success"}';
    public const FAILED_RESPONSE_BODY = '{"message":"failed"}';
    public const EVENT_TYPES = [
        'PAYMENT_NOTIFICATION',
        'REFUND_NOTIFICATION',
        'SUBSCRIPTION_STATUS_NOTIFICATION',
        'SUBSCRIPTION_PERIOD_CHANGED_NOTIFICATION',
        'SUBSCRIPTION_CHANGE_NOTIFICATION',
    ];

    /** @var array<string, Closure(array<string, mixed>): mixed> */
    private array $handlers = [];

    /** @var null|Closure(array<string, mixed>): mixed */
    private ?Closure $subscriptionPaymentFallback = null;

    public function __construct(private WaffoConfig $config)
    {
    }

    public function onPayment(callable $handler): self
    {
        return $this->register(WebhookEventType::from(WebhookEventType::Payment), $handler);
    }

    public function onRefund(callable $handler): self
    {
        return $this->register(WebhookEventType::from(WebhookEventType::Refund), $handler);
    }

    public function onSubscriptionStatus(callable $handler): self
    {
        return $this->register(WebhookEventType::from(WebhookEventType::SubscriptionStatus), $handler);
    }

    public function onSubscriptionPayment(callable $handler): self
    {
        $this->subscriptionPaymentFallback = Closure::fromCallable($handler);

        return $this;
    }

    public function onSubscriptionPeriodChanged(callable $handler): self
    {
        return $this->register(WebhookEventType::from(WebhookEventType::SubscriptionPeriodChanged), $handler);
    }

    public function onSubscriptionChange(callable $handler): self
    {
        return $this->register(WebhookEventType::from(WebhookEventType::SubscriptionChange), $handler);
    }

    public function verifySignature(string $body, string $signature): bool
    {
        return Rsa::verify($body, $signature, $this->config->waffoPublicKey);
    }

    public function handle(string $body, ?string $signature): WebhookResult
    {
        return $this->handleWebhook($body, $signature);
    }

    public function handleWebhook(string $body, ?string $signature): WebhookResult
    {
        try {
            if ($signature === null || $signature === '') {
                return $this->failed('Missing signature');
            }
            if (!$this->verifySignature($body, $signature)) {
                return $this->failed('Invalid signature');
            }

            $notification = Json::decodeObject($body);
            $eventTypeValue = $notification['eventType'] ?? null;
            if (!is_string($eventTypeValue)) {
                return $this->failed('Unknown event type');
            }
            if (!in_array($eventTypeValue, self::EVENT_TYPES, true)) {
                return $this->failed("Unknown event type: {$eventTypeValue}");
            }

            $eventType = WebhookEventType::tryFrom($eventTypeValue);
            if ($eventType === null) {
                return $this->failed("Unknown event type: {$eventTypeValue}");
            }

            $handler = $this->handlers[$eventType->value] ?? null;
            if ($handler === null && $eventType->value === WebhookEventType::SubscriptionStatus) {
                $handler = $this->subscriptionPaymentFallback;
            }
            if ($handler !== null) {
                $handler($notification);
            } else {
                $this->config->logger?->warning('No Waffo webhook handler registered', ['eventType' => $eventTypeValue]);
            }

            return $this->succeeded();
        } catch (JsonException) {
            return $this->failed('Failed to parse webhook body');
        } catch (Throwable $error) {
            $this->config->logger?->error('Waffo webhook handler failed', ['exception' => $error::class]);

            return $this->failed('Error handling webhook: ' . $error->getMessage());
        }
    }

    public function buildSuccessResponse(): SignedWebhookResponse
    {
        return $this->signedResponse(self::SUCCESS_RESPONSE_BODY);
    }

    public function buildFailedResponse(): SignedWebhookResponse
    {
        return $this->signedResponse(self::FAILED_RESPONSE_BODY);
    }

    private function register(WebhookEventType $eventType, callable $handler): self
    {
        $this->handlers[$eventType->value] = Closure::fromCallable($handler);

        return $this;
    }

    private function signedResponse(string $body): SignedWebhookResponse
    {
        return new SignedWebhookResponse($body, Rsa::sign($body, $this->config->privateKey));
    }

    private function succeeded(): WebhookResult
    {
        $response = $this->buildSuccessResponse();

        return new WebhookResult(true, $response->body, $response->signature);
    }

    private function failed(string $error): WebhookResult
    {
        $response = $this->buildFailedResponse();

        return new WebhookResult(false, $response->body, $response->signature, $error);
    }
}
