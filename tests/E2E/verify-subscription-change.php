<?php

declare(strict_types=1);

use Waffo\Model\Generated\SubscriptionChangeInquiryResponse;
use Waffo\Tests\E2E\E2EArtifactRecorder;
use Waffo\Tests\E2E\E2EConfig;
use Waffo\Waffo;

require __DIR__ . '/../../vendor/autoload.php';

$statePath = getenv('WAFFO_E2E_BROWSER_STATE');
if (!is_string($statePath) || !is_file($statePath)) {
    throw new RuntimeException('WAFFO_E2E_BROWSER_STATE must point to a subscription change state');
}
$rawState = file_get_contents($statePath);
if ($rawState === false) {
    throw new RuntimeException('Unable to read subscription change state');
}
$state = json_decode($rawState, true, 512, JSON_THROW_ON_ERROR);
$label = (string) $state['label'];
$originSubscriptionRequest = (string) $state['originSubscriptionRequest'];
$subscriptionRequest = (string) $state['subscriptionRequest'];
$waffo = new Waffo(E2EConfig::load()->toSdkConfig());
$artifacts = new E2EArtifactRecorder();

$deadline = microtime(true) + 120;
$completed = null;
do {
    $response = $waffo->subscription()->changeInquiry([
        'originSubscriptionRequest' => $originSubscriptionRequest,
        'subscriptionRequest' => $subscriptionRequest,
    ]);
    $lastResponse = $response;
    $artifacts->recordResponse($label, 'poll-SUCCESS', $response);
    if ($response->isSuccess()
        && $response->data instanceof SubscriptionChangeInquiryResponse
        && $response->data->subscriptionChangeStatus === 'SUCCESS') {
        $completed = $response->data;
        break;
    }
    usleep(2_000_000);
} while (microtime(true) < $deadline);
if (!$completed instanceof SubscriptionChangeInquiryResponse) {
    throw new RuntimeException(
        "Timed out waiting for originSubscriptionRequest={$originSubscriptionRequest}, "
            . "subscriptionRequest={$subscriptionRequest} SUCCESS; response="
            . E2EArtifactRecorder::formatResponse($lastResponse),
    );
}
if ($completed->originSubscriptionRequest !== $originSubscriptionRequest
    || $completed->subscriptionRequest !== $subscriptionRequest) {
    throw new RuntimeException(
        'Subscription change inquiry returned different request identifiers: '
            . "originSubscriptionRequest={$originSubscriptionRequest}, subscriptionRequest={$subscriptionRequest}, "
            . 'response=' . E2EArtifactRecorder::formatResponse($lastResponse),
    );
}

fwrite(STDOUT, json_encode([
    'label' => $label,
    'originSubscriptionRequest' => $originSubscriptionRequest,
    'subscriptionRequest' => $subscriptionRequest,
    'subscriptionId' => $completed->subscriptionId,
    'subscriptionChangeStatus' => $completed->subscriptionChangeStatus,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
