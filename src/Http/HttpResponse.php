<?php

declare(strict_types=1);

namespace Waffo\Http;

final class HttpResponse
{
    /** @param array<string, list<string>> $headers */
    public function __construct(
        public int $statusCode,
        public array $headers,
        public string $body,
    ) {
    }

    public function header(string $name): ?string
    {
        foreach ($this->headers as $headerName => $values) {
            if (strcasecmp($headerName, $name) === 0) {
                return $values[0] ?? null;
            }
        }

        return null;
    }
}
