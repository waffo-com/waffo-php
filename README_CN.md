# Waffo PHP SDK

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Build Status](https://img.shields.io/github/actions/workflow/status/waffo-com/waffo-sdk/ci.yml?branch=main)](https://github.com/waffo-com/waffo-sdk/actions)

[English](README.md) | **中文**

## 最新版本摘要

- **Node.js v2.5.0**: 新增 `subscription().update()`，支持 `amount`、`trialPeriodAmount` 和 `scheduledAmounts`，并补齐 E2E 验证，确认 scheduled amount 会在下一期扣款生效。
- **Java v2.0.0**: 订阅请求和响应中的 `cashierLanguage` 改为 `String`，同时保留 `CashierLanguage` 并扩充到最新 22 个便捷常量。
- **Go v1.4.0**: 新增订阅 update 能力，并将 create、change、update、inquiry 的 scheduled amount 字段统一对齐到 `openapi.json`。
- **Python v0.2.0b0**: 新增 `subscription.update()`，并补齐 scheduled amount 相关生成模型。
- **通用增强**: 补齐 capture 时间自动注入、扩展嵌套 schema 字段校验，并持续保持 SDK 类型与 `openapi.json` 对齐。

Waffo 官方 PHP SDK，提供支付、退款、订阅、配置、钱包查询和 Webhook 验签能力。

## 简介

Waffo SDK 是 [Waffo 支付平台](https://www.waffo.com) 的**官方 SDK 集合**，为 AI 产品、SaaS 服务等提供一站式全球支付解决方案。

### 核心特性

- **全球支付**: 支持信用卡、借记卡、电子钱包、虚拟账户等多种支付方式
- **订阅管理**: 完整的订阅生命周期管理，支持试用期、周期扣款、升降级
- **退款处理**: 灵活的全额/部分退款能力，支持退款状态追踪
- **Webhook 通知**: 实时推送支付结果、退款结果、订阅状态变更等
- **安全可靠**: PCI DSS 认证，RSA 签名验证，强制 TLS 1.2+ 加密

### 适用场景

| 场景 | 说明 |
|------|------|
| **AI 产品** | ChatGPT 类应用、AI 写作工具、AI 图像生成，支持按量计费或订阅 |
| **SaaS 服务** | 企业软件订阅、在线协作工具、云服务周期付款 |
| **内容平台** | 会员订阅、付费内容、打赏场景 |

## 目录

- [最新版本摘要](#最新版本摘要)
- [系统架构](#系统架构)
- [可用 SDK](#可用-sdk)
- [安装](#安装)
- [快速开始](#快速开始)
- [环境配置](#环境配置)
- [API 概览](#api-概览)
- [Webhook 处理](#webhook-处理)
- [支付方式类型](#支付方式类型)
- [高级配置](#高级配置)（含[配置 DNS 缓存 TTL](#配置-dns-缓存-ttl)）
- [处理新增 API 字段 (ExtraParams)](#处理新增-api-字段-extraparams)
- [错误处理](#错误处理)
- [开发](#开发)
- [支持](#支持)

## 系统架构

```
┌─────────────────────────────────────────────────────────────────────┐
│                         Waffo SDK                                   │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │                     Waffo (入口类)                           │   │
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
│  │                    WaffoHttpClient (核心)                     │  │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────┐   │  │
│  │  │ RSA 签名    │  │    JSON     │  │ 请求/响应处理        │   │  │
│  │  │  (SHA256)   │  │   序列化    │  │                     │   │  │
│  │  └─────────────┘  └─────────────┘  └─────────────────────┘   │  │
│  └──────────────────────────────────────────────────────────────┘  │
│                                        │                           │
│                                        ▼                           │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │                 HttpTransport (传输层)                        │  │
│  │  ┌────────────────────────────────────────────────────────┐  │  │
│  │  │  默认实现 (内置 HTTP 客户端)                             │  │  │
│  │  └────────────────────────────────────────────────────────┘  │  │
│  │  ┌────────────────────────────────────────────────────────┐  │  │
│  │  │  可扩展: 自定义 HTTP 客户端实现                          │  │  │
│  │  └────────────────────────────────────────────────────────┘  │  │
│  └──────────────────────────────────────────────────────────────┘  │
│                                        │                           │
└────────────────────────────────────────│───────────────────────────┘
                                         ▼
                              ┌────────────────────┐
                              │   Waffo API 服务器 │
                              │   SANDBOX / PROD   │
                              └────────────────────┘
```

### 模块说明

| 模块 | 说明 |
|------|------|
| `Waffo` | SDK 主入口类，提供对业务资源的直接访问 |
| `WaffoHttpClient` | 核心 HTTP 客户端，处理签名、序列化和通信 |
| `HttpTransport` | 传输层接口，支持自定义 HTTP 客户端实现 |
| `resources/*` | 业务资源类（订单、订阅、退款等） |
| `types/*` | 请求/响应类型定义 |
| `utils/*` | 工具类（RSA 签名、Webhook 处理等） |

## 可用 SDK

| 语言 | 包名 | 状态 | 文档 |
|------|------|------|------|
| Java | [com.waffo:waffo-java](https://github.com/waffo-com/waffo-java) | ✅ 稳定 | [English](https://github.com/waffo-com/waffo-java/blob/main/README_EN.md) / [中文](https://github.com/waffo-com/waffo-java) |
| Node.js/TypeScript | [@waffo/waffo-node](./packages/waffo-node) | ✅ 稳定 | [English](./packages/waffo-node/README.md) / [中文](./packages/waffo-node/README_CN.md) |
| Go | [waffo-go](./packages/waffo-go) | ✅ 稳定 | [English](./packages/waffo-go/README.md) / [中文](./packages/waffo-go/README_CN.md) |
| Python | [waffo](./packages/waffo-python) | ✅ 稳定 | [English](./packages/waffo-python/README.md) / [中文](./packages/waffo-python/README_CN.md) |
| PHP | [waffo/waffo-php](./packages/waffo-php) | ✅ 稳定 | [English](./packages/waffo-php/README.md) / [中文](./packages/waffo-php/README_CN.md) |
| Ruby | waffo-ruby | 🚧 计划中 | - |

> **注意**: 各语言 SDK 的详细安装说明、代码示例和框架集成请参阅对应 SDK 的 README 文档。

> **Java SDK 2.0.0 迁移提示**：请求和响应模型的 `cashierLanguage` 已改为 `String`。请求可直接传 IETF BCP 47 字符串，例如 `.cashierLanguage("en-HK")`；也可继续使用保留并扩充到最新 22 个语言码的 `CashierLanguage` 便捷常量，例如 `.cashierLanguage(CashierLanguage.EN_HK.getValue())`。可选响应请用可空 `String` 接收。已有源码通常会先在编译期报错；如果仅替换 JAR、未重新编译而在运行期出现 `NoSuchMethodError`，请清理旧 SDK 缓存或 shaded 副本，并对所有调用模块执行 clean rebuild。迁移前后代码见 [Java 2.0.0 changelog](./packages/waffo-java/CHANGELOG.md#200---2026-07-28)。

## 安装

### PHP

```bash
composer require waffo/waffo-php
```

## 快速开始

### 1. 初始化 SDK

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

### 2. 创建支付订单

```php
<?php

$response = $waffo->order()->create(['paymentRequestId' => bin2hex(random_bytes(16)), 'merchantOrderId' => 'ORDER-123', 'orderCurrency' => 'HKD', 'orderAmount' => '100.00', 'orderDescription' => 'Test order', 'notifyUrl' => 'https://example.com/webhooks/waffo', 'userInfo' => ['userId' => 'user-123'], 'paymentInfo' => ['productName' => 'ONE_TIME_PAYMENT']]);

if ($response->isSuccess()) {
    var_dump($response->data);
}
```

### 3. 查询订单状态

```php
<?php

$response = $waffo->order()->inquiry(['paymentRequestId' => 'payment-request-id']);

if ($response->isSuccess()) {
    var_dump($response->data);
}
```

## 环境配置

### 前置要求

- API Key（从 Waffo Dashboard 获取）
- RSA 密钥对（商户私钥 + Waffo 公钥）
- 商户 ID

### 环境变量

所有 SDK 支持通过环境变量配置：

```bash
export WAFFO_API_KEY=your-api-key
export WAFFO_PRIVATE_KEY=your-private-key
export WAFFO_PUBLIC_KEY=waffo-public-key       # Waffo 公钥
export WAFFO_ENVIRONMENT=SANDBOX               # 必填: SANDBOX 或 PRODUCTION
export WAFFO_MERCHANT_ID=your-merchant-id      # 必填
```

```bash
export WAFFO_API_KEY="your-api-key"
export WAFFO_PRIVATE_KEY="your-base64-private-key"
export WAFFO_PUBLIC_KEY="waffo-base64-public-key"
export WAFFO_MERCHANT_ID="your-merchant-id"
export WAFFO_ENVIRONMENT="sandbox"
```

### 环境 URL

| 环境 | Base URL | 说明 |
|------|----------|------|
| `SANDBOX` | `https://api-sandbox.waffo.com` | 测试环境 |
| `PRODUCTION` | `https://api.waffo.com` | 生产环境 |

> **重要**: 必须显式指定环境。SDK 不会默认使用任何环境，以防止意外请求到错误的环境。

### 配置选项

| 选项 | 必填 | 说明 |
|------|------|------|
| `apiKey` | ✅ | API Key |
| `privateKey` | ✅ | Base64 编码的商户私钥 |
| `waffoPublicKey` | ✅ | Waffo 公钥（从 Dashboard 获取） |
| `environment` | ✅ | SANDBOX 或 PRODUCTION |
| `merchantId` | ✅ | 商户 ID（必填，自动注入到请求中） |
| `connectTimeout` | ❌ | 连接超时（毫秒，默认: 10000） |
| `readTimeout` | ❌ | 读取超时（毫秒，默认: 30000） |
| `httpTransport` | ❌ | 自定义 HTTP 传输层 |

### 完整配置选项

```php
<?php

use Waffo\Config\Environment;
use Waffo\Config\WaffoConfig;

$config = new WaffoConfig('api-key', 'private-key', 'public-key', Environment::Sandbox, 'merchant-id', 10_000, 30_000);
```

### 请求级配置

```php
<?php

use Waffo\Config\RequestOptions;

$response = $waffo->order()->inquiry(['paymentRequestId' => 'payment-request-id'], new RequestOptions(5_000, 15_000));
```

### 框架集成

#### PHP 框架集成

所有 PHP 框架都必须把未经修改的请求体和 `X-SIGNATURE` 请求头传给 `WebhookHandler::handleWebhook()`，再以 HTTP 200 返回 SDK 生成的签名响应体与 `X-SIGNATURE`。

**Laravel 集成：**

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

**Symfony 集成：**

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

**Slim 集成：**

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

## API 概览

### 订单管理

| 方法 | 说明 |
|------|------|
| `order().create()` | 创建支付订单 |
| `order().inquiry()` | 查询订单状态 |
| `order().cancel()` | 取消订单 |
| `order().refund()` | 订单退款 |
| `order().capture()` | 请款（对已授权交易实际扣款） |

#### 创建订单

```php
<?php

$response = $waffo->order()->create(['paymentRequestId' => 'request-1', 'merchantOrderId' => 'order-1', 'orderCurrency' => 'USD', 'orderAmount' => '10.00', 'orderDescription' => 'Example', 'notifyUrl' => 'https://example.com/webhooks/waffo', 'userInfo' => ['userId' => 'user-1'], 'paymentInfo' => ['productName' => 'ONE_TIME_PAYMENT']]);

if ($response->isSuccess()) {
    var_dump($response->data);
}
```

#### 查询订单

```php
<?php

$response = $waffo->order()->inquiry(['acquiringOrderId' => 'A123']);

if ($response->isSuccess()) {
    var_dump($response->data);
}
```

#### 取消订单

```php
<?php

$response = $waffo->order()->cancel(['paymentRequestId' => 'request-1']);

if ($response->isSuccess()) {
    var_dump($response->data);
}
```

#### 退款订单

```php
<?php

$response = $waffo->order()->refund(['refundRequestId' => 'refund-1', 'acquiringOrderId' => 'A123', 'refundAmount' => '10.00', 'refundReason' => 'Customer request']);

if ($response->isSuccess()) {
    var_dump($response->data);
}
```

#### 请款（Capture）

对已授权（预授权）的交易实际扣款（手动请款流程）。请款金额不能超过授权金额。

```php
<?php

$response = $waffo->order()->capture(['paymentRequestId' => 'request-1', 'acquiringOrderId' => 'A123', 'captureAmount' => '10.00']);

if ($response->isSuccess()) {
    var_dump($response->data);
}
```

### 订阅管理

| 方法 | 说明 |
|------|------|
| `subscription().create()` | 创建订阅 |
| `subscription().inquiry()` | 查询订阅 |
| `subscription().cancel()` | 取消订阅 |
| `subscription().manage()` | 获取订阅管理 URL |
| `subscription().change()` | 变更订阅（升级/降级） |
| `subscription().changeInquiry()` | 查询订阅变更状态 |
| `subscription().update()` | 更新订阅计费金额（下一计费周期生效） |

#### 创建订阅

```php
<?php

$response = $waffo->subscription()->create(['subscriptionRequest' => 'sub-request-1', 'merchantSubscriptionId' => 'sub-1', 'currency' => 'USD', 'amount' => '9.99', 'notifyUrl' => 'https://example.com/webhooks/waffo', 'productInfo' => ['description' => 'Monthly plan', 'periodType' => 'MONTHLY', 'periodInterval' => '1'], 'userInfo' => ['userId' => 'user-1', 'userEmail' => 'user-1@example.com'], 'paymentInfo' => ['productName' => 'SUBSCRIPTION']]);

if ($response->isSuccess()) {
    var_dump($response->data);
}
```

#### 查询订阅

```php
<?php

$response = $waffo->subscription()->inquiry(['subscriptionRequest' => 'sub-request-1']);

if ($response->isSuccess()) {
    var_dump($response->data);
}
```

#### 取消订阅

```php
<?php

$response = $waffo->subscription()->cancel(['subscriptionId' => 'subscription-id']);

if ($response->isSuccess()) {
    var_dump($response->data);
}
```

#### 获取订阅管理链接

```php
<?php

$response = $waffo->subscription()->manage(['subscriptionId' => 'subscription-id']);

if ($response->isSuccess()) {
    var_dump($response->data);
}
```

#### 更新订阅

在不重新授权的情况下更新生效订阅的计费金额。提供 `subscriptionRequest` 或 `subscriptionId`，且 `amount` 与 `productInfo.scheduledAmounts` 至少提供一个。变更从下一个计费周期生效。

```php
<?php

$response = $waffo->subscription()->update(['subscriptionRequest' => 'sub-request-1', 'amount' => '8.99']);

if ($response->isSuccess()) {
    var_dump($response->data);
}
```

#### 订阅变更示例

变更现有订阅到新的计划（升级或降级）。

```php
<?php

$response = $waffo->subscription()->change(['subscriptionRequest' => 'new-sub-request', 'originSubscriptionRequest' => 'old-sub-request', 'remainingAmount' => '50.00', 'currency' => 'USD', 'notifyUrl' => 'https://example.com/webhooks/waffo', 'productInfoList' => [['description' => 'Annual plan', 'periodType' => 'MONTHLY', 'periodInterval' => '12', 'amount' => '99.00']], 'userInfo' => ['userId' => 'user-1', 'userEmail' => 'user-1@example.com'], 'goodsInfo' => ['goodsId' => 'annual', 'goodsName' => 'Annual plan'], 'paymentInfo' => ['productName' => 'SUBSCRIPTION']]);

if ($response->isSuccess()) {
    var_dump($response->data);
}
```

#### 查询订阅变更状态

```php
<?php

$response = $waffo->subscription()->changeInquiry(['subscriptionRequest' => 'new-sub-request', 'originSubscriptionRequest' => 'old-sub-request']);

if ($response->isSuccess()) {
    var_dump($response->data);
}
```

#### 订阅变更状态值

| 状态 | 说明 |
|------|------|
| `IN_PROGRESS` | 变更处理中 |
| `AUTHORIZATION_REQUIRED` | 需要用户授权（重定向到 webUrl） |
| `SUCCESS` | 变更成功完成 |
| `CLOSED` | 变更已关闭（超时或失败） |

### 退款查询

| 方法 | 说明 |
|------|------|
| `refund().inquiry()` | 查询退款状态 |

```php
<?php

$response = $waffo->refund()->inquiry(['refundRequestId' => 'refund-1']);

if ($response->isSuccess()) {
    var_dump($response->data);
}
```

### 商户配置

| 方法 | 说明 |
|------|------|
| `merchantConfig().inquiry()` | 查询商户配置 |
| `payMethodConfig().inquiry()` | 查询可用支付方式 |
| `wallet().inquiry()` | 查询 x402 稳定币链上收款地址 |

#### 查询商户配置示例

```php
<?php

$response = $waffo->merchantConfig()->inquiry(['merchantId' => 'merchant-id']);

if ($response->isSuccess()) {
    var_dump($response->data);
}
```

#### 查询可用支付方式示例

`payMethodDetails` 响应会返回支付方式类别、支持币种、订单金额上下限和退款策略。实时可用性暂时不可获取时，`currentStatus` 可能为空。

```php
<?php

$response = $waffo->payMethodConfig()->inquiry(['merchantId' => 'merchant-id']);

if ($response->isSuccess()) {
    var_dump($response->data);
}
```

#### 钱包地址查询示例 (x402)

查询 x402 支付的链上稳定币收款地址。响应返回目标网络、链上代币合约地址，以及 Waffo 平台收款钱包地址。第一阶段支持 `USDC`。

```php
<?php

$response = $waffo->wallet()->inquiry(['paymentRequestId' => 'request-1', 'orderCurrency' => 'USDC']);

if ($response->isSuccess()) {
    var_dump($response->data);
}
```

#### 钱包查询响应字段

| 字段 | 说明 |
|------|------|
| `network` | CAIP-2 网络 ID（第一阶段：`eip155:8453`，Base 主网） |
| `asset` | 该网络 + 币种对应的链上代币合约地址 |
| `payTo` | Waffo 平台收款钱包地址（向此地址付款） |

## Webhook 处理

Waffo 通过 Webhook 推送支付结果、退款结果、订阅状态变更等通知。

### Webhook 处理器示例

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

### 通知类型

| 事件类型 | 处理方法 | 说明 |
|----------|----------|------|
| `PAYMENT_NOTIFICATION` | `onPayment()` | 支付结果通知（每次支付尝试都会触发，包括重试） |
| `REFUND_NOTIFICATION` | `onRefund()` | 退款结果通知 |
| `SUBSCRIPTION_STATUS_NOTIFICATION` | `onSubscriptionStatus()` | 订阅状态变更通知（订阅主记录状态变化时触发） |
| `SUBSCRIPTION_PERIOD_CHANGED_NOTIFICATION` | `onSubscriptionPeriodChanged()` | 订阅周期变更通知（每个周期的最终结果） |
| `SUBSCRIPTION_CHANGE_NOTIFICATION` | `onSubscriptionChange()` | 订阅变更（升降级）结果通知 |

### 订阅通知类型说明

| 通知类型 | 触发条件 | 范围 | 包含重试事件 | 典型用途 |
|----------|----------|------|--------------|----------|
| `SUBSCRIPTION_STATUS_NOTIFICATION` | 订阅主记录状态变化 | 订阅级别 | 否 | 跟踪订阅生命周期：首次支付成功激活(ACTIVE)、取消(MERCHANT_CANCELLED, CHANNEL_CANCELLED)、首次支付失败关闭(CLOSE)等 |
| `SUBSCRIPTION_PERIOD_CHANGED_NOTIFICATION` | 订阅周期达到最终状态 | 周期级别 | 否（仅最终结果） | 只需要每个周期的最终结果，不需要中间重试事件 |
| `SUBSCRIPTION_CHANGE_NOTIFICATION` | 订阅变更（升降级）完成 | 变更请求级别 | 否（仅最终结果） | 跟踪订阅变更结果：SUCCESS 或 CLOSED |
| `PAYMENT_NOTIFICATION` | 每次支付订单 | 支付订单级别 | 是（包含所有重试） | 需要每次支付尝试的完整详情，包括失败原因、时间戳、重试详情 |

> **选择指南**:
> - 如果只关心订阅激活/取消，使用 `SUBSCRIPTION_STATUS_NOTIFICATION`
> - 如果只关心每个周期的最终续费结果，使用 `SUBSCRIPTION_PERIOD_CHANGED_NOTIFICATION`
> - 如果只关心订阅变更（升降级）的最终结果，使用 `SUBSCRIPTION_CHANGE_NOTIFICATION`
> - 如果需要跟踪每次支付尝试（包括重试），使用 `PAYMENT_NOTIFICATION`

> **订阅变更（升降级）Webhook 说明**:
> 当订阅变更处理完成时，会触发以下通知：
> - `SUBSCRIPTION_CHANGE_NOTIFICATION`: 订阅变更完成时触发（SUCCESS 或 CLOSED）
> - `SUBSCRIPTION_STATUS_NOTIFICATION`: 原订阅状态变更为 `MERCHANT_CANCELLED` 时触发
> - `SUBSCRIPTION_STATUS_NOTIFICATION`: 新订阅状态变更为 `ACTIVE` 时触发
> - `PAYMENT_NOTIFICATION`: 如果升级需要补差价付款时触发

### Webhook 通知载荷示例

以下示例展示各通知类型的实际载荷结构。

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

对于 x402 稳定币支付，`PAYMENT_NOTIFICATION.result` 还会包含 `x402Info`，其中包括 `x402Request`、`paymentSignatureHeader` 和 `x402Response`。

#### SUBSCRIPTION_STATUS_NOTIFICATION / SUBSCRIPTION_PERIOD_CHANGED_NOTIFICATION

以上两种通知类型共享相同的载荷结构：

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

## 支付方式类型

### payMethodType 参考

| 类型 | 说明 | 示例 payMethodName |
|------|------|-------------------|
| `CREDITCARD` | 信用卡 | CC_VISA, CC_MASTERCARD, CC_AMEX, CC_JCB 等 |
| `DEBITCARD` | 借记卡 | DC_VISA, DC_MASTERCARD, DC_ELO 等 |
| `EWALLET` | 电子钱包 | GCASH, DANA, PROMPTPAY, GRABPAY 等 |
| `VA` | 虚拟账户 | BCA, BNI, BRI, MANDIRI 等 |
| `APPLEPAY` | Apple Pay | APPLEPAY |
| `GOOGLEPAY` | Google Pay | GOOGLEPAY |

### 使用示例

只指定类型，让用户在收银台页面选择：

```json
{"paymentInfo": {"payMethodType": "CREDITCARD"}}
```

指定具体支付方式：

```json
{"paymentInfo": {"payMethodType": "CREDITCARD", "payMethodName": "CC_VISA"}}
```

组合多种类型：

```json
{"paymentInfo": {"payMethodType": "CREDITCARD,DEBITCARD"}}
```

指定电子钱包渠道：

```json
{"paymentInfo": {"payMethodType": "EWALLET", "payMethodName": "GCASH"}}
```

> **注意**: 可用的 `ProductName`、`PayMethodType`、`PayMethodName` 值，商户可登录 [Waffo Portal](https://dashboard.waffo.com) 查看已签约的支付方式（首页 → 服务 → 收单）。

## 高级配置

### 自定义 HTTP 传输层

SDK 默认使用内置 HTTP 客户端。如需连接池或其他高级特性，可以实现自定义传输层。

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

> **安全提示**: 实现自定义 HTTP 传输层时，必须确保强制使用 TLS 1.2 或更高版本。见下方「TLS 安全配置」。

### TLS 安全配置

SDK 对所有 HTTPS 通信默认强制使用 **TLS 1.2 或更高版本**，符合 PCI DSS 安全标准。

#### 默认传输层

默认 HTTP 传输层实现会自动配置 TLS 1.2+：
- 所有 HTTPS 连接只允许 TLS 1.2 与 TLS 1.3 协议
- 不安全的旧协议（SSLv3、TLS 1.0、TLS 1.1）自动禁用
- 无需额外配置，开箱即用

#### 自定义传输层的 TLS 配置

如果使用自定义 HTTP 传输层，请确保配置了 TLS 1.2+（见上方各语言自定义传输层示例中的 TLS 配置）。

### 调试日志

开发阶段可开启调试日志排查问题。

### 超时配置建议

| 操作类型 | 连接超时 | 读取超时 | 说明 |
|----------|----------|----------|------|
| 创建订单 | 5s | 30s | 推荐值 |
| 创建订阅 | 5s | 30s | 推荐值 |
| 退款操作 | 5s | 30s | 推荐值 |
| 查询操作 | 5s | 15s | 可以更短 |

### 连接池建议

| 场景 | 最大连接数 | 单路由最大连接数 | 说明 |
|------|-----------|------------------|------|
| 低流量（< 10 QPS） | 20 | 10 | 默认配置即可 |
| 中等流量（10-100 QPS） | 50 | 20 | 可考虑 OkHttp |
| 高流量（> 100 QPS） | 100-200 | 50 | 可考虑 Apache HttpClient |

### 实例复用

SDK 实例是**线程安全**的，推荐在应用中以单例方式使用：

```php
<?php

$waffo = Waffo\Waffo::fromEnvironment();
$order = $waffo->order()->inquiry(['paymentRequestId' => 'request-1']);
$refund = $waffo->refund()->inquiry(['refundRequestId' => 'refund-1']);
```

### 配置 DNS 缓存 TTL

Waffo API 的 IP 地址不保证是固定的。为保障高可用性，Waffo 采用多网关架构并支持自动容灾切换——当某一网关服务商发生故障时，DNS 记录会更新以将流量路由至健康的网关。请确保您的 DNS 缓存配置不会过长地缓存 DNS 记录——否则在容灾切换期间，您的应用会继续向不可用的网关发送请求，直到缓存过期或进程重启。

**建议 TTL：60 秒或更短。**

#### 基础设施 / 网关层

如果您的请求通过反向代理或负载均衡器访问 Waffo API，还需检查其 DNS 缓存行为——这与应用代码的配置是独立的：

- **Nginx**：默认情况下，Nginx **仅在启动时**解析 DNS 并永久缓存结果。您必须显式配置 `resolver` 并设置 TTL：
  ```nginx
  resolver 8.8.8.8 valid=60s;
  set $backend "api.waffo.com";
  proxy_pass https://$backend;
  ```
  注意：`proxy_pass` 中必须使用变量才能触发重新解析；硬编码的主机名只会解析一次。

- **AWS ALB / 云负载均衡器**：大多数云负载均衡器会自动处理 DNS 重新解析。请查阅您的云服务商文档进行确认。

- **CDN / API 网关**：如果您通过 Cloudflare、AWS API Gateway 或类似服务路由流量，请确保其 DNS TTL 设置符合您的需求。

## 处理新增 API 字段 (ExtraParams)

当 Waffo API 新增字段但 SDK 尚未更新时，可以使用 ExtraParams 功能访问这些字段，无需等待 SDK 更新。

### 从响应中读取未知字段

```php
<?php

$response = $waffo->order()->inquiry(['paymentRequestId' => 'request-1']);
$futureField = $response->data?->extraFields()['futureField'] ?? null;
```

### 在请求中发送额外字段

```php
<?php

$response = $waffo->order()->create(['paymentRequestId' => 'request-1', 'merchantOrderId' => 'order-1', 'orderCurrency' => 'USD', 'orderAmount' => '10.00', 'orderDescription' => 'Example', 'notifyUrl' => 'https://example.com/webhooks/waffo', 'userInfo' => ['userId' => 'user-1'], 'paymentInfo' => ['productName' => 'ONE_TIME_PAYMENT'], 'futureField' => 'forward-compatible']);

if ($response->isSuccess()) {
    var_dump($response->data);
}
```

### 重要提示

> **请及时升级 SDK**
>
> ExtraParams 是一个**临时解决方案**，用于在 SDK 更新前访问新增的 API 字段。
>
> **最佳实践：**
> 1. 定期查看 SDK 发布说明，了解新增字段支持
> 2. SDK 正式支持该字段后，请从 `getExtraParam("field")` 迁移到官方 getter（如 `getField()`）
> 3. 当您对已支持的字段使用 `getExtraParam()` 时，SDK 会输出警告日志
>
> **为什么要迁移？**
> - 官方 getter 提供类型安全
> - 更好的 IDE 自动补全和文档支持
> - 减少字段名拼写错误的风险

## 错误处理

### 错误处理模式

SDK 使用混合错误处理方式：
- **业务错误**: 通过 `ApiResponse` 返回，使用 `response.isSuccess()` 检查
- **未知状态异常**: 仅针对**写操作**（可能影响资金或状态），网络超时或服务器返回 E0001 错误码时抛出 `WaffoUnknownStatusError`

### 会抛出未知状态异常的方法

只有这些可能影响资金或状态的方法会抛出 `WaffoUnknownStatusError`：

| 方法 | 说明 |
|------|------|
| `order().create()` | 创建订单，可能发起支付 |
| `order().refund()` | 退款，可能导致资金变动 |
| `order().cancel()` | 取消订单，影响订单状态 |
| `subscription().create()` | 创建订阅，可能产生首次扣款 |
| `subscription().cancel()` | 取消订阅，影响订阅状态 |
| `subscription().change()` | 变更订阅，升级时可能产生扣款 |

**查询方法不会抛出此异常**（如 `inquiry()`），因为查询操作可以安全重试，不会影响资金或状态。

### WaffoUnknownStatusError 处理

> ⚠️ **重要警告**
>
> 当捕获到 `WaffoUnknownStatusError` 时，表示**操作结果不确定**。
>
> **不要直接关闭订单或假设支付失败！** 用户可能已经完成支付。
>
> **正确处理方式:**
> 1. 调用 `waffo.order().inquiry()` 查询实际订单状态
> 2. 或等待 Waffo Webhook 回调通知
> 3. 以 Waffo 返回的订单状态为准

```php
<?php

use Waffo\Exception\WaffoUnknownStatusError;

try {
    $response = $waffo->order()->create($params);
} catch (WaffoUnknownStatusError $error) {
    $response = $waffo->order()->inquiry(['paymentRequestId' => $params['paymentRequestId']]);
}
```

### 错误码分类

错误码按首字母分类：

| 前缀 | 类别 | 说明 |
|------|------|------|
| **S** | SDK 内部错误 | SDK 客户端内部错误，如网络超时、签名失败等 |
| **A** | 商户相关 | 参数、签名、权限、合约问题 |
| **B** | 用户相关 | 用户状态、余额、授权问题 |
| **C** | 系统相关 | Waffo 系统或支付渠道问题 |
| **D** | 风控相关 | 风控拒绝 |
| **E** | 未知状态 | 服务器返回未知状态 |

### 完整错误码表

#### SDK 内部错误 (Sxxxx)

| 错误码 | 说明 | 异常类型 | 处理建议 |
|--------|------|----------|----------|
| `S0001` | 网络错误 | `WaffoUnknownStatusError` | **状态未知**，需查询订单确认 |
| `S0002` | 无效公钥 | `WaffoError` | 检查公钥是否为有效的 Base64 编码 X509 格式 |
| `S0003` | RSA 签名失败 | `WaffoError` | 检查私钥格式是否正确 |
| `S0004` | 响应签名验证失败 | `ApiResponse.error()` | 检查 Waffo 公钥配置，联系 Waffo |
| `S0005` | 请求序列化失败 | `ApiResponse.error()` | 检查请求参数格式 |
| `S0006` | SDK 未知错误 | `ApiResponse.error()` | 检查日志，联系技术支持 |
| `S0007` | 无效私钥 | `WaffoError` | 检查私钥是否为有效的 Base64 编码 PKCS8 格式 |

> **重要**: `S0001` 和 `E0001`（服务器返回）表示**未知状态**。不要直接关闭订单！应调用查询 API 或等待 Webhook 确认实际状态。

#### 商户相关错误 (Axxxxx)

| 错误码 | 说明 | HTTP 状态 |
|--------|------|-----------|
| `0` | 成功 | 200 |
| `A0001` | 无效 API Key | 401 |
| `A0002` | 无效签名 | 401 |
| `A0003` | 参数验证失败 | 400 |
| `A0004` | 权限不足 | 401 |
| `A0005` | 商户限额已超 | 400 |
| `A0006` | 商户状态异常 | 400 |
| `A0007` | 不支持的交易币种 | 400 |
| `A0008` | 交易金额超限 | 400 |
| `A0009` | 订单不存在 | 400 |
| `A0010` | 商户合约不允许此操作 | 400 |
| `A0011` | 幂等参数不匹配 | 400 |
| `A0012` | 商户账户余额不足 | 400 |
| `A0013` | 订单已支付，无法取消 | 400 |
| `A0014` | 退款规则不允许退款 | 400 |
| `A0015` | 支付渠道不支持取消 | 400 |
| `A0016` | 支付渠道拒绝取消 | 400 |
| `A0017` | 支付渠道不支持退款 | 400 |
| `A0018` | 支付方式与商户合约不匹配 | 400 |
| `A0019` | 因拒付争议无法退款 | 400 |
| `A0020` | 支付金额超过单笔限额 | 400 |
| `A0021` | 累计支付金额超过日限额 | 400 |
| `A0022` | 存在多个产品，需指定产品名称 | 400 |
| `A0023` | Token 已过期，无法创建订单 | 400 |
| `A0024` | 汇率已过期，无法处理订单 | 400 |
| `A0026` | 不支持的结账语言 | 400 |
| `A0027` | 退款次数已达上限（50次） | 400 |
| `A0029` | 商户提供的卡数据无效 | 400 |
| `A0030` | 卡 BIN 未找到 | 400 |
| `A0031` | 不支持的卡组织或卡类型 | 400 |
| `A0032` | 无效的支付 Token 数据 | 400 |
| `A0033` | 多个同名支付方式，需指定国家 | 400 |
| `A0034` | 商户提供的订单过期时间已过 | 400 |
| `A0035` | 当前订单不支持捕获操作 | 400 |
| `A0036` | 当前订单状态不允许捕获操作 | 400 |
| `A0037` | 用户支付 Token 无效或已过期 | 400 |
| `A0038` | MIT 交易需要已验证的用户支付 Token | 400 |
| `A0039` | 订单已被拒付预防服务退款 | 400 |
| `A0040` | 订单不能并发创建 | 400 |
| `A0045` | MIT 交易无法处理，tokenId 状态未验证 | 400 |

#### 用户相关错误 (Bxxxxx)

| 错误码 | 说明 | HTTP 状态 |
|--------|------|-----------|
| `B0001` | 用户状态异常 | 400 |
| `B0002` | 用户限额已超 | 400 |
| `B0003` | 用户余额不足 | 400 |
| `B0004` | 用户未在超时时间内支付 | 400 |
| `B0005` | 用户授权失败 | 400 |
| `B0006` | 无效的手机号码 | 400 |
| `B0007` | 无效的邮箱格式 | 400 |

#### 系统相关错误 (Cxxxxx)

| 错误码 | 说明 | HTTP 状态 |
|--------|------|-----------|
| `C0001` | 系统错误 | 500 |
| `C0002` | 商户合约无效 | 500 |
| `C0003` | 订单状态无效，无法继续处理 | 500 |
| `C0004` | 订单信息不匹配 | 500 |
| `C0005` | 支付渠道拒绝 | 503 |
| `C0006` | 支付渠道错误 | 503 |
| `C0007` | 支付渠道维护中 | 503 |

#### 风控相关错误 (Dxxxxx)

| 错误码 | 说明 | HTTP 状态 |
|--------|------|-----------|
| `D0001` | 风控拒绝 | 406 |

#### 未知状态错误 (Exxxxx)

| 错误码 | 说明 | HTTP 状态 |
|--------|------|-----------|
| `E0001` | 未知状态（需查询或等待回调） | 500 |

> **注意**: 收到 `E0001` 错误码时，表示交易状态未知。**不要直接关闭订单**，应调用查询 API 确认实际状态，或等待 Webhook 回调通知。

## 开发

### 生成 RSA 密钥对

所有 SDK 都提供生成 RSA 密钥对的工具方法：

```php
<?php

$keys = Waffo\Waffo::generateKeyPair();
echo $keys['privateKey'] . PHP_EOL;
echo $keys['publicKey'] . PHP_EOL;
```

## 支持

- 文档: [Waffo 开发者文档](https://dashboard-sandbox.waffo.com/docs/)
- Issues: [GitHub Issues](https://github.com/waffo-com/waffo-sdk/issues)
- 技术支持: merchant.support@waffo.com

## 许可证

MIT License - 详见 [LICENSE](LICENSE) 文件。
