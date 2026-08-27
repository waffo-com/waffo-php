<?php

declare(strict_types=1);

namespace Waffo\Util;

final class AmountValidationResult
{
    private function __construct(
        public bool $valid,
        public ?int $precision = null,
        public ?string $errorCode = null,
        public ?string $message = null,
        public ?string $normalizedAmount = null,
    ) {
    }

    public static function valid(int $precision, ?string $normalizedAmount = null): self
    {
        return new self(true, $precision, null, null, $normalizedAmount);
    }

    public static function invalid(string $errorCode, string $message): self
    {
        return new self(false, null, $errorCode, $message);
    }
}
