<?php

declare(strict_types=1);

namespace Waffo\Http;

final readonly class HttpRequest
{
    /** @var non-empty-string */
    public string $url;

    /** @var non-empty-string */
    public string $method;

    /** @var array<string, string> */
    public array $headers;

    public string $body;
    public int $connectTimeoutMs;
    public int $readTimeoutMs;

    /**
     * @param non-empty-string      $url
     * @param non-empty-string      $method
     * @param array<string, string> $headers
     */
    public function __construct(
        string $url,
        string $method,
        array $headers,
        string $body,
        int $connectTimeoutMs,
        int $readTimeoutMs,
    ) {
        if ($url === '' || $method === '') {
            throw new \InvalidArgumentException('HTTP request URL and method cannot be empty');
        }

        $this->url = $url;
        $this->method = $method;
        $this->headers = $headers;
        $this->body = $body;
        $this->connectTimeoutMs = $connectTimeoutMs;
        $this->readTimeoutMs = $readTimeoutMs;
    }
}
