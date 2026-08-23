<?php

declare(strict_types=1);

use Waffo\Model\Generated\SubscriptionChangeResponse;
use Waffo\Tests\E2E\E2EArtifactRecorder;
use Waffo\Tests\E2E\E2EConfig;
use Waffo\Tests\E2E\E2EFixtures;
use Waffo\Waffo;

require __DIR__ . '/../../vendor/autoload.php';

$statePath = getenv('WAFFO_E2E_BROWSER_STATE');
if (!is_string($statePath) || !is_file($statePath)) {
    throw new RuntimeException('WAFFO_E2E_BROWSER_STATE must point to an active subscription state');
}
$rawState = file_get_contents($statePath);
if ($rawState === false) {
    throw new RuntimeException('Unable to read active subscription state');
}
$state = json_decode($rawState, true, 512, JSON_THROW_ON_ERROR);
$label = 'subscription-change';
$originSubscriptionRequest = (string) $state['subscriptionRequest'];
$subscriptionRequest = E2EFixtures::uniqueId('php_sub_change');
$merchantSubscriptionId = E2EFixtures::uniqueId('PHP_SUB_CHANGE');
$waffo = new Waffo(E2EConfig::load()->toSdkConfig());
$artifacts = new E2EArtifactRecorder();
$response = $waffo->subscription()->change(E2EFixtures::subscriptionChangePayload(
    $originSubscriptionRequest,
    $subscriptionRequest,
    $merchantSubscriptionId,
    (string) $state['userId'],
    E2EFixtures::notifyUrl(),
));
$artifacts->recordResponse($label, 'change', $response);
if (!$response->isSuccess() || !$response->data instanceof SubscriptionChangeResponse) {
    throw new RuntimeException(
        "Subscription change failed: originSubscriptionRequest={$originSubscriptionRequest}, "
            . "subscriptionRequest={$subscriptionRequest}, response="
            . E2EArtifactRecorder::formatResponse($response),
    );
}
$checkoutUrl = E2EFixtures::actionUrl($response->data->subscriptionAction);
if ($response->data->subscriptionChangeStatus === 'AUTHORIZATION_REQUIRED' && $checkoutUrl === '') {
    throw new RuntimeException(
        'Subscription change requires authorization but no browser action was returned: '
            . "originSubscriptionRequest={$originSubscriptionRequest}, subscriptionRequest={$subscriptionRequest}, "
            . 'response=' . E2EArtifactRecorder::formatResponse($response),
    );
}

$changeState = [
    'label' => $label,
    'originSubscriptionRequest' => $originSubscriptionRequest,
    'originSubscriptionId' => (string) $state['subscriptionId'],
    'subscriptionRequest' => $subscriptionRequest,
    'merchantSubscriptionId' => $merchantSubscriptionId,
    'subscriptionId' => $response->data->subscriptionId,
    'subscriptionChangeStatus' => $response->data->subscriptionChangeStatus,
    'checkoutUrl' => $checkoutUrl,
];
file_put_contents($statePath, json_encode($changeState, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
fwrite(STDOUT, json_encode(array_diff_key($changeState, ['checkoutUrl' => true]), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
