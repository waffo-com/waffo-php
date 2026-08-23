<?php

declare(strict_types=1);

namespace Waffo\Tests\Support;

final class FixtureKeys
{
    /** @return array{privateKey: string, publicKey: string} */
    public static function load(): array
    {
        $raw = file_get_contents(TestAsset::path(__DIR__, '../../../../sdk-spec/test-vectors/rsa-signing.json', '../../sdk-spec/test-vectors/rsa-signing.json'));
        if ($raw === false) {
            throw new \RuntimeException('Unable to load RSA vector');
        }
        $vector = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        return [
            'privateKey' => (string) $vector['testKeys']['privateKey'],
            'publicKey' => (string) $vector['testKeys']['publicKey'],
        ];
    }

    private function __construct()
    {
    }
}
