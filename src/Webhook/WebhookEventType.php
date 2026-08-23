<?php

declare(strict_types=1);

namespace Waffo\Webhook;

enum WebhookEventType: string
{
    case Payment = 'PAYMENT_NOTIFICATION';
    case Refund = 'REFUND_NOTIFICATION';
    case SubscriptionStatus = 'SUBSCRIPTION_STATUS_NOTIFICATION';
    case SubscriptionPeriodChanged = 'SUBSCRIPTION_PERIOD_CHANGED_NOTIFICATION';
    case SubscriptionChange = 'SUBSCRIPTION_CHANGE_NOTIFICATION';
}
