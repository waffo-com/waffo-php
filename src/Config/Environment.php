<?php

declare(strict_types=1);

namespace Waffo\Config;

enum Environment: string
{
    case Sandbox = 'sandbox';
    case Production = 'production';

    /** @return non-empty-string */
    public function baseUrl(): string
    {
        return match ($this) {
            self::Sandbox => 'https://api-sandbox.waffo.com/api/v1',
            self::Production => 'https://api.waffo.com/api/v1',
        };
    }

    public static function parse(string $value): self
    {
        return match (strtolower(trim($value))) {
            'sandbox' => self::Sandbox,
            'production', 'prod' => self::Production,
            default => throw new \InvalidArgumentException("Unsupported Waffo environment: {$value}"),
        };
    }
}
