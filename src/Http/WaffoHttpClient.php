<?php

declare(strict_types=1);

namespace Waffo\Http;

use JsonException;
use Throwable;
use Waffo\Config\RequestOptions;
use Waffo\Config\WaffoConfig;
use Waffo\Crypto\Rsa;
use Waffo\Exception\WaffoError;
use Waffo\Exception\WaffoUnknownStatusError;
use Waffo\Model\AbstractModel;
use Waffo\Response\ApiResponse;
use Waffo\Util\Json;
use Waffo\Version;

final class WaffoHttpClient
{
    public const API_VERSION = '1.0.0';

    private readonly HttpTransport $transport;

    public function __construct(private readonly WaffoConfig $config)
    {
        $this->transport = $config->httpTransport ?? new CurlHttpTransport();
    }

    /** @param array<string, mixed>|AbstractModel $params */
    public function post(
        string $path,
        array|AbstractModel $params,
        ?string $responseSchema = null,
        ?RequestOptions $options = null,
        ?string $merchantIdPath = null,
    ): ApiResponse {
        try {
            $payload = $params instanceof AbstractModel ? $params->toArray() : $params;
            $payload = MerchantIdInjector::inject($payload, $this->config->merchantId, $merchantIdPath);
            $body = Json::encode($payload);
        } catch (JsonException $error) {
            return ApiResponse::error('S0005', 'Failed to serialize request: ' . $error->getMessage());
        }

        try {
            $signature = Rsa::sign($body, $this->config->privateKey);
            $url = $this->config->environment->baseUrl() . $path;

            $this->config->logger?->debug('Waffo request', ['url' => $url]);
            $response = $this->transport->send(new HttpRequest(
                url: $url,
                method: 'POST',
                headers: [
                    'Content-Type' => 'application/json',
                    'X-API-KEY' => $this->config->apiKey,
                    'X-SIGNATURE' => $signature,
                    'X-API-VERSION' => self::API_VERSION,
                    'X-SDK-VERSION' => Version::SDK_HEADER,
                ],
                body: $body,
                connectTimeoutMs: $options->connectTimeoutMs ?? $this->config->connectTimeoutMs,
                readTimeoutMs: $options->readTimeoutMs ?? $this->config->readTimeoutMs,
            ));
            $this->config->logger?->debug('Waffo response', ['statusCode' => $response->statusCode]);

            $responseSignature = $response->header('X-SIGNATURE');
            if ($responseSignature !== null && !Rsa::verify($response->body, $responseSignature, $this->config->waffoPublicKey)) {
                return ApiResponse::error('S0004', 'Response signature verification failed');
            }

            $apiResponse = ApiResponse::fromJson($response->body, $responseSchema);
            if ($apiResponse->code === 'E0001') {
                throw new WaffoUnknownStatusError('E0001', $apiResponse->message ?? 'Unknown status from server');
            }

            return $apiResponse;
        } catch (WaffoUnknownStatusError $error) {
            throw $error;
        } catch (NetworkException $error) {
            throw new WaffoUnknownStatusError(
                WaffoUnknownStatusError::CODE_NETWORK_ERROR,
                'Network error, payment status unknown: ' . $error->getMessage(),
                $error,
            );
        } catch (JsonException $error) {
            return ApiResponse::error('S0006', 'Failed to parse response: ' . $error->getMessage());
        } catch (WaffoError $error) {
            throw $error;
        } catch (Throwable $error) {
            $this->config->logger?->error('Unexpected Waffo SDK error', ['exception' => $error::class]);

            return ApiResponse::error('S0006', 'Unexpected error: ' . $error->getMessage());
        }
    }
}
