<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\Response\ValueMapper;

use Mews\Pos\DataMapper\Response\ValueMapper\AbstractResponseValueMapper;
use Mews\Pos\DataMapper\Response\ValueMapper\GarantiPosResponseValueMapper;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\GarantiPos;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\PosInterface;
use Mews\Pos\PosQuery\PosQueryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(GarantiPosResponseValueMapper::class)]
#[CoversClass(AbstractResponseValueMapper::class)]
class GarantiPosResponseValueMapperTest extends TestCase
{
    private GarantiPosResponseValueMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new GarantiPosResponseValueMapper();
    }

    public function testSupports(): void
    {
        $result = $this->mapper::supports(GarantiPos::class);
        $this->assertTrue($result);

        $result = $this->mapper::supports(AssecoPos::class);
        $this->assertFalse($result);
    }

    #[DataProvider('mapTxTypeDataProvider')]
    public function testMapTxType(string $txType, ?string $expected): void
    {
        $this->assertSame($expected, $this->mapper->mapTxType($txType));
    }

    #[DataProvider('mapOrderStatusDataProvider')]
    public function testMapOrderStatus(
        string  $orderStatus,
        ?string $requestTxType,
        ?string $txType,
        string  $expected
    ): void {
        $this->assertSame(
            $expected,
            $this->mapper->mapOrderStatus($orderStatus, $requestTxType, $txType)
        );
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
            ['TL', PosQueryInterface::QUERY_TYPE_HISTORY, PosInterface::CURRENCY_TRY],
            ['949', '', PosInterface::CURRENCY_TRY],
            ['TRY', '', null],
            ['840', '', PosInterface::CURRENCY_USD],
            ['USD', PosQueryInterface::QUERY_TYPE_HISTORY, PosInterface::CURRENCY_USD],
            ['978', '', PosInterface::CURRENCY_EUR],
            ['826', '', PosInterface::CURRENCY_GBP],
            ['392', '', PosInterface::CURRENCY_JPY],
            ['643', '', PosInterface::CURRENCY_RUB],
        ];
    }


    public static function mapTxTypeDataProvider(): array
    {
        return [
            ['Satis', PosInterface::TX_TYPE_PAY_AUTH],
            ['sales', PosInterface::TX_TYPE_PAY_AUTH],
            ['On Otorizasyon', PosInterface::TX_TYPE_PAY_PRE_AUTH],
            ['On Otorizasyon Kapama', PosInterface::TX_TYPE_PAY_POST_AUTH],
            ['Iade', PosInterface::TX_TYPE_REFUND],
            ['refund', PosInterface::TX_TYPE_REFUND],
            ['Iptal', PosInterface::TX_TYPE_CANCEL],
            ['void', PosInterface::TX_TYPE_CANCEL],
            ['', null],
        ];
    }

    public static function mapOrderStatusDataProvider(): array
    {
        return [
            ['WAITINGPOSTAUTH', PosInterface::TX_TYPE_STATUS, null, PosInterface::PAYMENT_STATUS_PRE_AUTH_COMPLETED],
            ['APPROVED', PosInterface::TX_TYPE_STATUS, null, 'APPROVED'],
            ['blabla', PosInterface::TX_TYPE_STATUS, null, 'blabla'],

            ['Basarili', PosQueryInterface::QUERY_TYPE_HISTORY, null, 'Basarili'],
            ['blabla', PosQueryInterface::QUERY_TYPE_HISTORY, null, 'blabla'],

            ['Basarili', PosQueryInterface::QUERY_TYPE_HISTORY, PosInterface::TX_TYPE_CANCEL, PosInterface::PAYMENT_STATUS_CANCELED],
            ['Onaylandi', PosQueryInterface::QUERY_TYPE_HISTORY, PosInterface::TX_TYPE_CANCEL, PosInterface::PAYMENT_STATUS_CANCELED],
            ['Basarili', PosQueryInterface::QUERY_TYPE_HISTORY, PosInterface::TX_TYPE_REFUND, PosInterface::PAYMENT_STATUS_FULLY_REFUNDED],
            ['Basarili', PosQueryInterface::QUERY_TYPE_HISTORY, PosInterface::TX_TYPE_PAY_AUTH, PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED],
            ['Basarili', PosQueryInterface::QUERY_TYPE_HISTORY, PosInterface::TX_TYPE_PAY_POST_AUTH, PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED],
            ['Basarili', PosQueryInterface::QUERY_TYPE_HISTORY, PosInterface::TX_TYPE_PAY_PRE_AUTH, PosInterface::PAYMENT_STATUS_PRE_AUTH_COMPLETED],
            ['Basarili', PosQueryInterface::QUERY_TYPE_HISTORY, '', 'Basarili'],
            ['Iptal', PosQueryInterface::QUERY_TYPE_HISTORY, '', 'Iptal'],
            ['', PosQueryInterface::QUERY_TYPE_HISTORY, '', PosInterface::PAYMENT_STATUS_ERROR],
            ['blabla', '', '', 'blabla'],
        ];
    }


    public static function mapSecureTypeDataProvider(): array
    {
        return [
            ['3D', PosQueryInterface::QUERY_TYPE_HISTORY, PosInterface::MODEL_3D_SECURE],
            ['', PosQueryInterface::QUERY_TYPE_HISTORY, PosInterface::MODEL_NON_SECURE],
            ['abc', PosQueryInterface::QUERY_TYPE_HISTORY, null],
            ['3D', PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_3D_SECURE],
            ['3D', PosInterface::TX_TYPE_PAY_PRE_AUTH, PosInterface::MODEL_3D_SECURE],
            ['3D_PAY', PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_3D_PAY],
            ['3D_PAY', PosInterface::TX_TYPE_PAY_PRE_AUTH, PosInterface::MODEL_3D_PAY],
        ];
    }

    #[DataProvider('mapCardTypeDataProvider')]
    public function testMapCardType(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, $this->mapper->mapCardType($input));
    }

    public static function mapCardTypeDataProvider(): array
    {
        return [
            [null, null],
            ['VISA', CreditCardInterface::CARD_TYPE_VISA],
            ['MASTER', CreditCardInterface::CARD_TYPE_MASTERCARD],
            ['MASTERCARD', CreditCardInterface::CARD_TYPE_MASTERCARD],
            ['AMEX', CreditCardInterface::CARD_TYPE_AMEX],
            ['TROY', CreditCardInterface::CARD_TYPE_TROY],
            ['UNKNOWN', null],
        ];
    }

    #[DataProvider('mapCardClassDataProvider')]
    public function testMapCardClass(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, $this->mapper->mapCardClass($input));
    }

    public static function mapCardClassDataProvider(): array
    {
        return [
            [null, null],
            ['C', CreditCardInterface::CARD_CLASS_CREDIT],
            ['D', CreditCardInterface::CARD_CLASS_DEBIT],
            ['M', CreditCardInterface::CARD_CLASS_PREPAID],
            ['X', null],
        ];
    }
}
