<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\Response\ValueMapper;

use Mews\Pos\DataMapper\Response\ValueMapper\AbstractResponseValueMapper;
use Mews\Pos\DataMapper\Response\ValueMapper\IyzicoPosResponseValueMapper;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\Gateways\IyzicoPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IyzicoPosResponseValueMapper::class)]
#[CoversClass(AbstractResponseValueMapper::class)]
class IyzicoPosResponseValueMapperTest extends TestCase
{
    private IyzicoPosResponseValueMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new IyzicoPosResponseValueMapper();
    }

    public function testSupports(): void
    {
        $result = $this->mapper::supports(IyzicoPos::class);
        $this->assertTrue($result);

        $result = $this->mapper::supports(AkbankPos::class);
        $this->assertFalse($result);
    }

    /**
     * @dataProvider mapTxTypeDataProvider
     */
    public function testMapTxType(string $txType, ?string $expected): void
    {
        $this->assertSame($expected, $this->mapper->mapTxType($txType));
    }

    /**
     * @dataProvider mapOrderStatusDataProvider
     */
    public function testMapOrderStatus(string $orderStatus, ?string $requestTxType, string $expected): void
    {
        $this->assertSame($expected, $this->mapper->mapOrderStatus($orderStatus, $requestTxType));
    }

    /**
     * @dataProvider mapSecureTypeDataProvider
     */
    public function testMapSecureType(?int $securityType, ?string $expected): void
    {
        $this->assertSame($expected, $this->mapper->mapSecureType($securityType));
    }

    /**
     * @dataProvider mapCurrencyDataProvider
     */
    public function testMapCurrency(string $currency, ?string $expected): void
    {
        $this->assertSame($expected, $this->mapper->mapCurrency($currency));
    }

    public static function mapTxTypeDataProvider(): array
    {
        return [
            ['AUTH', PosInterface::TX_TYPE_PAY_AUTH],
            ['PRE_AUTH', PosInterface::TX_TYPE_PAY_PRE_AUTH],
            ['POST_AUTH', PosInterface::TX_TYPE_PAY_POST_AUTH],
            ['CANCEL', PosInterface::TX_TYPE_CANCEL],
            ['PAYMENT', PosInterface::TX_TYPE_PAY_AUTH],
            ['REFUND', PosInterface::TX_TYPE_REFUND],
            ['UNKNOWN', null],
        ];
    }

    public static function mapOrderStatusDataProvider(): array
    {
        return [
            'tx_type_status_success'                          => ['SUCCESS', PosInterface::TX_TYPE_STATUS, 'SUCCESS'],
            'tx_type_order_history_canceled'                  => ['CANCELED', PosInterface::TX_TYPE_ORDER_HISTORY, PosInterface::PAYMENT_STATUS_CANCELED],
            'tx_type_order_history_refunded'                  => ['REFUNDED', PosInterface::TX_TYPE_ORDER_HISTORY, PosInterface::PAYMENT_STATUS_FULLY_REFUNDED],
            'tx_type_order_history_partially_refunded'        => ['PARTIALLY_REFUNDED', PosInterface::TX_TYPE_ORDER_HISTORY, PosInterface::PAYMENT_STATUS_PARTIALLY_REFUNDED],
            'tx_type_order_history_totally_refunded_unmapped' => ['TOTALLY_REFUNDED', PosInterface::TX_TYPE_ORDER_HISTORY, 'TOTALLY_REFUNDED'],
            'null_tx_type_returns_raw'                        => ['anything', null, 'anything'],
        ];
    }

    public static function mapSecureTypeDataProvider(): array
    {
        return [
            [null, PosInterface::MODEL_NON_SECURE],
            [0, PosInterface::MODEL_NON_SECURE],
            [1, PosInterface::MODEL_3D_SECURE],
            [99, null],
        ];
    }

    public static function mapCurrencyDataProvider(): array
    {
        return [
            ['TRY', PosInterface::CURRENCY_TRY],
            ['USD', PosInterface::CURRENCY_USD],
            ['EUR', PosInterface::CURRENCY_EUR],
            ['GBP', PosInterface::CURRENCY_GBP],
            ['JPY', PosInterface::CURRENCY_JPY],
            ['RUB', PosInterface::CURRENCY_RUB],
            ['XYZ', null],
        ];
    }
}
