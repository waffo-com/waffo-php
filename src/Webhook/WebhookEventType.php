<?php

declare(strict_types=1);

namespace Waffo\Webhook;

use JsonSerializable;
use ValueError;

/**
 * @property-read string $name
 * @property-read string $value
 */
final class WebhookEventType implements JsonSerializable
{
    public const Payment = 'PAYMENT_NOTIFICATION';
    public const Refund = 'REFUND_NOTIFICATION';
    public const SubscriptionStatus = 'SUBSCRIPTION_STATUS_NOTIFICATION';
    public const SubscriptionPeriodChanged = 'SUBSCRIPTION_PERIOD_CHANGED_NOTIFICATION';
    public const SubscriptionChange = 'SUBSCRIPTION_CHANGE_NOTIFICATION';

    private function __construct(private string $caseName, private string $caseValue)
    {
    }

    public static function from(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new ValueError("{$value} is not a valid backing value for Waffo\\Webhook\\WebhookEventType");
    }

    public static function tryFrom(string $value): ?self
    {
        return match ($value) {
            self::Payment => self::case('Payment', self::Payment),
            self::Refund => self::case('Refund', self::Refund),
            self::SubscriptionStatus => self::case('SubscriptionStatus', self::SubscriptionStatus),
            self::SubscriptionPeriodChanged => self::case('SubscriptionPeriodChanged', self::SubscriptionPeriodChanged),
            self::SubscriptionChange => self::case('SubscriptionChange', self::SubscriptionChange),
            default => null,
        };
    }

    /** @return list<self> */
    public static function cases(): array
    {
        return [
            self::from(self::Payment),
            self::from(self::Refund),
            self::from(self::SubscriptionStatus),
            self::from(self::SubscriptionPeriodChanged),
            self::from(self::SubscriptionChange),
        ];
    }

    public function __get(string $property): mixed
    {
        return match ($property) {
            'name' => $this->caseName,
            'value' => $this->caseValue,
            default => throw new \LogicException("Undefined WebhookEventType property: {$property}"),
        };
    }

    public function __isset(string $property): bool
    {
        return $property === 'name' || $property === 'value';
    }

    public function jsonSerialize(): string
    {
        return $this->caseValue;
    }

    private static function case(string $name, string $value): self
    {
        /** @var array<string, self> $cases */
        static $cases = [];

        return $cases[$value] ??= new self($name, $value);
    }
}
