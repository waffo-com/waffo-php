<?php

declare(strict_types=1);

namespace Waffo\Http;

final class CurlHttpTransport implements HttpTransport
{
    public function send(HttpRequest $request): HttpResponse
    {
        $handle = curl_init($request->url);
        if ($handle === false) {
            throw new NetworkException('Unable to initialize cURL');
        }

        $responseHeaders = [];
        $headerLines = [];
        foreach ($request->headers as $name => $value) {
            $headerLines[] = "{$name}: {$value}";
        }

        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $request->method,
            CURLOPT_POSTFIELDS => $request->body,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT_MS => $request->connectTimeoutMs,
            CURLOPT_TIMEOUT_MS => $request->readTimeoutMs,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $length = strlen($line);
                $separator = strpos($line, ':');
                if ($separator !== false) {
                    $name = trim(substr($line, 0, $separator));
                    $value = trim(substr($line, $separator + 1));
                    $responseHeaders[$name][] = $value;
                }

                return $length;
            },
        ]);

        $body = curl_exec($handle);
        if ($body === false) {
            $message = curl_error($handle);
            throw new NetworkException($message !== '' ? $message : 'cURL request failed');
        }

        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        return new HttpResponse($statusCode, $responseHeaders, (string) $body);
    }
}
