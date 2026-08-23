# PHP Framework Webhook Integration

All frameworks must pass the unmodified request body and `X-SIGNATURE` header to `WebhookHandler::handleWebhook()`, then return HTTP 200 with the returned body and response signature.

## Laravel

Use `$request->getContent()` for the raw body and return `response($result->responseBody, 200)->header('X-SIGNATURE', $result->responseSignature)`.

## Symfony

Use `$request->getContent()` and create a `Response` with status 200, `Content-Type: application/json`, and `X-SIGNATURE`.

## Slim

Read `(string) $request->getBody()`, write `responseBody` to the response stream, and attach `X-SIGNATURE` before returning the response.
