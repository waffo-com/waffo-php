<?php

declare(strict_types=1);

namespace Waffo\Resource;

use Waffo\Config\RequestOptions;
use Waffo\Exception\WaffoUnknownStatusError;
use Waffo\Http\WaffoHttpClient;
use Waffo\Model\AbstractModel;
use Waffo\Response\ApiResponse;

abstract class BaseResource
{
    public function __construct(
        protected readonly WaffoHttpClient $httpClient,
        private readonly string $basePath,
    ) {
    }

    /** @param array<string, mixed>|AbstractModel $params */
    final protected function post(
        string $endpoint,
        array|AbstractModel $params,
        string $responseSchema,
        ?RequestOptions $options,
        ?string $merchantIdPath,
    ): ApiResponse {
        return $this->httpClient->post(
            $this->basePath . $endpoint,
            $params,
            $responseSchema,
            $options,
            $merchantIdPath,
        );
    }

    /** @param array<string, mixed>|AbstractModel $params */
    final protected function postInquiry(
        string $endpoint,
        array|AbstractModel $params,
        string $responseSchema,
        ?RequestOptions $options,
        ?string $merchantIdPath,
    ): ApiResponse {
        try {
            return $this->post($endpoint, $params, $responseSchema, $options, $merchantIdPath);
        } catch (WaffoUnknownStatusError $error) {
            return ApiResponse::error($error->errorCode, $error->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>|AbstractModel $params
     * @return array<string, mixed>
     */
    final protected function withTimestamp(array|AbstractModel $params, string $field): array
    {
        if ($params instanceof AbstractModel) {
            $payload = $params->toArray();
            if (!isset($payload[$field]) || $payload[$field] === '') {
                $payload[$field] = \Waffo\Util\Time::now();
            }

            return $payload;
        }

        if (!isset($params[$field]) || $params[$field] === '') {
            $params[$field] = \Waffo\Util\Time::now();
        }

        return $params;
    }
}
