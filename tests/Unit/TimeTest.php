<?php

declare(strict_types=1);

namespace Waffo\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Waffo\Util\Time;

final class TimeTest extends TestCase
{
    public function testFormatsUtcWithExactlyThreeMilliseconds(): void
    {
        $date = new DateTimeImmutable('2026-08-22T09:10:11.987654+08:00');

        self::assertSame('2026-08-22T01:10:11.987Z', Time::now($date));
    }
}
