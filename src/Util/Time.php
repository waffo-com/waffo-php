<?php

declare(strict_types=1);

namespace Waffo\Util;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

final class Time
{
    public static function now(?DateTimeInterface $dateTime = null): string
    {
        $value = $dateTime === null
            ? new DateTimeImmutable('now', new DateTimeZone('UTC'))
            : DateTimeImmutable::createFromInterface($dateTime)->setTimezone(new DateTimeZone('UTC'));

        $milliseconds = substr($value->format('u'), 0, 3);

        return $value->format('Y-m-d\TH:i:s.') . $milliseconds . 'Z';
    }

    private function __construct()
    {
    }
}
