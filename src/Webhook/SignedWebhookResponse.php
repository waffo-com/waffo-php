<?php

declare(strict_types=1);

namespace Waffo\Webhook;

final readonly class SignedWebhookResponse
{
    public function __construct(public string $body, public string $signature)
    {
    }
}
