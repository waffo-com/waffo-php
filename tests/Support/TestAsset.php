<?php

declare(strict_types=1);

namespace Waffo\Tests\Support;

final class TestAsset
{
    public static function path(string $directory, string $monorepoRelative, string $publicRepoRelative): string
    {
        foreach ([$monorepoRelative, $publicRepoRelative] as $relative) {
            $candidate = $directory . '/' . $relative;
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException("Unable to locate test asset: {$monorepoRelative}");
    }

    private function __construct()
    {
    }
}
