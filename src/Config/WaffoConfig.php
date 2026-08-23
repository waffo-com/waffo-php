<?php

declare(strict_types=1);

namespace Waffo\Config;

use Waffo\Crypto\Rsa;
use Waffo\Http\HttpTransport;
use Waffo\Log\LoggerInterface;

final readonly class WaffoConfig
{
    public const DEFAULT_CONNECT_TIMEOUT_MS = 10_000;
    public const DEFAULT_READ_TIMEOUT_MS = 30_000;

    public function __construct(
        public string $apiKey,
        public string $privateKey,
        public string $waffoPublicKey,
        public Environment $environment = Environment::Sandbox,
        public ?string $merchantId = null,
        public int $connectTimeoutMs = self::DEFAULT_CONNECT_TIMEOUT_MS,
        public int $readTimeoutMs = self::DEFAULT_READ_TIMEOUT_MS,
        public ?HttpTransport $httpTransport = null,
        public ?LoggerInterface $logger = null,
    ) {
        if (trim($apiKey) === '') {
            throw new \InvalidArgumentException('apiKey cannot be empty');
        }
        if ($connectTimeoutMs <= 0 || $readTimeoutMs <= 0) {
            throw new \InvalidArgumentException('Timeout values must be greater than zero');
        }

        Rsa::assertPrivateKey($privateKey);
        Rsa::assertPublicKey($waffoPublicKey);
    }

    public static function fromEnvironment(): self
    {
        $apiKey = self::requiredEnvironmentValue('WAFFO_API_KEY');
        $privateKey = self::requiredEnvironmentValue('WAFFO_PRIVATE_KEY');
        $publicKey = self::requiredEnvironmentValue('WAFFO_PUBLIC_KEY');
        $environment = Environment::parse((string) (getenv('WAFFO_ENVIRONMENT') ?: 'sandbox'));
        $merchantId = getenv('WAFFO_MERCHANT_ID');

        return new self(
            apiKey: $apiKey,
            privateKey: $privateKey,
            waffoPublicKey: $publicKey,
            environment: $environment,
            merchantId: is_string($merchantId) && $merchantId !== '' ? $merchantId : null,
        );
    }

    private static function requiredEnvironmentValue(string $name): string
    {
        $value = getenv($name);
        if (!is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException("Missing required environment variable: {$name}");
        }

        return $value;
    }
}
