<?php

declare(strict_types=1);

namespace Waffo\Tests\E2E;

use JsonSerializable;
use Waffo\Model\AbstractModel;
use Waffo\Response\ApiResponse;

final class E2EArtifactRecorder
{
    private readonly string $eventsPath;

    public function __construct()
    {
        $directory = getenv('WAFFO_E2E_ARTIFACT_DIR');
        if (!is_string($directory) || $directory === '') {
            $directory = sys_get_temp_dir() . '/waffo-php-e2e-artifacts';
        }
        if (!is_dir($directory) && !mkdir($directory, 0o755, true) && !is_dir($directory)) {
            throw new \RuntimeException("Unable to create E2E artifact directory: {$directory}");
        }
        $this->eventsPath = rtrim($directory, DIRECTORY_SEPARATOR) . '/events.ndjson';
    }

    /** @param array<string, mixed> $details */
    public function record(string $case, string $phase, array $details): void
    {
        $event = [
            'timestamp' => gmdate('Y-m-d\TH:i:s.v\Z'),
            'case' => $case,
            'phase' => $phase,
            'details' => self::sanitize($details),
        ];
        $line = json_encode($event, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        if (file_put_contents($this->eventsPath, $line, FILE_APPEND | LOCK_EX) === false) {
            throw new \RuntimeException('Unable to append E2E evidence');
        }
    }

    public function recordResponse(string $case, string $phase, ApiResponse $response): void
    {
        $this->record($case, $phase, [
            'code' => $response->code,
            'message' => $response->message,
            'data' => $response->data,
        ]);
    }

    public static function formatResponse(ApiResponse $response): string
    {
        return json_encode(self::sanitize([
            'code' => $response->code,
            'message' => $response->message,
            'data' => $response->data,
        ]), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private static function sanitize(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && preg_match('/api.?key|private.?key|public.?key|signature|card|cvv|token|secret|password|authorization|pay.?method.?(?:properties|response)/i', $key) === 1) {
            return '[REDACTED]';
        }
        if ($value instanceof AbstractModel) {
            return self::sanitize($value->toArray(), $key);
        }
        if ($value instanceof JsonSerializable) {
            return self::sanitize($value->jsonSerialize(), $key);
        }
        if (is_object($value)) {
            return self::sanitize(get_object_vars($value), $key);
        }
        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $itemKey => $item) {
                $sanitized[$itemKey] = self::sanitize($item, is_string($itemKey) ? $itemKey : null);
            }

            return $sanitized;
        }
        if (is_string($value) && $key !== null && preg_match('/action|url$/i', $key) === 1) {
            return [
                'origin' => self::urlOrigin($value),
                'sha256' => hash('sha256', $value),
            ];
        }

        return $value;
    }

    private static function urlOrigin(string $value): ?string
    {
        $parts = parse_url($value);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        return $parts['scheme'] . '://' . $parts['host'];
    }
}
