<?php

declare(strict_types=1);

namespace Waffo\Tests\Vector;

use PHPUnit\Framework\TestCase;
use Waffo\Response\ApiResponse;
use Waffo\Tests\Support\TestAsset;

final class ApiResponseVectorTest extends TestCase
{
    public function testAllApiResponseCases(): void
    {
        $raw = file_get_contents(TestAsset::path(__DIR__, '../../../../sdk-spec/test-vectors/api-response.json', '../../sdk-spec/test-vectors/api-response.json'));
        self::assertNotFalse($raw);
        $vector = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        foreach ($vector['unitTests'] as $case) {
            if (isset($case['input']['orderAction'])) {
                $action = json_decode($case['input']['orderAction'], true, 512, JSON_THROW_ON_ERROR);
                self::assertSame($case['expected']['redirectUrl'], $action['webUrl'], $case['id']);
                continue;
            }
            $response = ApiResponse::fromJson($case['input']['json']);
            self::assertResponseExpectation($case['expected'], $response, $case['id']);
        }

        foreach ($vector['errorCodeTests'] as $case) {
            $response = ApiResponse::fromJson($case['input']['json']);
            self::assertFalse($response->isSuccess(), $case['id']);
            self::assertSame($case['expected']['errorCategory'], $response->errorCategory(), $case['id']);
        }

        foreach ($vector['edgeCases'] as $case) {
            if (!isset($case['input']['json'])) {
                continue;
            }
            $response = ApiResponse::fromJson($case['input']['json']);
            self::assertResponseExpectation($case['expected'], $response, $case['id']);
        }
    }

    /** @param array<string, mixed> $expected */
    private static function assertResponseExpectation(array $expected, ApiResponse $response, string $id): void
    {
        if (isset($expected['isSuccess'])) {
            self::assertSame($expected['isSuccess'], $response->isSuccess(), $id);
        }
        if (array_key_exists('code', $expected)) {
            self::assertSame($expected['code'], $response->code, $id);
        }
        if (array_key_exists('message', $expected)) {
            self::assertSame($expected['message'], $response->message, $id);
        }
        if (array_key_exists('data', $expected)) {
            if (is_array($expected['data']) && is_array($response->data)) {
                foreach ($expected['data'] as $key => $value) {
                    self::assertSame($value, $response->data[$key], "{$id}:{$key}");
                }
            } else {
                self::assertSame($expected['data'], $response->data, $id);
            }
        }
    }
}
