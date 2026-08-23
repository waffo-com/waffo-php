<?php

declare(strict_types=1);

namespace Waffo\Tests\Vector;

use PHPUnit\Framework\TestCase;
use Waffo\Model\Generated\ModelRegistry;
use Waffo\Tests\Support\TestAsset;

final class SchemaFieldsVectorTest extends TestCase
{
    public function testAllRegisteredSchemaFieldsAndRequiredFlags(): void
    {
        $raw = file_get_contents(TestAsset::path(__DIR__, '../../../../sdk-spec/test-vectors/schema-fields.json', '../../sdk-spec/test-vectors/schema-fields.json'));
        self::assertNotFalse($raw);
        $vector = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        foreach ($vector['schemas'] as $schema) {
            $schemaName = $schema['openapiSchema'];
            $class = ModelRegistry::MAP[$schemaName] ?? null;
            self::assertNotNull($class, $schemaName);
            self::assertSame($schema['fields'], array_keys($class::SCHEMA), $schemaName);
            $required = [];
            foreach ($class::SCHEMA as $field => $details) {
                if ($details['required']) {
                    $required[] = $field;
                }
            }
            sort($required);
            $expectedRequired = $schema['required'];
            sort($expectedRequired);
            self::assertSame($expectedRequired, $required, $schemaName);
            self::assertSame(
                'Waffo\\Model\\Generated\\' . str_replace('.', '_', $schemaName),
                $schema['sdkTypes']['php'],
                $schemaName,
            );
        }
    }
}
