<?php

declare(strict_types=1);

namespace Waffo\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Waffo\Model\Generated\AcqOrderCreateRequest;
use Waffo\Model\Generated\AcqOrderMerchantUserInfo;
use Waffo\Model\Generated\WalletInquiryResponse;
use Waffo\Model\Generated\WalletInquiryResponse_PaymentOption;

final class ModelTest extends TestCase
{
    public function testWalletResponsePreservesTypedOptions(): void
    {
        $options = [
            ['network' => 'eip155:8453', 'asset' => 'asset-base', 'payTo' => 'wallet-base', 'decimals' => 6],
            ['network' => 'eip155:4217', 'asset' => 'asset-tempo', 'payTo' => 'wallet-tempo', 'decimals' => 18],
        ];
        foreach ([['paymentOptions' => $options], ['paymentOptions' => []], [],
            ['paymentOptions' => null], ['paymentOptions' => [['decimals' => 0]]],
            ['paymentOptions' => [['network' => 'eip155:8453']]]] as $data) {
            $model = WalletInquiryResponse::fromArray($data);
            // AbstractModel serialization omits null fields; hydration still accepts null.
            self::assertEquals(array_filter($data, static fn ($value) => $value !== null), $model->toArray());
            foreach ($model->paymentOptions ?? [] as $option) {
                self::assertInstanceOf(WalletInquiryResponse_PaymentOption::class, $option);
            }
        }
        $model = WalletInquiryResponse::fromArray(['paymentOptions' => $options]);
        self::assertSame(['paymentOptions'], array_keys(WalletInquiryResponse::SCHEMA));
        $decodedOptions = $model->paymentOptions;
        self::assertNotNull($decodedOptions);
        self::assertArrayHasKey(0, $decodedOptions);
        self::assertArrayHasKey(1, $decodedOptions);
        self::assertSame(6, $decodedOptions[0]->decimals);
        self::assertSame('wallet-tempo', $decodedOptions[1]->payTo);
        $zero = WalletInquiryResponse::fromArray(['paymentOptions' => [['decimals' => 0]]]);
        $zeroOptions = $zero->paymentOptions;
        self::assertNotNull($zeroOptions);
        self::assertArrayHasKey(0, $zeroOptions);
        self::assertSame(0, $zeroOptions[0]->decimals);
    }

    public function testGeneratedModelHydratesNestedReferencesAndPreservesUnknownFields(): void
    {
        $model = AcqOrderCreateRequest::fromArray([
            'paymentRequestId' => 'request-1',
            'userInfo' => ['userId' => 'user-1'],
            'futureField' => 'kept',
        ]);

        self::assertInstanceOf(AcqOrderMerchantUserInfo::class, $model->userInfo);
        self::assertSame('kept', $model->extraFields()['futureField']);
        self::assertSame('user-1', $model->toArray()['userInfo']['userId']);
    }
}
