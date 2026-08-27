<?php

declare(strict_types=1);

namespace Waffo\Config;

final class RequestOptions
{
    public function __construct(
        public ?int $connectTimeoutMs = null,
        public ?int $readTimeoutMs = null,
    ) {
        if ($connectTimeoutMs !== null && $connectTimeoutMs <= 0) {
            throw new \InvalidArgumentException('connectTimeoutMs must be greater than zero');
        }
        if ($readTimeoutMs !== null && $readTimeoutMs <= 0) {
            throw new \InvalidArgumentException('readTimeoutMs must be greater than zero');
        }
    }
}
