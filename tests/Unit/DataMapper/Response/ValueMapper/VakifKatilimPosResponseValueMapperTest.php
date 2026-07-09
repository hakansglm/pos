<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\Response\ValueMapper;

use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Mews\Pos\DataMapper\Response\ValueMapper\AbstractResponseValueMapper;
use Mews\Pos\DataMapper\Response\ValueMapper\VakifKatilimPosResponseValueMapper;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\KuveytPos;
use Mews\Pos\Gateway\VakifKatilimPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(VakifKatilimPosResponseValueMapper::class)]
#[CoversClass(AbstractResponseValueMapper::class)]
class VakifKatilimPosResponseValueMapperTest extends TestCase
{
    private VakifKatilimPosResponseValueMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new VakifKatilimPosResponseValueMapper();
    }

    public function testSupports(): void
    {
        $result = $this->mapper::supports(VakifKatilimPos::class);
        $this->assertTrue($result);

        $result = $this->mapper::supports(KuveytPos::class);
        $this->assertFalse($result);

        $result = $this->mapper::supports(AssecoPos::class);
        $this->assertFalse($result);
    }

    public function testMapTxType(): void
    {
        $this->expectException(LogicException::class);
        $this->mapper->mapTxType('Sale');
    }

    #[DataProvider('mapOrderStatusDataProvider')]
    public function testMapOrderStatus(int|string $orderStatus, string|int $expected): void
    {
        $this->assertSame($expected, $this->mapper->mapOrderStatus($orderStatus));
    }

    #[DataProvider('mapCurrencyDataProvider')]
    public function testMapCurrency(string $currency, string $txType, ?string $expected): void
    {
        $this->assertSame($expected, $this->mapper->mapCurrency($currency, $txType));
    }

    #[DataProvider('mapSecureTypeDataProvider')]
    public function testMapSecureType(string $secureType, string $txType, ?string $expected): void
    {
        $this->assertSame($expected, $this->mapper->mapSecureType($secureType, $txType));
    }

    public static function mapCurrencyDataProvider(): array
    {
        return [
            ['949', PosInterface::TX_TYPE_PAY_AUTH, PosInterface::CURRENCY_TRY],
            ['0949', PosInterface::TX_TYPE_PAY_AUTH, PosInterface::CURRENCY_TRY],
            ['949', '', PosInterface::CURRENCY_TRY],
            ['840', '', PosInterface::CURRENCY_USD],
            ['0840', '', PosInterface::CURRENCY_USD],
            ['978', '', PosInterface::CURRENCY_EUR],
            ['826', '', PosInterface::CURRENCY_GBP],
            ['0826', '', PosInterface::CURRENCY_GBP],
            ['392', '', PosInterface::CURRENCY_JPY],
            ['810', '', PosInterface::CURRENCY_RUB],
            ['TRY', '', null],
        ];
    }

    public static function mapOrderStatusDataProvider(): array
    {
        return [
            [1, PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED],
            [4, PosInterface::PAYMENT_STATUS_FULLY_REFUNDED],
            [5, PosInterface::PAYMENT_STATUS_PARTIALLY_REFUNDED],
            [6, PosInterface::PAYMENT_STATUS_CANCELED],
            [2, 2],
            ['blabla', 'blabla'],
        ];
    }

    public static function mapSecureTypeDataProvider(): array
    {
        return [
            ['3', PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_3D_SECURE],
            ['5', PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_NON_SECURE],
            ['0', PosInterface::TX_TYPE_PAY_AUTH, null],
        ];
    }
}
