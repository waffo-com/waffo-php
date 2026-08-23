<?php

declare(strict_types=1);

use Waffo\Tests\E2E\E2EArtifactRecorder;
use Waffo\Tests\E2E\E2EConfig;
use Waffo\Waffo;

require __DIR__ . '/../../vendor/autoload.php';

if (($_SERVER['REQUEST_URI'] ?? '/') === '/health') {
    header('Content-Type: application/json');
    echo '{"status":"ok"}';

    return;
}

if (($_SERVER['REQUEST_URI'] ?? '/') !== '/webhook' || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(404);
    echo 'not found';

    return;
}

$body = file_get_contents('php://input');
if ($body === false) {
    $body = '';
}
$signature = $_SERVER['HTTP_X_SIGNATURE'] ?? null;
$handler = (new Waffo(E2EConfig::load()->toSdkConfig()))->webhook();
$handler
    ->onPayment(static fn (array $notification): null => null)
    ->onRefund(static fn (array $notification): null => null)
    ->onSubscriptionStatus(static fn (array $notification): null => null)
    ->onSubscriptionPeriodChanged(static fn (array $notification): null => null)
    ->onSubscriptionChange(static fn (array $notification): null => null);
$result = $handler->handleWebhook($body, is_string($signature) ? $signature : null);

try {
    $notification = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    $notification = [];
}
$eventType = is_array($notification) && is_string($notification['eventType'] ?? null)
    ? $notification['eventType']
    : 'UNKNOWN';
(new E2EArtifactRecorder())->record('webhook', $eventType, [
    'notification' => is_array($notification) ? $notification : [],
    'handlerSuccess' => $result->success,
    'responseBody' => $result->responseBody,
    'signedResponsePresent' => $result->responseSignature !== '',
    'error' => $result->error,
]);

http_response_code($result->success ? 200 : 400);
header('Content-Type: application/json');
header('X-SIGNATURE: ' . $result->responseSignature);
echo $result->responseBody;
