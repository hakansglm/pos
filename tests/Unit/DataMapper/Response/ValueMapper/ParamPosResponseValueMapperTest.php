<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\Response\ValueMapper;

use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Mews\Pos\DataMapper\Response\ValueMapper\AbstractResponseValueMapper;
use Mews\Pos\DataMapper\Response\ValueMapper\ParamPosResponseValueMapper;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\Param3DHostPos;
use Mews\Pos\Gateway\ParamPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ParamPosResponseValueMapper::class)]
#[CoversClass(AbstractResponseValueMapper::class)]
class ParamPosResponseValueMapperTest extends TestCase
{
    private ParamPosResponseValueMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new ParamPosResponseValueMapper();
    }

    public function testSupports(): void
    {
        $result = $this->mapper::supports(ParamPos::class);
        $this->assertTrue($result);
        $result = $this->mapper::supports(Param3DHostPos::class);
        $this->assertTrue($result);

        $result = $this->mapper::supports(AssecoPos::class);
        $this->assertFalse($result);
    }

    public function testMapTxType(): void
    {
        $this->expectException(LogicException::class);
        $this->mapper->mapTxType('Auth');
    }

    #[DataProvider('mapOrderStatusDataProvider')]
    public function testMapOrderStatus(
        string $orderStatus,
        ?string $preAuthStatus,
        bool   $isRecurringOrder,
        string $expected
    ): void {
        $this->assertSame(
            $expected,
            $this->mapper->mapOrderStatus($orderStatus)
        );
    }

    #[DataProvider('mapCurrencyDataProvider')]
    public function testMapCurrency(string $currency, string $txType, ?string $expected): void
    {
        $this->assertSame(
            $expected,
            $this->mapper->mapCurrency($currency, $txType)
        );
    }

    #[DataProvider('mapSecureTypeDataProvider')]
    public function testMapSecureType(string $secureType, string $txType, ?string $expected): void
    {
        $this->assertSame($expected, $this->mapper->mapSecureType($secureType, $txType));
    }



    public static function mapCurrencyDataProvider(): array
    {
        return [
            ['TL', PosInterface::TX_TYPE_PAY_AUTH, PosInterface::CURRENCY_TRY],
            ['TL', '', PosInterface::CURRENCY_TRY],
            ['TRL', '', PosInterface::CURRENCY_TRY],
            ['EUR', '', PosInterface::CURRENCY_EUR],
            ['USD', '', PosInterface::CURRENCY_USD],
            ['949', '', null],
        ];
    }

    public static function mapOrderStatusDataProvider(): array
    {
        return [
            ['SUCCESS', null, false, PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED],
            ['FAIL', null, false, PosInterface::PAYMENT_STATUS_ERROR],
            ['BANK_FAIL', null, false, PosInterface::PAYMENT_STATUS_ERROR],
            ['CANCEL', null, false, PosInterface::PAYMENT_STATUS_CANCELED],
            ['REFUND', null, false, PosInterface::PAYMENT_STATUS_FULLY_REFUNDED],
            ['PARTIAL_REFUND', null, false, PosInterface::PAYMENT_STATUS_PARTIALLY_REFUNDED],
            ['blabla', null, true, 'blabla'],
            ['blabla', null, false, 'blabla'],
        ];
    }

    public static function mapSecureTypeDataProvider(): array
    {
        return [
            ['3D', PosInterface::TX_TYPE_HISTORY, PosInterface::MODEL_3D_SECURE],
            ['NONSECURE', PosInterface::TX_TYPE_HISTORY, PosInterface::MODEL_NON_SECURE],
            ['abc', PosInterface::TX_TYPE_HISTORY, null],
            ['3D', PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_3D_SECURE],
            ['3D', PosInterface::TX_TYPE_PAY_PRE_AUTH, PosInterface::MODEL_3D_SECURE],
        ];
    }
}
