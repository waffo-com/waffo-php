<?php

declare(strict_types=1);

namespace Waffo\Util;

use JsonException;

final class Json
{
    /** @throws JsonException */
    public static function encode(mixed $value): string
    {
        return json_encode(
            $value,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /** @return array<string, mixed> */
    public static function decodeObject(string $json): array
    {
        if (!str_starts_with(ltrim($json), '{')) {
            throw new JsonException('JSON value must be an object');
        }

        $value = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($value)) {
            throw new JsonException('JSON value must be an object');
        }

        return $value;
    }

    private function __construct()
    {
    }
}
