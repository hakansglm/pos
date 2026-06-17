<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseValueMapper;

use Mews\Pos\DataMapper\ResponseValueMapper\PosNetPosResponseValueMapper;
use Mews\Pos\Gateways\AssecoPos;
use Mews\Pos\Gateways\PosNetPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\ResponseValueMapper\PosNetPosResponseValueMapper
 * @covers \Mews\Pos\DataMapper\ResponseValueMapper\AbstractResponseValueMapper
 */
class PosNetPosResponseValueMapperTest extends TestCase
{
    private PosNetPosResponseValueMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new PosNetPosResponseValueMapper();
    }

    public function testSupports(): void
    {
        $result = $this->mapper::supports(PosNetPos::class);
        $this->assertTrue($result);

        $result = $this->mapper::supports(AssecoPos::class);
        $this->assertFalse($result);
    }


    /**
     * @dataProvider mapTxTypeDataProvider
     */
    public function testMapTxType(string $txType, ?string $expected): void
    {
        $this->assertSame($expected, $this->mapper->mapTxType($txType));
    }

    public function testMapOrderStatus(): void
    {
        $this->expectException(\LogicException::class);
        $this->mapper->mapOrderStatus('S');
    }

    /**
     * @dataProvider mapCurrencyDataProvider
     */
    public function testMapCurrency(string $currency, string $txType, ?string $expected): void
    {
        $this->assertSame($expected, $this->mapper->mapCurrency($currency, $txType));
    }

    public function testMapSecureType(): void
    {
        $this->expectException(\LogicException::class);
        $this->mapper->mapSecureType('3DModel', PosInterface::TX_TYPE_PAY_AUTH);
    }

    public static function mapCurrencyDataProvider(): array
    {
        return [
            ['TL', PosInterface::TX_TYPE_PAY_AUTH, PosInterface::CURRENCY_TRY],
            ['TL', '', PosInterface::CURRENCY_TRY],
            ['US', '', PosInterface::CURRENCY_USD],
            ['EU', '', PosInterface::CURRENCY_EUR],
            ['GB', '', PosInterface::CURRENCY_GBP],
            ['JP', '', PosInterface::CURRENCY_JPY],
            ['RU', '', PosInterface::CURRENCY_RUB],
            ['TRY', '', null],
        ];
    }


    public static function mapTxTypeDataProvider(): array
    {
        return [
            ['Sale', PosInterface::TX_TYPE_PAY_AUTH],
            ['Auth', PosInterface::TX_TYPE_PAY_PRE_AUTH],
            ['Capt', PosInterface::TX_TYPE_PAY_POST_AUTH],
            ['reverse', PosInterface::TX_TYPE_CANCEL],
            ['return', PosInterface::TX_TYPE_REFUND],
            ['agreement', PosInterface::TX_TYPE_STATUS],
            ['', null],
            ['blabla', null],
        ];
    }
}
