<?php

declare(strict_types=1);

namespace Waffo\Http;

interface HttpTransport
{
    public function send(HttpRequest $request): HttpResponse;
}
