<?php

declare(strict_types=1);

namespace Waffo\Tests\Vector;

use PHPUnit\Framework\TestCase;
use Waffo\Config\WaffoConfig;
use Waffo\Http\HttpResponse;
use Waffo\Http\MerchantIdInjector;
use Waffo\Tests\Support\FakeHttpTransport;
use Waffo\Tests\Support\FixtureKeys;
use Waffo\Tests\Support\TestAsset;
use Waffo\Waffo;

final class MerchantIdInjectionVectorTest extends TestCase
{
    public function testAllMerchantIdInjectionCases(): void
    {
        $raw = file_get_contents(TestAsset::path(__DIR__, '../../../../sdk-spec/test-vectors/merchantid-injection.json', '../../sdk-spec/test-vectors/merchantid-injection.json'));
        self::assertNotFalse($raw);
        $vector = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        foreach ($vector['unitTests'] as $case) {
            $input = $case['input'];
            if (array_key_exists('requestMerchantId', $input)) {
                $payload = ['merchantId' => $input['requestMerchantId']];
                $actual = MerchantIdInjector::inject($payload, $input['configMerchantId'], 'merchantId');
                self::assertSame($case['expected']['finalMerchantId'], $actual['merchantId'], $case['id']);
                continue;
            }
            $request = $input['request'];
            $path = array_key_exists('merchantId', $request) ? 'merchantId' : 'merchantInfo.merchantId';
            self::assertSame(
                $case['expected']['request'],
                MerchantIdInjector::inject($request, $input['configMerchantId'], $path),
                $case['id'],
            );
        }

        foreach ($vector['edgeCases'] as $case) {
            self::assertSame(
                $case['expected']['request'],
                MerchantIdInjector::inject(
                    $case['input']['request'],
                    $case['input']['configMerchantId'],
                    'merchantInfo.merchantId',
                ),
                $case['id'],
            );
        }
    }

    public function testAllResourceOperationsUseVectorInjectionPath(): void
    {
        $vectorRaw = file_get_contents(TestAsset::path(__DIR__, '../../../../sdk-spec/test-vectors/merchantid-injection.json', '../../sdk-spec/test-vectors/merchantid-injection.json'));
        self::assertNotFalse($vectorRaw);
        $vector = json_decode($vectorRaw, true, 512, JSON_THROW_ON_ERROR);

        $keys = FixtureKeys::load();

        foreach ($vector['requestTypeTests'] as $case) {
            $transport = new FakeHttpTransport(new HttpResponse(200, [], '{"code":"0","data":{}}'));
            $waffo = new Waffo(new WaffoConfig(
                'api-key',
                $keys['privateKey'],
                $keys['publicKey'],
                merchantId: 'CONFIG_MERCHANT_123',
                httpTransport: $transport,
            ));

            $resource = $waffo->{$case['capability']}();
            $response = $resource->{$case['method']}([]);

            self::assertTrue($response->isSuccess(), $case['id']);
            self::assertCount(1, $transport->requests, $case['id']);
            $payload = json_decode($transport->requests[0]->body, true, 512, JSON_THROW_ON_ERROR);
            if ($case['injectionPath'] === 'merchantInfo.merchantId') {
                self::assertSame('CONFIG_MERCHANT_123', $payload['merchantInfo']['merchantId'], $case['id']);
                self::assertArrayNotHasKey('merchantId', $payload, $case['id']);
            } elseif ($case['injectionPath'] === 'merchantId') {
                self::assertSame('CONFIG_MERCHANT_123', $payload['merchantId'], $case['id']);
                self::assertArrayNotHasKey('merchantInfo', $payload, $case['id']);
            } else {
                self::assertArrayNotHasKey('merchantId', $payload, $case['id']);
                self::assertArrayNotHasKey('merchantInfo', $payload, $case['id']);
            }
        }
    }
}
