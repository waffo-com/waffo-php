<?php

declare(strict_types=1);

use Waffo\Model\Generated\AcqSubscriptionCreateResponse;
use Waffo\Tests\E2E\E2EArtifactRecorder;
use Waffo\Tests\E2E\E2EConfig;
use Waffo\Tests\E2E\E2EFixtures;
use Waffo\Waffo;

require __DIR__ . '/../../vendor/autoload.php';

$label = $argv[1] ?? 'subscription-lifecycle';
$statePath = getenv('WAFFO_E2E_BROWSER_STATE');
if (!is_string($statePath) || $statePath === '') {
    throw new RuntimeException('WAFFO_E2E_BROWSER_STATE is required');
}

$waffo = new Waffo(E2EConfig::load()->toSdkConfig());
$artifacts = new E2EArtifactRecorder();
$subscriptionRequest = E2EFixtures::uniqueId('php_sub');
$merchantSubscriptionId = E2EFixtures::uniqueId('PHP_SUB');
$userId = E2EFixtures::uniqueId('php_sub_user');
$response = $waffo->subscription()->create(E2EFixtures::subscriptionPayload(
    $subscriptionRequest,
    $merchantSubscriptionId,
    E2EFixtures::notifyUrl(),
    $userId,
));
$artifacts->recordResponse($label, 'create', $response);
if (!$response->isSuccess() || !$response->data instanceof AcqSubscriptionCreateResponse) {
    throw new RuntimeException(
        "Subscription create failed: subscriptionRequest={$subscriptionRequest}, "
            . "merchantSubscriptionId={$merchantSubscriptionId}, response="
            . E2EArtifactRecorder::formatResponse($response),
    );
}
$checkoutUrl = E2EFixtures::actionUrl($response->data->subscriptionAction);
if ($checkoutUrl === '' || $response->data->subscriptionId === null) {
    throw new RuntimeException(
        'Subscription create response did not contain a browser action and subscription id: '
            . "subscriptionRequest={$subscriptionRequest}, merchantSubscriptionId={$merchantSubscriptionId}, "
            . 'response=' . E2EArtifactRecorder::formatResponse($response),
    );
}

$state = [
    'label' => $label,
    'subscriptionRequest' => $subscriptionRequest,
    'merchantSubscriptionId' => $merchantSubscriptionId,
    'subscriptionId' => $response->data->subscriptionId,
    'userId' => $userId,
    'checkoutUrl' => $checkoutUrl,
];
file_put_contents($statePath, json_encode($state, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
fwrite(STDOUT, json_encode(array_diff_key($state, ['checkoutUrl' => true]), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
