<?php

declare(strict_types=1);

namespace Waffo;

use Waffo\Config\WaffoConfig;
use Waffo\Crypto\Rsa;
use Waffo\Http\WaffoHttpClient;
use Waffo\Resource\MerchantConfigResource;
use Waffo\Resource\OrderResource;
use Waffo\Resource\PayMethodConfigResource;
use Waffo\Resource\RefundResource;
use Waffo\Resource\SubscriptionResource;
use Waffo\Resource\WalletResource;
use Waffo\Webhook\WebhookHandler;

final class Waffo
{
    private WaffoHttpClient $httpClient;
    private WebhookHandler $webhookHandler;

    public function __construct(private WaffoConfig $config)
    {
        $this->httpClient = new WaffoHttpClient($config);
        $this->webhookHandler = new WebhookHandler($config);
    }

    public static function fromEnvironment(): self
    {
        return new self(WaffoConfig::fromEnvironment());
    }

    /** @return array{privateKey: string, publicKey: string} */
    public static function generateKeyPair(int $bits = 2048): array
    {
        return Rsa::generateKeyPair($bits);
    }

    public function order(): OrderResource
    {
        return new OrderResource($this->httpClient);
    }

    public function refund(): RefundResource
    {
        return new RefundResource($this->httpClient);
    }

    public function subscription(): SubscriptionResource
    {
        return new SubscriptionResource($this->httpClient);
    }

    public function merchantConfig(): MerchantConfigResource
    {
        return new MerchantConfigResource($this->httpClient);
    }

    public function payMethodConfig(): PayMethodConfigResource
    {
        return new PayMethodConfigResource($this->httpClient);
    }

    public function wallet(): WalletResource
    {
        return new WalletResource($this->httpClient);
    }

    public function webhook(): WebhookHandler
    {
        return $this->webhookHandler;
    }
}
