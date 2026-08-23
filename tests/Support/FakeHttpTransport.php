<?php

declare(strict_types=1);

namespace Waffo\Tests\Support;

use Waffo\Http\HttpRequest;
use Waffo\Http\HttpResponse;
use Waffo\Http\HttpTransport;

final class FakeHttpTransport implements HttpTransport
{
    /** @var list<HttpRequest> */
    public array $requests = [];

    /** @var list<HttpResponse|\Throwable> */
    private array $responses;

    public function __construct(HttpResponse|\Throwable ...$responses)
    {
        $this->responses = $responses;
    }

    public function send(HttpRequest $request): HttpResponse
    {
        $this->requests[] = $request;
        $response = array_shift($this->responses);
        if ($response instanceof \Throwable) {
            throw $response;
        }
        if (!$response instanceof HttpResponse) {
            throw new \RuntimeException('No fake HTTP response queued');
        }

        return $response;
    }
}
