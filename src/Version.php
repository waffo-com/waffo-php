<?php

declare(strict_types=1);

namespace Waffo;

final class Version
{
    public const VERSION = '0.3.0';
    public const SDK_HEADER = 'waffo-php/' . self::VERSION;

    private function __construct()
    {
    }
}
