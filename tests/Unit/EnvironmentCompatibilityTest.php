<?php

declare(strict_types=1);

namespace Waffo\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ValueError;
use Waffo\Config\Environment;
use Waffo\Config\WaffoConfig;
use Waffo\Tests\Support\FixtureKeys;

final class EnvironmentCompatibilityTest extends TestCase
{
    public function testDocumentedEnvironmentConstantCreatesAUsableConfig(): void
    {
        $keys = FixtureKeys::load();
        $config = new WaffoConfig(
            apiKey: 'api-key',
            privateKey: $keys['privateKey'],
            waffoPublicKey: $keys['publicKey'],
            environment: Environment::Sandbox,
        );

        self::assertSame('Sandbox', $config->environment->name);
        self::assertSame('sandbox', $config->environment->value);
        self::assertSame('https://api-sandbox.waffo.com/api/v1', $config->environment->baseUrl());
    }

    public function testEnvironmentKeepsValueReadAndParseBehavior(): void
    {
        self::assertSame(Environment::from('sandbox'), Environment::parse('sandbox'));
        self::assertSame('production', Environment::parse('prod')->value);
        self::assertSame('production', Environment::from('production')->value);
        self::assertSame('sandbox', Environment::tryFrom('sandbox')?->value);
        self::assertSame('"sandbox"', json_encode(Environment::from('sandbox'), JSON_THROW_ON_ERROR));
        self::assertNull(Environment::tryFrom('invalid'));
        self::assertSame(
            ['sandbox', 'production'],
            array_map(static fn (Environment $environment): string => $environment->value, Environment::cases()),
        );
    }

    public function testEnvironmentFromRejectsUnknownValues(): void
    {
        $this->expectException(ValueError::class);

        Environment::from('invalid');
    }
}
