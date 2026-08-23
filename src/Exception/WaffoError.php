<?php

declare(strict_types=1);

namespace Waffo\Exception;

use RuntimeException;
use Throwable;

class WaffoError extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly ?Throwable $errorCause = null,
    ) {
        parent::__construct($message, 0, $errorCause);
    }
}
