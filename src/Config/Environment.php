<?php

declare(strict_types=1);

namespace Waffo\Config;

use JsonSerializable;
use ValueError;

/**
 * @property-read string $name
 * @property-read string $value
 */
final class Environment implements JsonSerializable
{
    public const Sandbox = 'sandbox';
    public const Production = 'production';

    private function __construct(private string $caseName, private string $caseValue)
    {
    }

    /** @return non-empty-string */
    public function baseUrl(): string
    {
        return match ($this->caseValue) {
            self::Sandbox => 'https://api-sandbox.waffo.com/api/v1',
            self::Production => 'https://api.waffo.com/api/v1',
            default => throw new \LogicException("Unsupported Waffo environment: {$this->caseValue}"),
        };
    }

    public static function parse(string $value): self
    {
        return match (strtolower(trim($value))) {
            self::Sandbox => self::from(self::Sandbox),
            self::Production, 'prod' => self::from(self::Production),
            default => throw new \InvalidArgumentException("Unsupported Waffo environment: {$value}"),
        };
    }

    public static function from(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new ValueError("{$value} is not a valid backing value for Waffo\\Config\\Environment");
    }

    public static function tryFrom(string $value): ?self
    {
        return match ($value) {
            self::Sandbox => self::case('Sandbox', self::Sandbox),
            self::Production => self::case('Production', self::Production),
            default => null,
        };
    }

    /** @return list<self> */
    public static function cases(): array
    {
        return [self::from(self::Sandbox), self::from(self::Production)];
    }

    public function __get(string $property): mixed
    {
        return match ($property) {
            'name' => $this->caseName,
            'value' => $this->caseValue,
            default => throw new \LogicException("Undefined Environment property: {$property}"),
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
