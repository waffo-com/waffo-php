<?php

declare(strict_types=1);

namespace Waffo\Resource;

use Waffo\Config\RequestOptions;
use Waffo\Http\WaffoHttpClient;
use Waffo\Model\AbstractModel;
use Waffo\Response\ApiResponse;

final class MerchantConfigResource extends BaseResource
{
    public function __construct(WaffoHttpClient $httpClient)
    {
        parent::__construct($httpClient, '/merchantconfig');
    }

    /** @param array<string, mixed>|AbstractModel $params */
    public function inquiry(array|AbstractModel $params = [], ?RequestOptions $options = null): ApiResponse
    {
        return $this->postInquiry(
            '/inquiry',
            $params,
            'AcqMerchantConfigInquiryResponse',
            $options,
            'merchantId',
        );
    }
}
