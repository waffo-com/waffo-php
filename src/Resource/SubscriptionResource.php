<?php

declare(strict_types=1);

namespace Waffo\Resource;

use Waffo\Config\RequestOptions;
use Waffo\Http\WaffoHttpClient;
use Waffo\Model\AbstractModel;
use Waffo\Response\ApiResponse;

final class SubscriptionResource extends BaseResource
{
    public function __construct(WaffoHttpClient $httpClient)
    {
        parent::__construct($httpClient, '/subscription');
    }

    /** @param array<string, mixed>|AbstractModel $params */
    public function create(array|AbstractModel $params, ?RequestOptions $options = null): ApiResponse
    {
        return $this->post(
            '/create',
            $this->withTimestamp($params, 'requestedAt'),
            'AcqSubscriptionCreateResponse',
            $options,
            'merchantInfo.merchantId',
        );
    }

    /** @param array<string, mixed>|AbstractModel $params */
    public function inquiry(array|AbstractModel $params, ?RequestOptions $options = null): ApiResponse
    {
        return $this->postInquiry('/inquiry', $params, 'SubscriptionInquiryResponse', $options, null);
    }

    /** @param array<string, mixed>|AbstractModel $params */
    public function cancel(array|AbstractModel $params, ?RequestOptions $options = null): ApiResponse
    {
        return $this->post(
            '/cancel',
            $this->withTimestamp($params, 'requestedAt'),
            'SubscriptionCancelResponse',
            $options,
            'merchantId',
        );
    }

    /** @param array<string, mixed>|AbstractModel $params */
    public function manage(array|AbstractModel $params, ?RequestOptions $options = null): ApiResponse
    {
        return $this->post('/manage', $params, 'SubscriptionManageResponse', $options, null);
    }

    /** @param array<string, mixed>|AbstractModel $params */
    public function update(array|AbstractModel $params, ?RequestOptions $options = null): ApiResponse
    {
        return $this->post('/update', $params, 'SubscriptionUpdateResponse', $options, null);
    }

    /** @param array<string, mixed>|AbstractModel $params */
    public function change(array|AbstractModel $params, ?RequestOptions $options = null): ApiResponse
    {
        return $this->post(
            '/change',
            $this->withTimestamp($params, 'requestedAt'),
            'SubscriptionChangeResponse',
            $options,
            'merchantInfo.merchantId',
        );
    }

    /** @param array<string, mixed>|AbstractModel $params */
    public function changeInquiry(array|AbstractModel $params, ?RequestOptions $options = null): ApiResponse
    {
        return $this->postInquiry(
            '/change/inquiry',
            $params,
            'SubscriptionChangeInquiryResponse',
            $options,
            null,
        );
    }
}
