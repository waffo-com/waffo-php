<?php

declare(strict_types=1);

namespace Waffo\Tests\Vector;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Waffo\Resource\SubscriptionResource;
use Waffo\Tests\Support\TestAsset;

final class SubscriptionE2ECasesVectorTest extends TestCase
{
    public function testAllSubscriptionE2ECasesAreExecutableByThePhpSurface(): void
    {
        $raw = file_get_contents(TestAsset::path(__DIR__, '../../../../sdk-spec/test-vectors/subscription-e2e-cases.json', '../../sdk-spec/test-vectors/subscription-e2e-cases.json'));
        self::assertNotFalse($raw);
        $vector = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(7, $vector['e2eTests']);

        $caseIds = [];
        foreach ($vector['e2eTests'] as $case) {
            $caseIds[] = $case['id'];
            self::assertNotEmpty($case['flow'], $case['id']);
            self::assertNotEmpty($case['assertions'], $case['id']);
            foreach ($case['assertions'] as $assertion) {
                self::assertArrayHasKey('path', $assertion, $case['id']);
            }
        }
        self::assertSame(
            ['E2E-SU01', 'E2E-SU02', 'E2E-SU03', 'E2E-SU04', 'E2E-SU05', 'E2E-SU06', 'E2E-SU07'],
            $caseIds,
        );
        foreach (['create', 'inquiry', 'manage', 'update'] as $method) {
            self::assertTrue((new ReflectionMethod(SubscriptionResource::class, $method))->isPublic());
        }
    }
}
