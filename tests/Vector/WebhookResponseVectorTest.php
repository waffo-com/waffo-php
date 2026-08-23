<?php

declare(strict_types=1);

namespace Waffo\Tests\Vector;

use PHPUnit\Framework\TestCase;
use Waffo\Config\WaffoConfig;
use Waffo\Crypto\Rsa;
use Waffo\Tests\Support\FixtureKeys;
use Waffo\Tests\Support\TestAsset;
use Waffo\Webhook\WebhookHandler;

final class WebhookResponseVectorTest extends TestCase
{
    public function testExactSignedWebhookResponseBodies(): void
    {
        $raw = file_get_contents(TestAsset::path(__DIR__, '../../../../sdk-spec/test-vectors/webhook-response.json', '../../sdk-spec/test-vectors/webhook-response.json'));
        self::assertNotFalse($raw);
        $vector = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $keys = FixtureKeys::load();
        $handler = new WebhookHandler(new WaffoConfig('api-key', $keys['privateKey'], $keys['publicKey']));

        $success = $handler->buildSuccessResponse();
        self::assertSame($vector['successResponse']['body'], $success->body);
        self::assertTrue(Rsa::verify($success->body, $success->signature, $keys['publicKey']));

        $failed = $handler->buildFailedResponse();
        self::assertSame($vector['failedResponse']['body'], $failed->body);
        self::assertTrue(Rsa::verify($failed->body, $failed->signature, $keys['publicKey']));
    }
}
