<?php

declare(strict_types=1);

namespace Waffo\Tests\Vector;

use PHPUnit\Framework\TestCase;
use Waffo\Tests\Support\TestAsset;
use Waffo\Util\AmountValidator;

final class AmountValidatorVectorTest extends TestCase
{
    public function testAllAmountValidatorCases(): void
    {
        $raw = file_get_contents(TestAsset::path(__DIR__, '../../../../sdk-spec/test-vectors/amount-validator.json', '../../sdk-spec/test-vectors/amount-validator.json'));
        self::assertNotFalse($raw);
        $vector = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $cases = array_merge(
            $vector['unitTests'],
            $vector['currencyPrecisionTests'],
            $vector['edgeCases'],
        );

        foreach ($cases as $case) {
            $result = AmountValidator::validate($case['input']['amount'], $case['input']['currency']);
            $expected = $case['expected'];
            self::assertSame($expected['valid'], $result->valid, $case['id']);
            if (isset($expected['precision'])) {
                self::assertSame($expected['precision'], $result->precision, $case['id']);
            }
            if (isset($expected['errorCode'])) {
                self::assertSame($expected['errorCode'], $result->errorCode, $case['id']);
                self::assertSame($expected['message'], $result->message, $case['id']);
            }
            if (isset($expected['normalizedAmount'])) {
                self::assertSame($expected['normalizedAmount'], $result->normalizedAmount, $case['id']);
            }
        }
    }
}
