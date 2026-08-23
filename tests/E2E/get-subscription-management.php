<?php

declare(strict_types=1);

use Waffo\Model\Generated\SubscriptionManageResponse;
use Waffo\Tests\E2E\E2EArtifactRecorder;
use Waffo\Tests\E2E\E2EConfig;
use Waffo\Waffo;

require __DIR__ . '/../../vendor/autoload.php';

$statePath = getenv('WAFFO_E2E_BROWSER_STATE');
if (!is_string($statePath) || !is_file($statePath)) {
    throw new RuntimeException('WAFFO_E2E_BROWSER_STATE must point to an active subscription state');
}
$contents = file_get_contents($statePath);
if ($contents === false) {
    throw new RuntimeException('Unable to read subscription state');
}
$state = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
$response = (new Waffo(E2EConfig::load()->toSdkConfig()))->subscription()->manage([
    'subscriptionRequest' => (string) $state['subscriptionRequest'],
    'subscriptionId' => (string) $state['subscriptionId'],
]);
(new E2EArtifactRecorder())->recordResponse((string) $state['label'], 'manage-for-period-change', $response);
if (!$response->isSuccess()
    || !$response->data instanceof SubscriptionManageResponse
    || $response->data->managementUrl === null) {
    throw new RuntimeException(
        "Subscription manage failed: subscriptionRequest={$state['subscriptionRequest']}, "
            . "subscriptionId={$state['subscriptionId']}, response="
            . E2EArtifactRecorder::formatResponse($response),
    );
}
$state['managementUrl'] = $response->data->managementUrl;
file_put_contents($statePath, json_encode($state, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
fwrite(STDOUT, json_encode([
    'label' => $state['label'],
    'subscriptionRequest' => $state['subscriptionRequest'],
    'subscriptionId' => $state['subscriptionId'],
    'managementUrlPresent' => true,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
