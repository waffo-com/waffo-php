<?php

declare(strict_types=1);

namespace Waffo\Resource;

use Waffo\Config\RequestOptions;
use Waffo\Http\WaffoHttpClient;
use Waffo\Model\AbstractModel;
use Waffo\Response\ApiResponse;

final class OrderResource extends BaseResource
{
    public function __construct(WaffoHttpClient $httpClient)
    {
        parent::__construct($httpClient, '/order');
    }

    /** @param array<string, mixed>|AbstractModel $params */
    public function create(array|AbstractModel $params, ?RequestOptions $options = null): ApiResponse
    {
        return $this->post(
            '/create',
            $this->withTimestamp($params, 'orderRequestedAt'),
            'AcqOrderCreatedResponse',
            $options,
            'merchantInfo.merchantId',
        );
    }

    /** @param array<string, mixed>|AbstractModel $params */
    public function inquiry(array|AbstractModel $params, ?RequestOptions $options = null): ApiResponse
    {
        return $this->postInquiry('/inquiry', $params, 'AcqOrderInquiry', $options, null);
    }

    /** @param array<string, mixed>|AbstractModel $params */
    public function cancel(array|AbstractModel $params, ?RequestOptions $options = null): ApiResponse
    {
        return $this->post(
            '/cancel',
            $this->withTimestamp($params, 'orderRequestedAt'),
            'AcqOrderCancelResponse',
            $options,
            'merchantId',
        );
    }

    /** @param array<string, mixed>|AbstractModel $params */
    public function refund(array|AbstractModel $params, ?RequestOptions $options = null): ApiResponse
    {
        return $this->post(
            '/refund',
            $this->withTimestamp($params, 'requestedAt'),
            'AcqOrderRefundResponse',
            $options,
            'merchantId',
        );
    }

    /** @param array<string, mixed>|AbstractModel $params */
    public function capture(array|AbstractModel $params, ?RequestOptions $options = null): ApiResponse
    {
        return $this->post(
            '/capture',
            $this->withTimestamp($params, 'captureRequestedAt'),
            'OrderCaptureResponse',
            $options,
            'merchantId',
        );
    }
}
