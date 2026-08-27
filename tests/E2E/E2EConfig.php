<?php

declare(strict_types=1);

namespace Waffo\Tests\E2E;

use Waffo\Config\Environment;
use Waffo\Config\WaffoConfig;

final class E2EConfig
{
    public function __construct(
        public string $apiKey,
        public string $privateKey,
        public string $waffoPublicKey,
        public string $merchantId,
        public Environment $environment,
    ) {
    }

    public static function load(): self
    {
        $environmentValues = [
            'api-key' => getenv('WAFFO_API_KEY'),
            'private-key' => getenv('WAFFO_PRIVATE_KEY'),
            'waffo-public-key' => getenv('WAFFO_PUBLIC_KEY'),
            'merchant-id' => getenv('WAFFO_MERCHANT_ID'),
            'environment' => getenv('WAFFO_ENVIRONMENT') ?: 'sandbox',
        ];
        if (self::hasRequiredValues($environmentValues)) {
            return self::fromValues($environmentValues);
        }

        $configuredPath = getenv('WAFFO_E2E_CONFIG');
        $candidates = [];
        if (is_string($configuredPath) && $configuredPath !== '') {
            $candidates[] = $configuredPath;
        }
        $candidates[] = __DIR__ . '/application-test.yml';
        $candidates[] = __DIR__ . '/../../../waffo-node/test/e2e/application-test.yml';
        $candidates[] = __DIR__ . '/../../../waffo-go/test/e2e/application-test.yml';
        $candidates[] = __DIR__ . '/../../../waffo-java/src/test/resources/application-test.yml';

        foreach ($candidates as $path) {
            if (!is_file($path)) {
                continue;
            }
            $values = self::parseFlatWaffoYaml($path);
            if (self::hasRequiredValues($values)) {
                return self::fromValues($values);
            }
        }

        throw new \RuntimeException('Waffo E2E credentials are missing or incomplete');
    }

    public function toSdkConfig(): WaffoConfig
    {
        return new WaffoConfig(
            apiKey: $this->apiKey,
            privateKey: $this->privateKey,
            waffoPublicKey: $this->waffoPublicKey,
            environment: $this->environment,
            merchantId: $this->merchantId,
            connectTimeoutMs: 15_000,
            readTimeoutMs: 60_000,
        );
    }

    /** @param array<string, string|false> $values */
    private static function hasRequiredValues(array $values): bool
    {
        foreach (['api-key', 'private-key', 'waffo-public-key', 'merchant-id'] as $name) {
            if (!isset($values[$name]) || !is_string($values[$name]) || trim($values[$name]) === '') {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, string|false> $values */
    private static function fromValues(array $values): self
    {
        return new self(
            apiKey: (string) $values['api-key'],
            privateKey: (string) $values['private-key'],
            waffoPublicKey: (string) $values['waffo-public-key'],
            merchantId: (string) $values['merchant-id'],
            environment: Environment::parse((string) (($values['environment'] ?? null) ?: 'sandbox')),
        );
    }

    /** @return array<string, string> */
    private static function parseFlatWaffoYaml(string $path): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException("Unable to read E2E config: {$path}");
        }

        $values = [];
        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            if (preg_match('/^\s{2}([a-z-]+):\s*(.*?)\s*$/', $line, $matches) !== 1) {
                continue;
            }
            $value = trim($matches[2]);
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }
            $values[$matches[1]] = $value;
        }

        return $values;
    }
}
