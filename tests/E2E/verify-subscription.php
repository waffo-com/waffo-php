<?php

declare(strict_types=1);

use Waffo\Model\Generated\SubscriptionInquiryResponse;
use Waffo\Model\Generated\SubscriptionManageResponse;
use Waffo\Tests\E2E\E2EArtifactRecorder;
use Waffo\Tests\E2E\E2EConfig;
use Waffo\Waffo;

require __DIR__ . '/../../vendor/autoload.php';

$statePath = getenv('WAFFO_E2E_BROWSER_STATE');
if (!is_string($statePath) || !is_file($statePath)) {
    throw new RuntimeException('WAFFO_E2E_BROWSER_STATE must point to an existing state file');
}
$rawState = file_get_contents($statePath);
if ($rawState === false) {
    throw new RuntimeException('Unable to read browser state');
}
$state = json_decode($rawState, true, 512, JSON_THROW_ON_ERROR);
$label = (string) $state['label'];
$subscriptionRequest = (string) $state['subscriptionRequest'];
$subscriptionId = (string) $state['subscriptionId'];
$waffo = new Waffo(E2EConfig::load()->toSdkConfig());
$artifacts = new E2EArtifactRecorder();

$deadline = microtime(true) + 120;
$active = null;
do {
    $inquiry = $waffo->subscription()->inquiry(['subscriptionRequest' => $subscriptionRequest]);
    $lastInquiry = $inquiry;
    $artifacts->recordResponse($label, 'poll-ACTIVE', $inquiry);
    if ($inquiry->isSuccess()
        && $inquiry->data instanceof SubscriptionInquiryResponse
        && $inquiry->data->subscriptionStatus === 'ACTIVE') {
        $active = $inquiry->data;
        break;
    }
    usleep(2_000_000);
} while (microtime(true) < $deadline);
if (!$active instanceof SubscriptionInquiryResponse) {
    throw new RuntimeException(
        "Timed out waiting for subscriptionRequest={$subscriptionRequest}, subscriptionId={$subscriptionId} ACTIVE; "
            . 'response=' . E2EArtifactRecorder::formatResponse($lastInquiry),
    );
}

$manage = $waffo->subscription()->manage([
    'subscriptionRequest' => $subscriptionRequest,
    'subscriptionId' => $subscriptionId,
]);
$artifacts->recordResponse($label, 'manage', $manage);
if (!$manage->isSuccess() || !$manage->data instanceof SubscriptionManageResponse || $manage->data->managementUrl === null) {
    throw new RuntimeException(
        "Subscription manage failed: subscriptionRequest={$subscriptionRequest}, subscriptionId={$subscriptionId}, "
            . 'response=' . E2EArtifactRecorder::formatResponse($manage),
    );
}

$update = $waffo->subscription()->update([
    'subscriptionRequest' => $subscriptionRequest,
    'amount' => '89.00',
]);
$artifacts->recordResponse($label, 'update', $update);
if (!$update->isSuccess() || $update->data === null) {
    throw new RuntimeException(
        "Subscription update failed: subscriptionRequest={$subscriptionRequest}, subscriptionId={$subscriptionId}, "
            . 'response=' . E2EArtifactRecorder::formatResponse($update),
    );
}

$cancel = $waffo->subscription()->cancel(['subscriptionId' => $subscriptionId]);
$artifacts->recordResponse($label, 'cancel', $cancel);
if (!$cancel->isSuccess()) {
    throw new RuntimeException(
        "Subscription cancel failed: subscriptionRequest={$subscriptionRequest}, subscriptionId={$subscriptionId}, "
            . 'response=' . E2EArtifactRecorder::formatResponse($cancel),
    );
}

$terminalStatuses = ['MERCHANT_CANCELLED', 'CLOSE', 'USER_CANCELLED', 'CHANNEL_CANCELLED'];
$cancelDeadline = microtime(true) + 120;
$terminalStatus = null;
do {
    $inquiry = $waffo->subscription()->inquiry(['subscriptionRequest' => $subscriptionRequest]);
    $artifacts->recordResponse($label, 'poll-cancelled', $inquiry);
    if ($inquiry->isSuccess() && $inquiry->data instanceof SubscriptionInquiryResponse) {
        $terminalStatus = $inquiry->data->subscriptionStatus;
        if (in_array($terminalStatus, $terminalStatuses, true)) {
            break;
        }
    }
    usleep(2_000_000);
} while (microtime(true) < $cancelDeadline);
if (!in_array($terminalStatus, $terminalStatuses, true)) {
    throw new RuntimeException(
        "Timed out waiting for subscriptionRequest={$subscriptionRequest}, subscriptionId={$subscriptionId} "
            . 'cancellation; response=' . E2EArtifactRecorder::formatResponse($inquiry),
    );
}

fwrite(STDOUT, json_encode([
    'label' => $label,
    'subscriptionRequest' => $subscriptionRequest,
    'subscriptionId' => $subscriptionId,
    'activeStatus' => $active->subscriptionStatus,
    'managementUrlVerified' => true,
    'updateVerified' => true,
    'terminalStatus' => $terminalStatus,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
