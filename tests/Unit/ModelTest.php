<?php

declare(strict_types=1);

namespace Waffo\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Waffo\Model\Generated\AcqOrderCreateRequest;
use Waffo\Model\Generated\AcqOrderMerchantUserInfo;

final class ModelTest extends TestCase
{
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
