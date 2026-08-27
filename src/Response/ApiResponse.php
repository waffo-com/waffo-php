<?php

declare(strict_types=1);

namespace Waffo\Response;

use JsonException;
use Waffo\Model\Generated\ModelRegistry;
use Waffo\Util\Json;

final class ApiResponse
{
    public function __construct(
        public string $code,
        public ?string $message = null,
        public mixed $data = null,
    ) {
    }

    public static function success(mixed $data = null, ?string $message = null): self
    {
        return new self('0', $message, $data);
    }

    public static function error(string $code, ?string $message): self
    {
        return new self($code, $message);
    }

    /** @throws JsonException */
    public static function fromJson(string $json, ?string $responseSchema = null): self
    {
        $payload = Json::decodeObject($json);
        $code = (string) ($payload['code'] ?? '');
        $message = isset($payload['msg'])
            ? (string) $payload['msg']
            : (isset($payload['message']) ? (string) $payload['message'] : null);
        $data = $payload['data'] ?? null;

        if ($code === '0' && $responseSchema !== null && is_array($data)) {
            $data = ModelRegistry::hydrate($responseSchema, $data);
        }

        return new self($code !== '' ? $code : 'UNKNOWN', $message, $data);
    }

    public function isSuccess(): bool
    {
        return $this->code === '0';
    }

    public function errorCategory(): ?string
    {
        if ($this->isSuccess()) {
            return null;
        }

        return match ($this->code[0] ?? '') {
            'A' => 'merchant',
            'P' => 'parameter',
            'B' => 'business',
            'C', 'S' => 'system',
            'D' => 'risk',
            'E' => 'unknown_status',
            default => 'unknown',
        };
    }
}
