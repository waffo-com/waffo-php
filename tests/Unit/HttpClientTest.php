<?php

declare(strict_types=1);

namespace Waffo\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Waffo\Config\RequestOptions;
use Waffo\Config\WaffoConfig;
use Waffo\Crypto\Rsa;
use Waffo\Exception\WaffoUnknownStatusError;
use Waffo\Http\HttpResponse;
use Waffo\Http\NetworkException;
use Waffo\Http\WaffoHttpClient;
use Waffo\Model\Generated\AcqOrderCreatedResponse;
use Waffo\Tests\Support\FakeHttpTransport;
use Waffo\Tests\Support\FixtureKeys;
use Waffo\Version;

final class HttpClientTest extends TestCase
{
    public function testSignsFinalBodyInjectsMerchantAndHydratesResponse(): void
    {
        $keys = FixtureKeys::load();
        $responseBody = '{"code":"0","message":"success","data":{"acquiringOrderId":"A123","futureField":"kept"}}';
        $transport = new FakeHttpTransport(new HttpResponse(
            200,
            ['X-SIGNATURE' => [Rsa::sign($responseBody, $keys['privateKey'])]],
            $responseBody,
        ));
        $client = new WaffoHttpClient(new WaffoConfig(
            apiKey: 'api-key',
            privateKey: $keys['privateKey'],
            waffoPublicKey: $keys['publicKey'],
            merchantId: 'MID-1',
            httpTransport: $transport,
        ));

        $response = $client->post(
            '/order/create',
            ['merchantInfo' => ['subMerchantId' => 'SUB-1']],
            'AcqOrderCreatedResponse',
            new RequestOptions(1_234, 5_678),
            'merchantInfo.merchantId',
        );

        self::assertTrue($response->isSuccess());
        self::assertInstanceOf(AcqOrderCreatedResponse::class, $response->data);
        self::assertSame('kept', $response->data->extraFields()['futureField']);
        $request = $transport->requests[0];
        self::assertSame('https://api-sandbox.waffo.com/api/v1/order/create', $request->url);
        self::assertSame(1_234, $request->connectTimeoutMs);
        self::assertSame(5_678, $request->readTimeoutMs);
        self::assertSame('1.0.0', $request->headers['X-API-VERSION']);
        self::assertSame(Version::SDK_HEADER, $request->headers['X-SDK-VERSION']);
        self::assertTrue(Rsa::verify($request->body, $request->headers['X-SIGNATURE'], $keys['publicKey']));
        self::assertSame(
            ['merchantInfo' => ['merchantId' => 'MID-1', 'subMerchantId' => 'SUB-1']],
            json_decode($request->body, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testReturnsSignatureErrorForInvalidResponseSignature(): void
    {
        $keys = FixtureKeys::load();
        $transport = new FakeHttpTransport(new HttpResponse(
            200,
            ['X-SIGNATURE' => ['invalid']],
            '{"code":"0","data":{}}',
        ));
        $client = new WaffoHttpClient(new WaffoConfig(
            'api-key',
            $keys['privateKey'],
            $keys['publicKey'],
            httpTransport: $transport,
        ));

        $response = $client->post('/order/create', [], null);

        self::assertSame('S0004', $response->code);
    }

    public function testNetworkFailureThrowsUnknownStatus(): void
    {
        $keys = FixtureKeys::load();
        $client = new WaffoHttpClient(new WaffoConfig(
            'api-key',
            $keys['privateKey'],
            $keys['publicKey'],
            httpTransport: new FakeHttpTransport(new NetworkException('timeout')),
        ));

        $this->expectException(WaffoUnknownStatusError::class);
        $this->expectExceptionMessage('payment status unknown');
        $client->post('/order/create', [], null);
    }

    public function testServerUnknownStatusThrows(): void
    {
        $keys = FixtureKeys::load();
        $client = new WaffoHttpClient(new WaffoConfig(
            'api-key',
            $keys['privateKey'],
            $keys['publicKey'],
            httpTransport: new FakeHttpTransport(new HttpResponse(200, [], '{"code":"E0001","message":"unknown"}')),
        ));

        $this->expectException(WaffoUnknownStatusError::class);
        $client->post('/order/create', [], null);
    }

    public function testSeparatesRequestSerializationAndResponseParsingFailures(): void
    {
        $keys = FixtureKeys::load();
        $transport = new FakeHttpTransport(new HttpResponse(200, [], 'not-json'));
        $client = new WaffoHttpClient(new WaffoConfig(
            'api-key',
            $keys['privateKey'],
            $keys['publicKey'],
            httpTransport: $transport,
        ));

        $serialization = $client->post('/order/create', ['invalid' => NAN], null);
        self::assertSame('S0005', $serialization->code);
        self::assertCount(0, $transport->requests);

        $parsing = $client->post('/order/create', [], null);
        self::assertSame('S0006', $parsing->code);
        self::assertStringStartsWith('Failed to parse response:', (string) $parsing->message);
    }
}
