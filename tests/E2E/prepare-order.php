<?php

declare(strict_types=1);

use Waffo\Model\Generated\AcqOrderCreatedResponse;
use Waffo\Tests\E2E\E2EArtifactRecorder;
use Waffo\Tests\E2E\E2EConfig;
use Waffo\Tests\E2E\E2EFixtures;
use Waffo\Waffo;

require __DIR__ . '/../../vendor/autoload.php';

$label = $argv[1] ?? 'payment-success';
$mode = $argv[2] ?? 'dana';
$statePath = getenv('WAFFO_E2E_BROWSER_STATE');
if (!is_string($statePath) || $statePath === '') {
    throw new RuntimeException('WAFFO_E2E_BROWSER_STATE is required');
}

$waffo = new Waffo(E2EConfig::load()->toSdkConfig());
$artifacts = new E2EArtifactRecorder();
$paymentRequestId = E2EFixtures::uniqueId('php_browser');
$merchantOrderId = E2EFixtures::uniqueId('PHP_BROWSER');
$payload = $mode === 'manual-capture'
    ? E2EFixtures::manualCaptureOrderPayload($paymentRequestId, $merchantOrderId, E2EFixtures::notifyUrl())
    : E2EFixtures::danaOrderPayload($paymentRequestId, $merchantOrderId, '10000', E2EFixtures::notifyUrl());
$response = $waffo->order()->create($payload);
$artifacts->recordResponse($label, 'create', $response);
if (!$response->isSuccess() || !$response->data instanceof AcqOrderCreatedResponse) {
    throw new RuntimeException(
        "Order create failed: paymentRequestId={$paymentRequestId}, merchantOrderId={$merchantOrderId}, response="
            . E2EArtifactRecorder::formatResponse($response),
    );
}
$checkoutUrl = E2EFixtures::actionUrl($response->data->orderAction);
if ($checkoutUrl === '' || $response->data->acquiringOrderId === null) {
    throw new RuntimeException(
        'Order create response did not contain a browser action and acquiring order id: '
            . "paymentRequestId={$paymentRequestId}, merchantOrderId={$merchantOrderId}, response="
            . E2EArtifactRecorder::formatResponse($response),
    );
}

$state = [
    'label' => $label,
    'paymentRequestId' => $paymentRequestId,
    'merchantOrderId' => $merchantOrderId,
    'acquiringOrderId' => $response->data->acquiringOrderId,
    'checkoutUrl' => $checkoutUrl,
    'mode' => $mode,
    'amount' => (string) $payload['orderAmount'],
];
file_put_contents($statePath, json_encode($state, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
fwrite(STDOUT, json_encode(array_diff_key($state, ['checkoutUrl' => true]), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
