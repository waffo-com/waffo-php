<?php

declare(strict_types=1);

namespace Waffo\Util;

final class AmountValidator
{
    /** @var array<string, int> */
    private const PRECISION = [
        'BHD' => 3,
        'JOD' => 3,
        'KWD' => 3,
        'OMR' => 3,
        'CLP' => 0,
        'ISK' => 0,
        'JPY' => 0,
        'KRW' => 0,
        'VND' => 0,
    ];

    public static function validate(?string $amount, string $currency): AmountValidationResult
    {
        if ($amount === null) {
            return AmountValidationResult::invalid('INVALID_AMOUNT_FORMAT', 'Amount cannot be null');
        }
        if (trim($amount) === '') {
            return AmountValidationResult::invalid('INVALID_AMOUNT_FORMAT', 'Amount cannot be empty');
        }
        if (!preg_match('/^-?\d+(?:\.\d+)?$/', $amount)) {
            return AmountValidationResult::invalid('INVALID_AMOUNT_FORMAT', "Invalid amount format: {$amount}");
        }
        if (str_starts_with($amount, '-')) {
            return AmountValidationResult::invalid('NEGATIVE_AMOUNT', "Amount cannot be negative: {$amount}");
        }
        if ((float) $amount === 0.0) {
            return AmountValidationResult::invalid('ZERO_AMOUNT', 'Amount cannot be zero');
        }

        $normalizedCurrency = strtoupper($currency);
        $precision = self::PRECISION[$normalizedCurrency] ?? 2;
        $decimalLength = str_contains($amount, '.') ? strlen(substr($amount, strpos($amount, '.') + 1)) : 0;
        if ($decimalLength > $precision) {
            return AmountValidationResult::invalid(
                'INVALID_CURRENCY_PRECISION',
                "Amount '{$amount}' exceeds maximum precision for {$normalizedCurrency} ({$precision} decimals)",
            );
        }

        $normalizedInteger = ltrim(strtok($amount, '.'), '0');
        if ($normalizedInteger === '') {
            $normalizedInteger = '0';
        }
        $normalized = str_contains($amount, '.')
            ? $normalizedInteger . '.' . substr($amount, strpos($amount, '.') + 1)
            : $normalizedInteger;

        return AmountValidationResult::valid($precision, $normalized);
    }

    private function __construct()
    {
    }
}
