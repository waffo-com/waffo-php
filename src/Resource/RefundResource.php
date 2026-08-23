<?php

declare(strict_types=1);

namespace Waffo\Resource;

use Waffo\Config\RequestOptions;
use Waffo\Http\WaffoHttpClient;
use Waffo\Model\AbstractModel;
use Waffo\Response\ApiResponse;

final class RefundResource extends BaseResource
{
    public function __construct(WaffoHttpClient $httpClient)
    {
        parent::__construct($httpClient, '/refund');
    }

    /** @param array<string, mixed>|AbstractModel $params */
    public function inquiry(array|AbstractModel $params, ?RequestOptions $options = null): ApiResponse
    {
        return $this->postInquiry('/inquiry', $params, 'AcqOrderRefundInquiry', $options, null);
    }
}
