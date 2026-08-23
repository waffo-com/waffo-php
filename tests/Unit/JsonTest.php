<?php

declare(strict_types=1);

namespace Waffo\Tests\Unit;

use JsonException;
use PHPUnit\Framework\TestCase;
use Waffo\Util\Json;

final class JsonTest extends TestCase
{
    public function testDecodesEmptyObject(): void
    {
        self::assertSame([], Json::decodeObject('{}'));
    }

    public function testRejectsEmptyList(): void
    {
        $this->expectException(JsonException::class);
        $this->expectExceptionMessage('JSON value must be an object');

        Json::decodeObject('[]');
    }
}
