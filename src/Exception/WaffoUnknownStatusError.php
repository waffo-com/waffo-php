<?php

declare(strict_types=1);

namespace Waffo\Exception;

use Throwable;

final class WaffoUnknownStatusError extends WaffoError
{
    public const CODE_NETWORK_ERROR = 'S0001';

    public function __construct(string $errorCode, string $message, ?Throwable $errorCause = null)
    {
        parent::__construct($errorCode, $message, $errorCause);
    }
}
