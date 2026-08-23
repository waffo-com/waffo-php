<?php

declare(strict_types=1);

use Waffo\Model\Generated\AcqOrderInquiry;
use Waffo\Model\Generated\AcqOrderRefundInquiry;
use Waffo\Tests\E2E\E2EArtifactRecorder;
use Waffo\Tests\E2E\E2EConfig;
use Waffo\Tests\E2E\E2EFixtures;
use Waffo\Waffo;

require __DIR__ . '/../../vendor/autoload.php';

$statePath = getenv('WAFFO_E2E_BROWSER_STATE');
$expectedStatus = $argv[1] ?? 'PAY_SUCCESS';
$refund = ($argv[2] ?? '') === '--refund';
$capture = ($argv[2] ?? '') === '--capture';
$captureStatusOnly = ($argv[2] ?? '') === '--capture-status';
if (!is_string($statePath) || !is_file($statePath)) {
    throw new RuntimeException('WAFFO_E2E_BROWSER_STATE must point to an existing state file');
}
$rawState = file_get_contents($statePath);
if ($rawState === false) {
    throw new RuntimeException('Unable to read browser state');
}
$state = json_decode($rawState, true, 512, JSON_THROW_ON_ERROR);
$label = (string) $state['label'];
$paymentRequestId = (string) $state['paymentRequestId'];
$acquiringOrderId = (string) $state['acquiringOrderId'];

$waffo = new Waffo(E2EConfig::load()->toSdkConfig());
$artifacts = new E2EArtifactRecorder();
$deadline = microtime(true) + 120;
$order = null;
do {
    $response = $waffo->order()->inquiry(['acquiringOrderId' => $acquiringOrderId]);
    $lastResponse = $response;
    $artifacts->recordResponse($label, 'poll-' . $expectedStatus, $response);
    if ($response->isSuccess() && $response->data instanceof AcqOrderInquiry) {
        $order = $response->data;
        if ($order->orderStatus === $expectedStatus) {
            break;
        }
    }
    usleep(2_000_000);
} while (microtime(true) < $deadline);

if (!$order instanceof AcqOrderInquiry || $order->orderStatus !== $expectedStatus) {
    throw new RuntimeException(
        "Timed out waiting for paymentRequestId={$paymentRequestId}, acquiringOrderId={$acquiringOrderId} "
            . "status {$expectedStatus}; response="
            . E2EArtifactRecorder::formatResponse($lastResponse),
    );
}
if ($order->paymentRequestId !== $paymentRequestId) {
    throw new RuntimeException(
        "Inquiry returned a different paymentRequestId: expected={$paymentRequestId}, "
            . "acquiringOrderId={$acquiringOrderId}, response="
            . E2EArtifactRecorder::formatResponse($lastResponse),
    );
}

if ($refund) {
    $refundRequestId = E2EFixtures::uniqueId('php_refund');
    $refundResponse = $waffo->order()->refund([
        'acquiringOrderId' => $acquiringOrderId,
        'refundRequestId' => $refundRequestId,
        'refundAmount' => '5000',
        'refundReason' => 'PHP SDK E2E partial refund',
        'refundNotifyUrl' => E2EFixtures::notifyUrl(),
    ]);
    $artifacts->recordResponse($label, 'refund', $refundResponse);
    if (!$refundResponse->isSuccess()) {
        throw new RuntimeException(
            "Refund failed: paymentRequestId={$paymentRequestId}, acquiringOrderId={$acquiringOrderId}, "
                . "refundRequestId={$refundRequestId}, response="
                . E2EArtifactRecorder::formatResponse($refundResponse),
        );
    }
    $refundInquiry = $waffo->refund()->inquiry(['refundRequestId' => $refundRequestId]);
    $artifacts->recordResponse($label, 'refund-inquiry', $refundInquiry);
    if (!$refundInquiry->isSuccess()
        || !$refundInquiry->data instanceof AcqOrderRefundInquiry
        || $refundInquiry->data->refundStatus !== 'ORDER_PARTIALLY_REFUNDED') {
        throw new RuntimeException(
            "Refund inquiry failed: paymentRequestId={$paymentRequestId}, acquiringOrderId={$acquiringOrderId}, "
                . "refundRequestId={$refundRequestId}, response="
                . E2EArtifactRecorder::formatResponse($refundInquiry),
        );
    }
    $state['refundRequestId'] = $refundRequestId;
    file_put_contents(
        $statePath,
        json_encode($state, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
    );
}

if ($capture) {
    $captureResponse = $waffo->order()->capture([
        'paymentRequestId' => $paymentRequestId,
        'acquiringOrderId' => $acquiringOrderId,
        'captureAmount' => (string) $state['amount'],
    ]);
    $artifacts->recordResponse($label, 'capture', $captureResponse);
    if (!$captureResponse->isSuccess()) {
        throw new RuntimeException(
            "Capture failed: paymentRequestId={$paymentRequestId}, acquiringOrderId={$acquiringOrderId}, response="
                . E2EArtifactRecorder::formatResponse($captureResponse),
        );
    }

    $captureDeadline = microtime(true) + 20;
    $acceptedCaptureStatuses = ['CAPTURE_IN_PROGRESS', 'PAY_SUCCESS'];
    do {
        $captureInquiry = $waffo->order()->inquiry(['acquiringOrderId' => $acquiringOrderId]);
        $artifacts->recordResponse($label, 'poll-capture-accepted', $captureInquiry);
        if ($captureInquiry->isSuccess()
            && $captureInquiry->data instanceof AcqOrderInquiry
            && in_array($captureInquiry->data->orderStatus, $acceptedCaptureStatuses, true)) {
            $order = $captureInquiry->data;
            break;
        }
        usleep(2_000_000);
    } while (microtime(true) < $captureDeadline);
    if (!in_array($order->orderStatus, $acceptedCaptureStatuses, true)) {
        throw new RuntimeException(
            "Capture did not enter an accepted state for paymentRequestId={$paymentRequestId}, "
                . "acquiringOrderId={$acquiringOrderId}; response="
                . E2EArtifactRecorder::formatResponse($captureInquiry),
        );
    }
}

fwrite(STDOUT, json_encode([
    'label' => $label,
    'paymentRequestId' => $paymentRequestId,
    'acquiringOrderId' => $acquiringOrderId,
    'orderStatus' => $order->orderStatus,
    'refundVerified' => $refund,
    'refundRequestId' => $state['refundRequestId'] ?? null,
    'captureVerified' => $capture,
    'captureStatusVerified' => $captureStatusOnly,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
