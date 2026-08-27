<?php

declare(strict_types=1);

namespace Waffo\Webhook;

final class WebhookResult
{
    public function __construct(
        public bool $success,
        public string $responseBody,
        public string $responseSignature,
        public ?string $error = null,
    ) {
    }
}
