<?php

declare(strict_types=1);

namespace Waffo\Http;

final class MerchantIdInjector
{
    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    public static function inject(array $payload, ?string $merchantId, ?string $path): array
    {
        if ($merchantId === null || $merchantId === '' || $path === null) {
            return $payload;
        }

        if ($path === 'merchantInfo.merchantId') {
            $merchantInfo = $payload['merchantInfo'] ?? [];
            if (!is_array($merchantInfo)) {
                return $payload;
            }
            if (!isset($merchantInfo['merchantId']) || $merchantInfo['merchantId'] === '') {
                $merchantInfo = ['merchantId' => $merchantId] + $merchantInfo;
            }
            $payload['merchantInfo'] = $merchantInfo;

            return $payload;
        }

        if ($path === 'merchantId' && (!isset($payload['merchantId']) || $payload['merchantId'] === '')) {
            $payload['merchantId'] = $merchantId;
        }

        return $payload;
    }

    private function __construct()
    {
    }
}
