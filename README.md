# Waffo PHP SDK

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Build Status](https://img.shields.io/github/actions/workflow/status/waffo-com/waffo-sdk/ci.yml?branch=main)](https://github.com/waffo-com/waffo-sdk/actions)

**English** | [中文](README_CN.md)

## Latest Release Highlights

- **Node.js v2.5.0**: Added `subscription().update()` with `amount`, `trialPeriodAmount`, and `scheduledAmounts`, plus E2E coverage proving scheduled amounts take effect on the next billing period.
- **Java v2.0.0**: Changed subscription `cashierLanguage` request and response fields to `String`, while retaining and expanding `CashierLanguage` to the latest 22 convenience constants.
- **Go v1.4.0**: Added subscription update support and openapi-aligned scheduled amount fields across create, change, update, and inquiry flows.
- **Python v0.2.0b0**: Added `subscription.update()` and generated subscription models covering scheduled amount fields.
- **Shared reliability improvements**: Auto-injected capture timestamps, expanded nested schema field validation, and kept SDK types aligned with `openapi.json`.

Official Waffo PHP SDK for payments, refunds, subscriptions, configuration, wallet inquiry, and webhook verification.

## Introduction

Waffo SDK is the **official SDK collection** for [Waffo Payment Platform](https://www.waffo.com), providing one-stop global payment solutions for AI products, SaaS services, and more.

### Core Features

- **Global Payments**: Support for credit cards, debit cards, e-wallets, virtual accounts, and more payment methods covering mainstream global payment channels
- **Subscription Management**: Complete subscription lifecycle management with trial periods, recurring billing, and subscription upgrades/downgrades
- **Refund Processing**: Flexible full/partial refund capabilities with refund status tracking
- **Webhook Notifications**: Real-time payment result push notifications for payments, refunds, subscription status changes, and more
- **Security & Reliability**: PCI DSS certified, RSA signature verification, enforced TLS 1.2+ encryption

### Use Cases

| Scenario | Description |
|----------|-------------|
| **AI Products** | ChatGPT-like applications, AI writing tools, AI image generation with usage-based billing or subscriptions |
| **SaaS Services** | Enterprise software subscriptions, online collaboration tools, cloud services with periodic payments |
| **Content Platforms** | Membership subscriptions, paid content, tipping scenarios |

## Table of Contents

- [Latest Release Highlights](#latest-release-highlights)
- [System Architecture](#system-architecture)
- [Available SDKs](#available-sdks)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Configuration](#configuration)
- [API Usage](#api-usage)
  - [Order Management](#order-management)
  - [Subscription Management](#subscription-management)
  - [Subscription Change (Upgrade/Downgrade)](#subscription-change-upgradedowngrade)
  - [Refund Query](#refund-query)
  - [Merchant Configuration](#merchant-configuration)
  - [Wallet Address Inquiry (x402)](#wallet-address-inquiry-x402)
- [Webhook Handling](#webhook-handling)
- [Payment Method Types](#payment-method-types)
- [Advanced Configuration](#advanced-configuration)
- [Configuring DNS Cache TTL](#configuring-dns-cache-ttl)
- [Handling New API Fields (ExtraParams)](#handling-new-api-fields-extraparams)
- [Error Handling](#error-handling)
- [Development](#development)
- [SDK Development Guide](#sdk-development-guide)
- [Support](#support)
- [License](#license)

## System Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                         Waffo SDK                                   │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │                     Waffo (Entry Class)                      │   │
│  │  ┌────────────┐  ┌───────────────────┐  ┌──────────────┐    │   │
│  │  │  order()   │  │  subscription()   │  │   refund()   │    │   │
│  │  └────────────┘  └───────────────────┘  └──────────────┘    │   │
│  │  ┌────────────────────┐  ┌───────────────────────────────┐  │   │
│  │  │  merchantConfig()  │  │      payMethodConfig()        │  │   │
│  │  └────────────────────┘  └───────────────────────────────┘  │   │
│  └─────────────────────────────────────────────────────────────┘   │
│                                        │                           │
│                                        ▼                           │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │                    WaffoHttpClient (Core)                     │  │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────┐   │  │
│  │  │ RSA Signing │  │    JSON     │  │ Request/Response    │   │  │
│  │  │  (SHA256)   │  │Serialization│  │    Processing       │   │  │
│  │  └─────────────┘  └─────────────┘  └─────────────────────┘   │  │
│  └──────────────────────────────────────────────────────────────┘  │
│                                        │                           │
│                                        ▼                           │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │                 HttpTransport (Transport Layer)               │  │
│  │  ┌────────────────────────────────────────────────────────┐  │  │
│  │  │  Default Implementation (Built-in HTTP Client)          │  │  │
│  │  └────────────────────────────────────────────────────────┘  │  │
│  │  ┌────────────────────────────────────────────────────────┐  │  │
│  │  │  Extensible: Custom HTTP Client Implementations         │  │  │
│  │  └────────────────────────────────────────────────────────┘  │  │
│  └──────────────────────────────────────────────────────────────┘  │
│                                        │                           │
└────────────────────────────────────────│───────────────────────────┘
                                         ▼
                              ┌────────────────────┐
                              │   Waffo API Server │
                              │   SANDBOX / PROD   │
                              └────────────────────┘
```

### Module Description

| Module | Description |
|--------|-------------|
| `Waffo` | SDK main entry class, providing direct access to business resources |
| `WaffoHttpClient` | Core HTTP client handling signing, serialization, and communication |
| `HttpTransport` | Transport layer interface, supporting custom HTTP client implementations |
| `resources/*` | Business resource classes (orders, subscriptions, refunds, etc.) |
| `types/*` | Request/response type definitions |
| `utils/*` | Utility classes (RSA signing, webhook processing, etc.) |

## Available SDKs

| Language | Package | Status | Documentation |
|----------|---------|--------|---------------|
| Java | [com.waffo:waffo-java](https://github.com/waffo-com/waffo-java) | ✅ Stable | [English](https://github.com/waffo-com/waffo-java#readme) / [中文](https://github.com/waffo-com/waffo-java/blob/main/README.md) |
| Node.js/TypeScript | [@waffo/waffo-node](./packages/waffo-node) | ✅ Stable | [English](./packages/waffo-node/README.md) / [中文](./packages/waffo-node/README_CN.md) |
| Go | [waffo-go](./packages/waffo-go) | ✅ Stable | [English](./packages/waffo-go/README.md) / [中文](./packages/waffo-go/README_CN.md) |
| Python | [waffo](./packages/waffo-python) | ✅ Stable | [English](./packages/waffo-python/README.md) / [中文](./packages/waffo-python/README_CN.md) |
| PHP | [waffo/waffo-php](./packages/waffo-php) | ✅ Stable | [English](./packages/waffo-php/README.md) / [中文](./packages/waffo-php/README_CN.md) |
| Ruby | waffo-ruby | 🚧 Planned | - |

> **Note**: For detailed installation instructions, code examples, and framework integration, please refer to each SDK's README documentation.

## Requirements

### Common Requirements

- API Key (from Waffo Dashboard)
- RSA Key Pair (merchant private key + Waffo public key)
- Merchant ID

### Language-Specific Requirements

| Language | Version | Notes |
|----------|---------|-------|
| Java | 8+ (8, 11, 17, 21, 25) | Maven 3.6+ or Gradle 6.0+ |
| Node.js | 18+ | npm, yarn, or pnpm |
| Python | 3.9+ | pip |
| Go | 1.20+ | go modules |
| PHP | 8.0+ | Composer |

### Version Compatibility

## Installation

### PHP

> Requires PHP 8.0 or newer. PHP 8.0 and 8.1 are SDK-compatible but no longer receive upstream PHP security fixes; use a currently supported PHP runtime in production.

```bash
composer require waffo/waffo-php
```

## Quick Start

### 1. Initialize the SDK

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Waffo\Config\Environment;
use Waffo\Config\WaffoConfig;
use Waffo\Waffo;

$waffo = new Waffo(new WaffoConfig(
    apiKey: 'your-api-key',
    privateKey: 'your-base64-private-key',
    waffoPublicKey: 'waffo-base64-public-key',
    environment: Environment::Sandbox,
    merchantId: 'your-merchant-id',
));
```

### 2. Create a Payment Order

```php
<?php

$response = $waffo->order()->create(['paymentRequestId' => bin2hex(random_bytes(16)), 'merchantOrderId' => 'ORDER-123', 'orderCurrency' => 'HKD', 'orderAmount' => '100.00', 'orderDescription' => 'Test order', 'notifyUrl' => 'https://example.com/webhooks/waffo', 'userInfo' => ['userId' => 'user-123'], 'paymentInfo' => ['productName' => 'ONE_TIME_PAYMENT']]);

if ($response->isSuccess()) {
    var_dump($response->data);
}
```

### 3. Query Order Status

```php
<?php

$response = $waffo->order()->inquiry(['paymentRequestId' => 'payment-request-id']);

if ($response->isSuccess()) {
    var_dump($response->data);
}
```

## Configuration

### Full Configuration Options

```php
<?php

use Waffo\Config\Environment;
use Waffo\Config\WaffoConfig;

$config = new WaffoConfig('api-key', 'private-key', 'public-key', Environment::Sandbox, 'merchant-id', 10_000, 30_000);
```

### Environment Variables

All SDKs support configuration via environment variables:

```bash
# Set environment variables
export WAFFO_API_KEY=your-api-key
export WAFFO_PRIVATE_KEY=your-private-key
export WAFFO_PUBLIC_KEY=waffo-public-key       # Waffo public key
export WAFFO_ENVIRONMENT=SANDBOX               # Required: SANDBOX or PRODUCTION
export WAFFO_MERCHANT_ID=your-merchant-id      # Required
```

```bash
export WAFFO_API_KEY="your-api-key"
export WAFFO_PRIVATE_KEY="your-base64-private-key"
export WAFFO_PUBLIC_KEY="waffo-base64-public-key"
export WAFFO_MERCHANT_ID="your-merchant-id"
export WAFFO_ENVIRONMENT="sandbox"
```

### Environment URLs

| Environment | Base URL | Description |
|-------------|----------|-------------|
| `SANDBOX` | `https://api-sandbox.waffo.com` | Test environment |
| `PRODUCTION` | `https://api.waffo.com` | Production environment |

> **Important**: Environment must be explicitly specified. SDKs do not default to any environment to prevent accidental requests to wrong environments.

### Request-Level Configuration

```php
<?php

use Waffo\Config\RequestOptions;

$response = $waffo->order()->inquiry(['paymentRequestId' => 'payment-request-id'], new RequestOptions(5_000, 15_000));
```

### Framework Integration

#### PHP Framework Integration

All PHP frameworks must pass the unmodified request body and `X-SIGNATURE` header to `WebhookHandler::handleWebhook()`, then return HTTP 200 with the signed response body and `X-SIGNATURE` produced by the SDK.

**Laravel Integration:**

```php
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Waffo\Waffo;

Route::post('/webhook', function (Request $request, Waffo $waffo) {
    $result = $waffo->webhook()->handleWebhook(
        $request->getContent(),
        (string) $request->header('X-SIGNATURE', ''),
    );

    return response($result->responseBody, 200)
        ->header('Content-Type', 'application/json')
        ->header('X-SIGNATURE', $result->responseSignature);
});
```

**Symfony Integration:**

```php
<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Waffo\Waffo;

function waffoWebhook(Request $request, Waffo $waffo): Response
{
    $result = $waffo->webhook()->handleWebhook(
        $request->getContent(),
        (string) $request->headers->get('X-SIGNATURE', ''),
    );

    return new Response($result->responseBody, 200, [
        'Content-Type' => 'application/json',
        'X-SIGNATURE' => $result->responseSignature,
    ]);
}
```

**Slim Integration:**

```php
<?php

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

$app->post('/webhook', function (
    ServerRequestInterface $request,
    ResponseInterface $response,
) use ($waffo): ResponseInterface {
    $result = $waffo->webhook()->handleWebhook(
        (string) $request->getBody(),
        $request->getHeaderLine('X-SIGNATURE'),
    );
    $response->getBody()->write($result->responseBody);

    return $response
        ->withStatus(200)
        ->withHeader('Content-Type', 'application/json')
        ->withHeader('X-SIGNATURE', $result->responseSignature);
});
```

## API Usage

### Order Management

#### Create Order

```php
<?php

$response = $waffo->order()->create(['paymentRequestId' => 'request-1', 'merchantOrderId' => 'order-1', 'orderCurrency' => 'USD', 'orderAmount' => '10.00', 'orderDescription' => 'Example', 'notifyUrl' => 'https://example.com/webhooks/waffo', 'userInfo' => ['userId' => 'user-1'], 'paymentInfo' => ['productName' => 'ONE_TIME_PAYMENT']]);

if ($response->isSuccess()) {
    var_dump($response->data);
}
```

#### Query Order

```php
<?php

$response = $waffo->order()->inquiry(['acquiringOrderId' => 'A123']);

if ($response->isSuccess()) {
    var_dump($response->data);
}
```

#### Cancel Order

```php
<?php

$response = $waffo->order()->cancel(['paymentRequestId' => 'request-1']);

if ($response->isSuccess()) {
    var_dump($response->data);
}
```

#### Refund Order

```php
<?php

$response = $waffo->order()->refund(['refundRequestId' => 'refund-1', 'acquiringOrderId' => 'A123', 'refundAmount' => '10.00', 'refundReason' => 'Customer request']);

if ($response->isSuccess()) {
    var_dump($response->data);
}
```

#### Capture Order

Capture a pre-authorized payment (manual capture flow). The capture amount must not exceed the authorized amount.

```php
<?php

$response = $waffo->order()->capture(['paymentRequestId' => 'request-1', 'acquiringOrderId' => 'A123', 'captureAmount' => '10.00']);

if ($response->isSuccess()) {
    var_dump($response->data);
}
```

### Subscription Management

#### Create Subscription

```php
<?php

$response = $waffo->subscription()->create(['subscriptionRequest' => 'sub-request-1', 'merchantSubscriptionId' => 'sub-1', 'currency' => 'USD', 'amount' => '9.99', 'notifyUrl' => 'https://example.com/webhooks/waffo', 'productInfo' => ['description' => 'Monthly plan', 'periodType' => 'MONTHLY', 'periodInterval' => '1'], 'userInfo' => ['userId' => 'user-1', 'userEmail' => 'user-1@example.com'], 'paymentInfo' => ['productName' => 'SUBSCRIPTION']]);

if ($response->isSuccess()) {
    var_dump($response->data);
}
```

#### Query Subscription

```php
<?php

$response = $waffo->subscription()->inquiry(['subscriptionRequest' => 'sub-request-1']);

if ($response->isSuccess()) {
    var_dump($response->data);
}
```

#### Cancel Subscription

```php
<?php

$response = $waffo->subscription()->cancel(['subscriptionId' => 'subscription-id']);

if ($response->isSuccess()) {
    var_dump($response->data);
}
```

#### Get Subscription Management URL

```php
<?php

$response = $waffo->subscription()->manage(['subscriptionId' => 'subscription-id']);

if ($response->isSuccess()) {
    var_dump($response->data);
}
```

#### Update Subscription

Update the billing amounts of an active subscription without re-authorization. Provide `subscriptionRequest` or `subscriptionId`. To charge a seat add-on immediately, include `topupInfo`; the updated billing amounts take effect from the next billing period.

```php
<?php

$response = $waffo->subscription()->update([
    'subscriptionId' => 'subscription_id',
    'amount' => '129.00',
    'topupInfo' => [
        'topupRequest' => 'mimo-topup-001',
        'topupAmount' => '30.00',
        'description' => 'Seat add-on 3 seats',
        'orderExpiredAt' => '2030-01-01T00:00:00Z',
    ],
]);

if ($response->isSuccess()) {
    echo 'Top-up Order: ' . $response->data?->topupInfo?->acquiringOrderId . PHP_EOL;
    echo 'Top-up Status: ' . $response->data?->topupInfo?->topupStatus . PHP_EOL;
}
```

### Subscription Change (Upgrade/Downgrade)

Change an existing subscription to a new plan (upgrade or downgrade).

#### Change Subscription

```php
<?php

$response = $waffo->subscription()->change(['subscriptionRequest' => 'new-sub-request', 'originSubscriptionRequest' => 'old-sub-request', 'remainingAmount' => '50.00', 'currency' => 'USD', 'notifyUrl' => 'https://example.com/webhooks/waffo', 'productInfoList' => [['description' => 'Annual plan', 'periodType' => 'MONTHLY', 'periodInterval' => '12', 'amount' => '99.00']], 'userInfo' => ['userId' => 'user-1', 'userEmail' => 'user-1@example.com'], 'goodsInfo' => ['goodsId' => 'annual', 'goodsName' => 'Annual plan'], 'paymentInfo' => ['productName' => 'SUBSCRIPTION']]);

if ($response->isSuccess()) {
    var_dump($response->data);
}
```

#### Subscription Change Status Values

| Status | Description |
|--------|-------------|
| `IN_PROGRESS` | Change is being processed |
| `AUTHORIZATION_REQUIRED` | User needs to authorize the change (redirect to webUrl) |
| `SUCCESS` | Change completed successfully |
| `CLOSED` | Change was closed (timeout or failed) |

#### Query Subscription Change Status

```php
<?php

$response = $waffo->subscription()->changeInquiry(['subscriptionRequest' => 'new-sub-request', 'originSubscriptionRequest' => 'old-sub-request']);

if ($response->isSuccess()) {
    var_dump($response->data);
}
```

### Refund Query

```php
<?php

$response = $waffo->refund()->inquiry(['refundRequestId' => 'refund-1']);

if ($response->isSuccess()) {
    var_dump($response->data);
}
```

### Merchant Configuration

#### Query Merchant Configuration

```php
<?php

$response = $waffo->merchantConfig()->inquiry(['merchantId' => 'merchant-id']);

if ($response->isSuccess()) {
    var_dump($response->data);
}
```

#### Query Available Payment Methods

The `payMethodDetails` response includes the payment method category, supported currency, order amount limits, and refund policy. `currentStatus` can be absent when real-time availability is temporarily unavailable.

```php
<?php

$response = $waffo->payMethodConfig()->inquiry(['merchantId' => 'merchant-id']);

if ($response->isSuccess()) {
    var_dump($response->data);
}
```

### Wallet Address Inquiry (x402)

Inquire the on-chain stablecoin receiving address for an x402 payment. The response returns the target network, the on-chain token contract, and the Waffo platform wallet address to send funds to. Phase 1 supports `USDC`.

```php
<?php

$response = $waffo->wallet()->inquiry(['paymentRequestId' => 'request-1', 'orderCurrency' => 'USDC']);

if ($response->isSuccess()) {
    var_dump($response->data);
}
```

#### Wallet Inquiry Response Fields

| Field | Description |
|-------|-------------|
| `network` | CAIP-2 network id (Phase 1: `eip155:8453`, Base mainnet) |
| `asset` | On-chain token contract address for the network + currency |
| `payTo` | Waffo platform receiving wallet address (send funds here) |

## Webhook Handling

Waffo pushes payment results, refund results, subscription status changes, and more via webhooks.

### Webhook Handler Example

```php
<?php

$result = $waffo->webhook()
    ->onPayment(static function (array $event): void {})
    ->onRefund(static function (array $event): void {})
    ->onSubscriptionStatus(static function (array $event): void {})
    ->onSubscriptionPeriodChanged(static function (array $event): void {})
    ->onSubscriptionChange(static function (array $event): void {})
    ->handleWebhook(file_get_contents('php://input'), $_SERVER['HTTP_X_SIGNATURE'] ?? null);
header('X-SIGNATURE: ' . $result->responseSignature);
echo $result->responseBody;
```

### Webhook Notification Types

| Event Type | Handler Method | Description |
|------------|----------------|-------------|
| `PAYMENT_NOTIFICATION` | `onPayment()` | Payment result notification (triggered on every payment attempt, including retries) |
| `REFUND_NOTIFICATION` | `onRefund()` | Refund result notification |
| `SUBSCRIPTION_STATUS_NOTIFICATION` | `onSubscriptionStatus()` | Subscription status change notification (triggered when subscription main record status changes) |
| `SUBSCRIPTION_PERIOD_CHANGED_NOTIFICATION` | `onSubscriptionPeriodChanged()` | Subscription period change notification (final result of each period) |
| `SUBSCRIPTION_CHANGE_NOTIFICATION` | `onSubscriptionChange()` | Subscription change (upgrade/downgrade) result notification |

### Subscription Notification Types Explained

| Notification Type | Trigger Condition | Scope | Includes Retry Events | Typical Use Case |
|-------------------|-------------------|-------|----------------------|------------------|
| `SUBSCRIPTION_STATUS_NOTIFICATION` | Subscription main record status changes | Subscription level | No | Track subscription lifecycle: first payment success activation (ACTIVE), cancellation (MERCHANT_CANCELLED, CHANNEL_CANCELLED), first payment failure close (CLOSE), etc. |
| `SUBSCRIPTION_PERIOD_CHANGED_NOTIFICATION` | Subscription period reaches final state | Period level | No (only final result) | Only need final result of each period, no intermediate retry events |
| `SUBSCRIPTION_CHANGE_NOTIFICATION` | Subscription change (upgrade/downgrade) completes | Change request level | No (only final result) | Track subscription change results: SUCCESS or CLOSED |
| `PAYMENT_NOTIFICATION` | Every payment order | Payment order level | Yes (includes all retries) | Need complete details of every payment attempt, including failure reasons, timestamps, retry details |

> **Selection Guide**:
> - If you only care about subscription activation/cancellation, use `SUBSCRIPTION_STATUS_NOTIFICATION`
> - If you only care about final renewal result of each period, use `SUBSCRIPTION_PERIOD_CHANGED_NOTIFICATION`
> - If you only care about subscription change (upgrade/downgrade) final result, use `SUBSCRIPTION_CHANGE_NOTIFICATION`
> - If you need to track every payment attempt (including retries), use `PAYMENT_NOTIFICATION`

> **Subscription Payment Note**: Each period's payment (including first payment and renewals) triggers `PAYMENT_NOTIFICATION` events. You can get subscription-related info (subscriptionId, period, etc.) from `subscriptionInfo`.

> **Subscription Change (Upgrade/Downgrade) Webhook Note**:
> When a subscription change is processed, the following notifications are triggered:
> - `SUBSCRIPTION_CHANGE_NOTIFICATION`: When subscription change completes (SUCCESS or CLOSED)
> - `SUBSCRIPTION_STATUS_NOTIFICATION`: When original subscription status changes to `MERCHANT_CANCELLED`
> - `SUBSCRIPTION_STATUS_NOTIFICATION`: When new subscription status changes to `ACTIVE`
> - `PAYMENT_NOTIFICATION`: If upgrade requires additional payment (price difference)

### Webhook Notification Payload Examples

The following examples show the actual payload structure for each notification type.

#### PAYMENT_NOTIFICATION

```json
{
  "eventType": "PAYMENT_NOTIFICATION",
  "result": {
    "acquiringOrderId": "A2026xxxxxxxxxxxxxxxxxxxx",
    "orderStatus": "PAY_SUCCESS",
    "orderAmount": "109.00",
    "orderCurrency": "USD",
    "finalDealAmount": "109.00",
    "userCurrency": "USD",
    "orderDescription": "Sample Product",
    "orderRequestedAt": "2026-02-28T10:05:58.000Z",
    "orderCompletedAt": "2026-02-28T10:06:10.000Z",
    "orderUpdatedAt": "2026-02-28T10:06:10.000Z",
    "refundExpiryAt": "2026-08-26T23:59:59.999Z",
    "userInfo": {
      "userId": "user@example.com",
      "userEmail": "user@example.com"
    },
    "merchantInfo": {
      "merchantId": "YOUR_MERCHANT_ID"
    },
    "goodsInfo": {
      "goodsId": "goods_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
      "goodsName": "Sample Product"
    },
    "addressInfo": {},
    "paymentInfo": {
      "productName": "SUBSCRIPTION",
      "payMethodType": "CREDITCARD",
      "payMethodName": "CC_VISA",
      "payMethodProperties": "{\"cardToken\":\"CARD_TOKEN_XXXXXXXXXXXXXXXXXXXX\",\"cardTransactionType\":\"CIT\"}",
      "payMethodResponse": "{\"maskCardData\":\"XXXX42****XX4242\"}"
    },
    "subscriptionInfo": {
      "subscriptionId": "SC2026xxxxxxxxxxxxxxxxxxxxxxxxxx",
      "subscriptionRequest": "sub_req_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
      "merchantRequest": "sub_req_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
      "period": "1"
    },
    "cancelRedirectUrl": "https://YOUR_DOMAIN/checkout/YOUR_CHECKOUT_ID"
  }
}
```

For x402 stablecoin payments, `PAYMENT_NOTIFICATION.result` also includes `x402Info` with `x402Request`, `paymentSignatureHeader`, and `x402Response`.

#### SUBSCRIPTION_STATUS_NOTIFICATION / SUBSCRIPTION_PERIOD_CHANGED_NOTIFICATION

These two notification types share the same payload structure:

```json
{
  "eventType": "SUBSCRIPTION_STATUS_NOTIFICATION",
  "result": {
    "subscriptionId": "SC2026xxxxxxxxxxxxxxxxxxxxxxxxxx",
    "subscriptionRequest": "sub_req_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
    "merchantSubscriptionId": "sub_req_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
    "subscriptionStatus": "ACTIVE",
    "currency": "USD",
    "userCurrency": "USD",
    "amount": "109.00",
    "requestedAt": "2026-02-28T10:05:58.000Z",
    "updatedAt": "2026-02-28T10:06:10.000Z",
    "userInfo": {
      "userId": "user@example.com",
      "userEmail": "user@example.com"
    },
    "merchantInfo": {
      "merchantId": "YOUR_MERCHANT_ID"
    },
    "goodsInfo": {
      "goodsId": "goods_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
      "goodsName": "Sample Product"
    },
    "productInfo": {
      "periodType": "MONTHLY",
      "periodInterval": "1",
      "currentPeriod": "1",
      "startDateTime": "2026-02-28T10:06:10.000Z",
      "nextPaymentDateTime": "2026-03-28T10:06:10.000Z",
      "description": "Sample Product"
    },
    "paymentInfo": {
      "productName": "SUBSCRIPTION",
      "payMethodType": "CREDITCARD",
      "payMethodName": "CC_VISA",
      "payMethodProperties": "{}"
    },
    "paymentDetails": [
      {
        "period": "1",
        "acquiringOrderId": "A2026xxxxxxxxxxxxxxxxxxxx",
        "orderAmount": "109.00",
        "orderCurrency": "USD",
        "orderStatus": "PAY_SUCCESS",
        "orderUpdatedAt": "2026-02-28T10:06:10.000Z"
      }
    ]
  }
}
```

## Payment Method Types

### payMethodType Reference

| Type | Description | Example payMethodName |
|------|-------------|----------------------|
| `CREDITCARD` | Credit Card | CC_VISA, CC_MASTERCARD, CC_AMEX, CC_JCB, etc. |
| `DEBITCARD` | Debit Card | DC_VISA, DC_MASTERCARD, DC_ELO, etc. |
| `EWALLET` | E-Wallet | GCASH, DANA, PROMPTPAY, GRABPAY, etc. |
| `VA` | Virtual Account | BCA, BNI, BRI, MANDIRI, etc. |
| `APPLEPAY` | Apple Pay | APPLEPAY |
| `GOOGLEPAY` | Google Pay | GOOGLEPAY |

### Usage Examples

Specify a type and let the user choose on the checkout page:

```json
{"paymentInfo": {"payMethodType": "CREDITCARD"}}
```

Specify an exact payment method:

```json
{"paymentInfo": {"payMethodType": "CREDITCARD", "payMethodName": "CC_VISA"}}
```

Combine multiple types:

```json
{"paymentInfo": {"payMethodType": "CREDITCARD,DEBITCARD"}}
```

Select an e-wallet channel:

```json
{"paymentInfo": {"payMethodType": "EWALLET", "payMethodName": "GCASH"}}
```

> **Note**: For available `ProductName`, `PayMethodType`, `PayMethodName` values, merchants can log in to [Waffo Portal](https://dashboard.waffo.com) to view contracted payment methods (Home → Service → Pay-in).

## Advanced Configuration

### Custom HTTP Transport

SDKs use built-in HTTP clients by default. For connection pooling or advanced features, you can implement custom transport.

```php
<?php

use Waffo\Http\HttpRequest;
use Waffo\Http\HttpResponse;
use Waffo\Http\HttpTransport;

final class MerchantTransport implements HttpTransport
{
    public function send(HttpRequest $request): HttpResponse
    {
        return new HttpResponse(200, [], '{"code":"0","data":{}}');
    }
}
```

> **Security Note**: When implementing custom HTTP transport, ensure TLS 1.2 or higher is enforced. See "TLS Security Configuration" section below.

### TLS Security Configuration

SDKs enforce **TLS 1.2 or higher** by default for all HTTPS communication, complying with PCI DSS security standards.

#### Default Transport

Default HTTP transport implementations automatically configure TLS 1.2+:
- All HTTPS connections only allow TLS 1.2 and TLS 1.3 protocols
- Insecure legacy protocols (SSLv3, TLS 1.0, TLS 1.1) are automatically disabled
- No additional configuration needed, works out of the box

#### Custom Transport TLS Configuration

If using custom HTTP transport, ensure TLS 1.2+ is configured:

### Debug Logging

Enable debug logging to troubleshoot issues during development.

### Timeout Configuration Recommendations

| Operation Type | Connect Timeout | Read Timeout | Notes |
|----------------|-----------------|--------------|-------|
| Create Order | 5s | 30s | Recommended |
| Create Subscription | 5s | 30s | Recommended |
| Refund Operation | 5s | 30s | Recommended |
| Query Operations | 5s | 15s | Can be shorter |

### Connection Pool Recommendations

| Scenario | Max Connections | Max Per Route | Notes |
|----------|-----------------|---------------|-------|
| Low Traffic (< 10 QPS) | 20 | 10 | Default config sufficient |
| Medium Traffic (10-100 QPS) | 50 | 20 | Consider using OkHttp |
| High Traffic (> 100 QPS) | 100-200 | 50 | Consider Apache HttpClient |

### Instance Reuse

SDK instances are **thread-safe**. Recommended to use as singleton in your application:

```php
<?php

$waffo = Waffo\Waffo::fromEnvironment();
$order = $waffo->order()->inquiry(['paymentRequestId' => 'request-1']);
$refund = $waffo->refund()->inquiry(['refundRequestId' => 'refund-1']);
```

### Configuring DNS Cache TTL

We cannot guarantee that the IP address of the Waffo API will be static. To ensure high availability, Waffo employs a multi-gateway architecture with automatic failover — if one gateway provider experiences an outage, DNS records are updated to route traffic to a healthy gateway. Ensure that your DNS cache configuration does not cache DNS entries for too long — otherwise, during a failover event, your application will continue sending requests to the unavailable gateway until the cache expires or your process restarts.

**Recommended TTL: 60 seconds or less.**

#### Infrastructure / Gateway Layer

If your requests to Waffo API go through a reverse proxy or load balancer, check its DNS caching behavior as well — this is configured independently from your application code:

- **Nginx**: By default, Nginx resolves DNS **only at startup** and caches the result forever. You must explicitly configure a `resolver` with a TTL:
  ```nginx
  resolver 8.8.8.8 valid=60s;
  set $backend "api.waffo.com";
  proxy_pass https://$backend;
  ```
  Note: Using a variable in `proxy_pass` is required to trigger re-resolution; a hardcoded hostname is resolved only once.

- **AWS ALB / Cloud Load Balancers**: Most cloud load balancers handle DNS re-resolution automatically. Verify your provider's documentation to confirm.

- **CDN / API Gateways**: If you route traffic through Cloudflare, AWS API Gateway, or similar services, ensure their DNS TTL settings align with your requirements.

## Handling New API Fields (ExtraParams)

When Waffo API adds new fields that are not yet defined in the SDK, you can use the ExtraParams feature to access these fields without waiting for an SDK update.

### Reading Unknown Fields from Responses

```php
<?php

$response = $waffo->order()->inquiry(['paymentRequestId' => 'request-1']);
$futureField = $response->data?->extraFields()['futureField'] ?? null;
```

### Sending Extra Fields in Requests

```php
<?php

$response = $waffo->order()->create(['paymentRequestId' => 'request-1', 'merchantOrderId' => 'order-1', 'orderCurrency' => 'USD', 'orderAmount' => '10.00', 'orderDescription' => 'Example', 'notifyUrl' => 'https://example.com/webhooks/waffo', 'userInfo' => ['userId' => 'user-1'], 'paymentInfo' => ['productName' => 'ONE_TIME_PAYMENT'], 'futureField' => 'forward-compatible']);

if ($response->isSuccess()) {
    var_dump($response->data);
}
```

### Important Notes

> **Upgrade SDK Promptly**
>
> ExtraParams is designed as a **temporary solution** for accessing new API fields before SDK updates.
>
> **Best Practices:**
> 1. Check SDK release notes regularly for new field support
> 2. Once SDK officially supports the field, migrate from `getExtraParam("field")` to the official getter (e.g., `getField()`)
> 3. The SDK logs a warning when you use `getExtraParam()` on officially supported fields
>
> **Why migrate?**
> - Official getters provide type safety
> - Better IDE auto-completion and documentation
> - Reduced risk of typos in field names

## Error Handling

### Error Handling Pattern

SDKs use a hybrid error handling approach:
- **Business errors**: Returned via `ApiResponse`, check with `response.isSuccess()`
- **Unknown status exceptions**: Only for **write operations** (may affect funds or status), network timeout or server returning E0001 error code throws `WaffoUnknownStatusError` (or language-specific equivalent)

### Methods That Throw Unknown Status Exception

Only these methods that may affect funds or status throw `WaffoUnknownStatusError`:

| Method | Description |
|--------|-------------|
| `order().create()` | Create order, may initiate payment |
| `order().refund()` | Refund, may cause fund changes |
| `order().cancel()` | Cancel order, affects order status |
| `subscription().create()` | Create subscription, may cause initial charge |
| `subscription().cancel()` | Cancel subscription, affects subscription status |
| `subscription().change()` | Change subscription, may cause charge for upgrade |

**Query methods do not throw this exception** (e.g., `inquiry()`), because query operations can be safely retried without affecting funds or status.

### WaffoUnknownStatusError Handling

> ⚠️ **IMPORTANT WARNING**
>
> When `WaffoUnknownStatusError` is caught, it means **the operation result is uncertain**.
>
> **DO NOT directly close the order or assume payment failed!** The user may have already completed payment.
>
> **Correct handling:**
> 1. Call `waffo.order().inquiry()` to query actual order status
> 2. Or wait for Waffo webhook callback notification
> 3. Use Waffo's returned order status as the final authority

```php
<?php

use Waffo\Exception\WaffoUnknownStatusError;

try {
    $response = $waffo->order()->create($params);
} catch (WaffoUnknownStatusError $error) {
    $response = $waffo->order()->inquiry(['paymentRequestId' => $params['paymentRequestId']]);
}
```

### WaffoUnknownStatusError Trigger Scenarios

| Scenario | Description |
|----------|-------------|
| Network Timeout | Request timeout, cannot determine if server received request |
| Connection Failed | Network connection failed, cannot determine server status |
| E0001 Error Code | Server returned E0001, indicating processing status unknown |

### Error Code Classification

Error codes are classified by first letter:

| Prefix | Category | Description |
|--------|----------|-------------|
| **S** | SDK Internal Error | SDK client internal error such as network timeout, signing failure, etc. |
| **A** | Merchant Related | Parameter, signature, permission, contract issues on merchant side |
| **B** | User Related | User status, balance, authorization issues |
| **C** | System Related | Waffo system or payment channel issues |
| **D** | Risk Related | Risk control rejection |
| **E** | Unknown Status | Server returned unknown status |

### Complete Error Code Table

#### SDK Internal Errors (Sxxxx)

| Code | Description | Exception Type | Handling Suggestion |
|------|-------------|----------------|---------------------|
| `S0001` | Network Error | `WaffoUnknownStatusError` | **Status unknown**, need to query order to confirm |
| `S0002` | Invalid Public Key | `WaffoError` | Check if public key is valid Base64 encoded X509 format |
| `S0003` | RSA Signing Failed | `WaffoError` | Check if private key format is correct |
| `S0004` | Response Signature Verification Failed | `ApiResponse.error()` | Check Waffo public key config, contact Waffo |
| `S0005` | Request Serialization Failed | `ApiResponse.error()` | Check request parameter format |
| `S0006` | SDK Unknown Error | `ApiResponse.error()` | Check logs, contact technical support |
| `S0007` | Invalid Private Key | `WaffoError` | Check if private key is valid Base64 encoded PKCS8 format |

> **Important**: `S0001` and `E0001` (returned by server) indicate **unknown status**. Do not close order directly! Should call query API or wait for webhook to confirm actual status.

#### Merchant Related Errors (Axxxxx)

| Code | Description | HTTP Status |
|------|-------------|-------------|
| `0` | Success | 200 |
| `A0001` | Invalid API Key | 401 |
| `A0002` | Invalid Signature | 401 |
| `A0003` | Parameter Validation Failed | 400 |
| `A0004` | Insufficient Permission | 401 |
| `A0005` | Merchant Limit Exceeded | 400 |
| `A0006` | Merchant Status Abnormal | 400 |
| `A0007` | Unsupported Transaction Currency | 400 |
| `A0008` | Transaction Amount Exceeded | 400 |
| `A0009` | Order Not Found | 400 |
| `A0010` | Merchant Contract Does Not Allow This Operation | 400 |
| `A0011` | Idempotent Parameter Mismatch | 400 |
| `A0012` | Merchant Account Insufficient Balance | 400 |
| `A0013` | Order Already Paid, Cannot Cancel | 400 |
| `A0014` | Refund Rules Do Not Allow Refund | 400 |
| `A0015` | Payment Channel Does Not Support Cancel | 400 |
| `A0016` | Payment Channel Rejected Cancel | 400 |
| `A0017` | Payment Channel Does Not Support Refund | 400 |
| `A0018` | Payment Method Does Not Match Merchant Contract | 400 |
| `A0019` | Cannot Refund Due to Chargeback Dispute | 400 |
| `A0020` | Payment Amount Exceeds Single Transaction Limit | 400 |
| `A0021` | Cumulative Payment Amount Exceeds Daily Limit | 400 |
| `A0022` | Multiple Products Exist, Need to Specify Product Name | 400 |
| `A0023` | Token Expired, Cannot Create Order | 400 |
| `A0024` | Exchange Rate Expired, Cannot Process Order | 400 |
| `A0026` | Unsupported Checkout Language | 400 |
| `A0027` | Refund Count Reached Limit (50 times) | 400 |
| `A0029` | Invalid Card Data Provided by Merchant | 400 |
| `A0030` | Card BIN Not Found | 400 |
| `A0031` | Unsupported Card Scheme or Card Type | 400 |
| `A0032` | Invalid Payment Token Data | 400 |
| `A0033` | Multiple Payment Methods with Same Name, Need to Specify Country | 400 |
| `A0034` | Order Expiry Time Provided by Merchant Has Passed | 400 |
| `A0035` | Current Order Does Not Support Capture Operation | 400 |
| `A0036` | Current Order Status Does Not Allow Capture Operation | 400 |
| `A0037` | User Payment Token Invalid or Expired | 400 |
| `A0038` | MIT Transaction Requires Verified User Payment Token | 400 |
| `A0039` | Order Already Refunded by Chargeback Prevention Service | 400 |
| `A0040` | Order Cannot Be Created Concurrently | 400 |
| `A0045` | MIT Transaction Cannot Process, tokenId Status Unverified | 400 |

#### User Related Errors (Bxxxxx)

| Code | Description | HTTP Status |
|------|-------------|-------------|
| `B0001` | User Status Abnormal | 400 |
| `B0002` | User Limit Exceeded | 400 |
| `B0003` | User Insufficient Balance | 400 |
| `B0004` | User Did Not Pay Within Timeout | 400 |
| `B0005` | User Authorization Failed | 400 |
| `B0006` | Invalid Phone Number | 400 |
| `B0007` | Invalid Email Format | 400 |

#### System Related Errors (Cxxxxx)

| Code | Description | HTTP Status |
|------|-------------|-------------|
| `C0001` | System Error | 500 |
| `C0002` | Merchant Contract Invalid | 500 |
| `C0003` | Order Status Invalid, Cannot Continue Processing | 500 |
| `C0004` | Order Information Mismatch | 500 |
| `C0005` | Payment Channel Rejected | 503 |
| `C0006` | Payment Channel Error | 503 |
| `C0007` | Payment Channel Under Maintenance | 503 |

#### Risk Related Errors (Dxxxxx)

| Code | Description | HTTP Status |
|------|-------------|-------------|
| `D0001` | Risk Control Rejected | 406 |

#### Unknown Status Errors (Exxxxx)

| Code | Description | HTTP Status |
|------|-------------|-------------|
| `E0001` | Unknown Status (Need to query or wait for callback) | 500 |

> **Note**: When receiving `E0001` error code, it indicates transaction status is unknown. **Do not close order directly**, should call query API to confirm actual status, or wait for webhook callback notification.

## Development

### Repository Structure

```
waffo-sdk/
├── openapi.json              # API specification (single source of truth)
├── sdk-spec/                 # Shared specifications for all SDKs
│   ├── RSA_SIGNING.md        # RSA signing specification
│   ├── HTTP_CLIENT.md        # HTTP client specification
│   ├── WEBHOOK_HANDLER.md    # Webhook handling specification
│   ├── ERROR_HANDLING.md     # Error handling specification
│   ├── NEW_LANGUAGE_GUIDE.md # Guide for adding new language SDK
│   ├── AGENT_INSTRUCTIONS.md # AI agent instructions
│   └── test-vectors/         # Cross-language test vectors
├── packages/
│   ├── waffo-node/           # Node.js SDK
│   ├── waffo-python/         # Python SDK
│   └── ...
├── scripts/
│   ├── generate-types.sh     # Type generation script
│   └── run-cross-lang-tests.sh
└── .github/workflows/
```

### Prerequisites

- Node.js 18+
- pnpm (for workspace management)

### Setup

```bash
# Install dependencies
pnpm install

# Generate types for all SDKs
./scripts/generate-types.sh

# Run all tests
./scripts/run-cross-lang-tests.sh
```

### Generate RSA Key Pair

All SDKs provide utility methods to generate RSA key pairs:

```php
<?php

$keys = Waffo\Waffo::generateKeyPair();
echo $keys['privateKey'] . PHP_EOL;
echo $keys['publicKey'] . PHP_EOL;
```

### API Specification

All SDKs are generated from `openapi.json`. When the API changes:

1. Update `openapi.json`
2. Run `./scripts/generate-types.sh`
3. Run tests for all SDKs
4. Release new versions

## SDK Development Guide

This section provides guidelines for developing new language SDKs or maintaining existing ones.

### Documentation Synchronization Rules

> **IMPORTANT**: All language-specific SDK READMEs MUST be kept in sync with this monorepo README.

When this README is updated, the changes should be automatically synchronized to all language SDKs:

| Source | Targets |
|--------|---------|
| `waffo-sdk/README.md` | `packages/waffo-node/README.md`, `waffo-java/README.md`, etc. |

**Synchronization Workflow:**

1. **Single Source of Truth**: This monorepo README serves as the master documentation
2. **Language Adaptation**: Each SDK README adapts the content for its specific language (code examples, installation, framework integration)
3. **Required Sections**: All SDKs MUST include the following sections from this README:
   - Introduction & Core Features
   - System Architecture
   - Configuration (adapted for language)
   - API Usage (all endpoints with language-specific examples)
   - Webhook Handling
   - Payment Method Types
   - Error Handling (complete error code table)
   - Support & License

4. **Update Process**:
   ```bash
   # When monorepo README changes, update all SDK READMEs
   ./scripts/sync-readme.sh
   ```

5. **CI/CD Validation**: PR checks should verify README consistency across SDKs

**What to Synchronize:**
- Error code tables (MUST be identical)
- Payment method types table
- Webhook notification types
- API endpoint descriptions
- Configuration options
- Security requirements (TLS, etc.)

**What to Adapt:**
- Code examples (language-specific syntax)
- Installation instructions
- Framework integration examples
- Language-specific best practices

### README Structure Guidelines

Each language-specific SDK README should include:

1. **Header Section**
   - Badges (version, license, build status, language version)
   - Language toggle (if multi-language)
   - Brief introduction

2. **Core Sections** (in order)
   - Introduction with core features
   - Use cases table
   - Table of contents
   - System architecture diagram
   - Requirements
   - Installation
   - Quick start (Initialize, Create Order, Query Order)
   - Configuration (full options, env vars, framework integration)
   - API Usage (all resources with examples)
   - Webhook Handling
   - Payment Method Types
   - Advanced Configuration
   - Error Handling (with complete error code table)
   - Development & Testing
   - Support
   - License

3. **Code Examples**
   - Use actual working code, not pseudocode
   - Include imports
   - Show both success and error handling
   - Add comments for important steps

### Implementation Requirements

See `sdk-spec/` directory for detailed specifications:

- `RSA_SIGNING.md` - RSA signature implementation
- `HTTP_CLIENT.md` - HTTP client requirements
- `WEBHOOK_HANDLER.md` - Webhook processing
- `ERROR_HANDLING.md` - Error handling patterns
- `NEW_LANGUAGE_GUIDE.md` - Complete guide for new SDKs

### Adding a New Language

See [sdk-spec/NEW_LANGUAGE_GUIDE.md](./sdk-spec/NEW_LANGUAGE_GUIDE.md) for detailed instructions.

## Support

- Documentation: [Waffo Developer Docs](https://dashboard-sandbox.waffo.com/docs/)
- Issues: [GitHub Issues](https://github.com/waffo-com/waffo-sdk/issues)
- Technical Support: merchant.support@waffo.com

## License

MIT License - See [LICENSE](LICENSE) file for details.
