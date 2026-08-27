<?php

declare(strict_types=1);

namespace Waffo\Tests\Readme;

use PHPUnit\Framework\TestCase;
use Waffo\Config\WaffoConfig;
use Waffo\Crypto\Rsa;
use Waffo\Http\HttpResponse;
use Waffo\Tests\Support\FakeHttpTransport;
use Waffo\Tests\Support\FixtureKeys;
use Waffo\Waffo;

final class ReadmeExamplesTest extends TestCase
{
    /** @return iterable<string, array{string, positive-int}> */
    public static function packageReadmes(): iterable
    {
        yield 'English' => [__DIR__ . '/../../README.md', 31];
        yield 'Chinese' => [__DIR__ . '/../../README_CN.md', 31];
    }

    /** @dataProvider packageReadmes */
    public function testFrameworkIntegrationIsPhpOnly(string $readme): void
    {
        $content = file_get_contents($readme);
        self::assertNotFalse($content);
        self::assertSame(
            1,
            preg_match('/### (?:Framework Integration|框架集成)\n([\s\S]*?)(?=\n## )/', $content, $matches),
            $readme,
        );
        $frameworks = $matches[1] ?? '';
        self::assertNotSame('', $frameworks, $readme);

        self::assertStringContainsString('Laravel', $frameworks, $readme);
        self::assertStringContainsString('Symfony', $frameworks, $readme);
        self::assertStringContainsString('Slim', $frameworks, $readme);
        self::assertStringNotContainsString('Node.js Framework Integration', $frameworks, $readme);
        self::assertStringNotContainsString('Java Framework Integration', $frameworks, $readme);
        self::assertDoesNotMatchRegularExpression('/```(?:typescript|java)\b/', $frameworks, $readme);
    }

    /** @dataProvider packageReadmes */
    public function testPackageReadmeContainsNoForeignSdkCodeFences(string $readme): void
    {
        $content = file_get_contents($readme);
        self::assertNotFalse($content);
        self::assertDoesNotMatchRegularExpression(
            '/```(?:typescript|javascript|java|groovy|xml|go|python)\b/i',
            $content,
            $readme,
        );
    }

    /** @dataProvider packageReadmes */
    public function testEveryPhpSnippetHasValidSyntax(string $readme, int $expectedCount): void
    {
        $content = file_get_contents($readme);
        self::assertNotFalse($content);
        preg_match_all('/```php\n([\s\S]*?)\n```/', $content, $matches);
        self::assertCount($expectedCount, $matches[1], $readme);

        foreach ($matches[1] as $index => $snippet) {
            $temporary = tempnam(sys_get_temp_dir(), 'waffo-readme-');
            self::assertNotFalse($temporary);
            file_put_contents($temporary, $snippet);
            exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($temporary) . ' 2>&1', $output, $status);
            unlink($temporary);
            self::assertSame(0, $status, "Snippet {$index} in {$readme}: " . implode("\n", $output));
        }
    }

    public function testRootReadmesContainPhpInEveryTabsBlock(): void
    {
        if (!is_file(__DIR__ . '/../../../../sdk-spec/sdk-manifest.json')) {
            self::markTestSkipped('Monorepo root README synchronization is verified only in the monorepo checkout');
        }
        foreach (['../../../../README.md', '../../../../README_CN.md'] as $relative) {
            $content = file_get_contents(__DIR__ . '/' . $relative);
            self::assertNotFalse($content);
            preg_match_all('/<!-- tabs:start -->([\s\S]*?)<!-- tabs:end -->/', $content, $matches);
            self::assertCount(29, $matches[1]);
            foreach ($matches[1] as $index => $tabs) {
                self::assertMatchesRegularExpression('/####\s*\*\*PHP\*\*/', $tabs, "tabs block {$index}");
            }
        }
    }

    public function testQuickStartOrderAndWebhookExamplesExecuteOffline(): void
    {
        $keys = FixtureKeys::load();
        $transport = new FakeHttpTransport(new HttpResponse(
            200,
            [],
            '{"code":"0","message":"success","data":{"acquiringOrderId":"A-README"}}',
        ));
        $waffo = new Waffo(new WaffoConfig(
            'api-key',
            $keys['privateKey'],
            $keys['publicKey'],
            merchantId: 'merchant-id',
            httpTransport: $transport,
        ));

        $response = $waffo->order()->create([
            'paymentRequestId' => 'readme-request',
            'merchantOrderId' => 'readme-order',
            'orderCurrency' => 'USD',
            'orderAmount' => '10.00',
            'orderDescription' => 'README smoke',
            'notifyUrl' => 'https://example.com/webhooks/waffo',
            'userInfo' => ['userId' => 'readme-user'],
            'paymentInfo' => ['productName' => 'ONE_TIME_PAYMENT'],
        ]);
        self::assertTrue($response->isSuccess());
        self::assertSame('A-README', $response->data->acquiringOrderId);

        $body = '{"eventType":"PAYMENT_NOTIFICATION","result":{"acquiringOrderId":"A-README"}}';
        $called = false;
        $webhook = $waffo->webhook()
            ->onPayment(static function () use (&$called): void {
                $called = true;
            })
            ->handleWebhook($body, Rsa::sign($body, $keys['privateKey']));
        self::assertTrue($webhook->success);
        self::assertTrue($called);
    }

    public function testOperationExamplesUseValidRequestFieldNames(): void
    {
        foreach (self::packageReadmes() as [$readme]) {
            $content = file_get_contents($readme);
            self::assertNotFalse($content);
            self::assertStringNotContainsString('captureRequestId', $content, $readme);
            self::assertStringContainsString("'acquiringOrderId' => 'A123', 'captureAmount'", $content, $readme);
            self::assertStringContainsString("subscription()->cancel(['subscriptionId'", $content, $readme);
            self::assertStringContainsString("'refundReason' => 'Customer request'", $content, $readme);
            self::assertStringContainsString("'merchantSubscriptionId' => 'sub-1'", $content, $readme);
            self::assertStringContainsString("'userEmail' => 'user-1@example.com'", $content, $readme);
            self::assertStringContainsString("'periodType' => 'MONTHLY', 'periodInterval' => '12'", $content, $readme);
            self::assertStringNotContainsString("'periodType' => 'YEARLY'", $content, $readme);
        }
    }
}
