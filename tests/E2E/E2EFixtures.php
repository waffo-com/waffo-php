<?php

declare(strict_types=1);

namespace Waffo\Tests\E2E;

final class E2EFixtures
{
    public static function notifyUrl(): string
    {
        $configured = getenv('WAFFO_E2E_NOTIFY_URL');

        return is_string($configured) && $configured !== ''
            ? rtrim($configured, '/')
            : 'https://httpbin.org/post';
    }

    /** @return array<string, mixed> */
    public static function danaOrderPayload(
        string $paymentRequestId,
        string $merchantOrderId,
        string $amount,
        string $notifyUrl,
    ): array {
        return [
            'paymentRequestId' => $paymentRequestId,
            'merchantOrderId' => $merchantOrderId,
            'orderCurrency' => 'IDR',
            'orderAmount' => $amount,
            'orderDescription' => 'PHP SDK E2E DANA order',
            'notifyUrl' => $notifyUrl,
            'successRedirectUrl' => 'https://httpbin.org/get?status=success',
            'failedRedirectUrl' => 'https://httpbin.org/get?status=failed',
            'cancelRedirectUrl' => 'https://httpbin.org/get?status=cancel',
            'userInfo' => [
                'userId' => self::uniqueId('php_e2e_user'),
                'userEmail' => 'php-sdk-e2e@waffo.test',
                'userTerminal' => 'WEB',
                'userCountryCode' => 'IDN',
            ],
            'goodsInfo' => [
                'goodsId' => 'PHP_DANA_E2E',
                'goodsName' => 'PHP SDK DANA E2E Product',
                'goodsCategory' => 'GOODS',
                'goodsUrl' => 'https://example.com/goods/php-dana-e2e',
                'goodsQuantity' => 1,
            ],
            'paymentInfo' => [
                'productName' => 'ONE_TIME_PAYMENT',
                'payMethodType' => 'EWALLET',
                'payMethodName' => 'DANA',
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function manualCaptureOrderPayload(
        string $paymentRequestId,
        string $merchantOrderId,
        string $notifyUrl,
    ): array {
        return [
            'paymentRequestId' => $paymentRequestId,
            'merchantOrderId' => $merchantOrderId,
            'orderCurrency' => 'HKD',
            'orderAmount' => '10.00',
            'orderDescription' => 'PHP SDK E2E manual capture order',
            'notifyUrl' => $notifyUrl,
            'successRedirectUrl' => 'https://httpbin.org/get?status=success',
            'failedRedirectUrl' => 'https://httpbin.org/get?status=failed',
            'cancelRedirectUrl' => 'https://httpbin.org/get?status=cancel',
            'userInfo' => [
                'userId' => self::uniqueId('php_capture_user'),
                'userEmail' => 'php-sdk-capture-e2e@waffo.test',
                'userTerminal' => 'WEB',
                'userCountryCode' => 'HKG',
            ],
            'paymentInfo' => [
                'productName' => 'ONE_TIME_PAYMENT',
                'payMethodType' => 'CREDITCARD',
                'captureMode' => 'manualCapture',
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function x402OrderPayload(
        string $paymentRequestId,
        string $merchantOrderId,
        string $amount,
        string $notifyUrl,
        ?string $paymentSignatureHeader = null,
    ): array {
        $x402Info = ['x402Request' => true];
        if ($paymentSignatureHeader !== null && $paymentSignatureHeader !== '') {
            $x402Info['paymentSignatureHeader'] = $paymentSignatureHeader;
        }

        return [
            'paymentRequestId' => $paymentRequestId,
            'merchantOrderId' => $merchantOrderId,
            'orderCurrency' => 'USDC',
            'orderAmount' => $amount,
            'orderDescription' => 'PHP SDK x402 E2E order',
            'notifyUrl' => $notifyUrl,
            'successRedirectUrl' => 'https://httpbin.org/get?status=success',
            'failedRedirectUrl' => 'https://httpbin.org/get?status=failed',
            'cancelRedirectUrl' => 'https://httpbin.org/get?status=cancel',
            'userInfo' => [
                'userId' => self::uniqueId('php_x402_user'),
                'userEmail' => 'php-sdk-x402-e2e@waffo.test',
                'userTerminal' => 'WEB',
                'userCreatedAt' => '2021-05-16T01:12:53.233Z',
            ],
            'goodsInfo' => [
                'goodsId' => 'PHP_X402_E2E',
                'goodsName' => 'PHP SDK x402 E2E Product',
                'goodsCategory' => 'GOODS',
                'goodsUrl' => 'https://example.com/goods/php-x402-e2e',
                'goodsUniquePrice' => $amount,
                'goodsQuantity' => 1,
            ],
            'paymentInfo' => [
                'productName' => 'ONE_TIME_PAYMENT',
                'payMethodType' => 'CRYPTO',
                'payMethodName' => 'USDC',
            ],
            'x402Info' => $x402Info,
        ];
    }

    /** @return array<string, mixed> */
    public static function subscriptionPayload(
        string $subscriptionRequest,
        string $merchantSubscriptionId,
        string $notifyUrl,
        ?string $userId = null,
    ): array {
        $selectedUserId = $userId ?? self::uniqueId('php_sub_user');

        return [
            'subscriptionRequest' => $subscriptionRequest,
            'merchantSubscriptionId' => $merchantSubscriptionId,
            'currency' => 'HKD',
            'amount' => '99.00',
            'productInfo' => [
                'description' => 'PHP SDK E2E subscription',
                'periodType' => 'MONTHLY',
                'periodInterval' => '1',
                'numberOfPeriod' => '12',
            ],
            'userInfo' => [
                'userId' => $selectedUserId,
                'userEmail' => $selectedUserId . '@waffo.test',
                'userTerminal' => 'WEB',
            ],
            'goodsInfo' => [
                'goodsId' => 'PHP_E2E_SUB',
                'goodsName' => 'PHP SDK E2E Subscription',
                'goodsCategory' => 'SUBSCRIPTION',
                'goodsUrl' => 'https://example.com/subscription',
                'goodsQuantity' => 1,
            ],
            'paymentInfo' => [
                'productName' => 'SUBSCRIPTION',
                'payMethodType' => 'CREDITCARD',
                'payMethodName' => 'CC_VISA',
            ],
            'notifyUrl' => $notifyUrl,
            'successRedirectUrl' => 'https://httpbin.org/get?status=success',
            'failedRedirectUrl' => 'https://httpbin.org/get?status=failed',
            'cancelRedirectUrl' => 'https://httpbin.org/get?status=cancel',
            'subscriptionManagementUrl' => 'https://example.com/subscription/manage',
        ];
    }

    /** @return array<string, mixed> */
    public static function subscriptionChangePayload(
        string $originSubscriptionRequest,
        string $subscriptionRequest,
        string $merchantSubscriptionId,
        string $userId,
        string $notifyUrl,
    ): array {
        return [
            'subscriptionRequest' => $subscriptionRequest,
            'merchantSubscriptionId' => $merchantSubscriptionId,
            'originSubscriptionRequest' => $originSubscriptionRequest,
            'remainingAmount' => '50.00',
            'currency' => 'HKD',
            'orderExpiredAt' => gmdate('Y-m-d\TH:i:s.000\Z', time() + 1_800),
            'notifyUrl' => $notifyUrl,
            'successRedirectUrl' => 'https://httpbin.org/get?status=success',
            'failedRedirectUrl' => 'https://httpbin.org/get?status=failed',
            'cancelRedirectUrl' => 'https://httpbin.org/get?status=cancel',
            'subscriptionManagementUrl' => 'https://example.com/subscription/manage',
            'productInfoList' => [[
                'description' => 'PHP SDK E2E Premium Subscription',
                'periodType' => 'MONTHLY',
                'periodInterval' => '1',
                'amount' => '199.00',
                'numberOfPeriod' => '12',
            ]],
            'userInfo' => [
                'userId' => $userId,
                'userEmail' => $userId . '@waffo.test',
                'userTerminal' => 'WEB',
            ],
            'goodsInfo' => [
                'goodsId' => 'PHP_E2E_SUB_PREMIUM',
                'goodsName' => 'PHP SDK E2E Premium Subscription',
                'goodsCategory' => 'SUBSCRIPTION',
                'goodsUrl' => 'https://example.com/subscription/premium',
                'goodsQuantity' => 1,
            ],
            'paymentInfo' => [
                'productName' => 'SUBSCRIPTION',
                'payMethodType' => 'CREDITCARD',
            ],
        ];
    }

    public static function actionUrl(?string $action): string
    {
        if ($action === null || trim($action) === '') {
            return '';
        }
        $trimmed = trim($action);
        if (str_starts_with($trimmed, 'https://') || str_starts_with($trimmed, 'http://')) {
            return $trimmed;
        }
        try {
            $parsed = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return '';
        }

        return is_array($parsed) ? (string) ($parsed['webUrl'] ?? $parsed['deeplinkUrl'] ?? '') : '';
    }

    public static function uniqueId(string $prefix): string
    {
        return substr($prefix . '_' . gmdate('ymdHis') . '_' . bin2hex(random_bytes(3)), 0, 32);
    }

    private function __construct()
    {
    }
}
