<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\Response\ValueMapper;

use LogicException;
use Mews\Pos\DataMapper\Response\ValueMapper\AbstractResponseValueMapper;
use Mews\Pos\DataMapper\Response\ValueMapper\PayTrPosResponseValueMapper;
use Mews\Pos\Gateway\AkbankPos;
use Mews\Pos\Gateway\PayTrPos;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PayTrPosResponseValueMapper::class)]
#[CoversClass(AbstractResponseValueMapper::class)]
class PayTrPosResponseValueMapperTest extends TestCase
{
    private PayTrPosResponseValueMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new PayTrPosResponseValueMapper();
    }

    public function testSupports(): void
    {
        $this->assertTrue($this->mapper::supports(PayTrPos::class));
        $this->assertFalse($this->mapper::supports(AkbankPos::class));
    }

    #[DataProvider('mapCurrencyDataProvider')]
    public function testMapCurrency(string $currency, ?string $expected): void
    {
        $this->assertSame($expected, $this->mapper->mapCurrency($currency, PosInterface::TX_TYPE_STATUS));
    }

    #[DataProvider('mapTxTypeDataProvider')]
    public function testMapTxType(string $rawValue, ?string $expected): void
    {
        $this->assertSame($expected, $this->mapper->mapTxType($rawValue));
    }

    public function testMapSecureType(): void
    {
        $this->expectException(LogicException::class);
        $this->mapper->mapSecureType('3D');
    }

    public function testMapOrderStatus(): void
    {
        $this->expectException(LogicException::class);
        $this->mapper->mapOrderStatus('1');
    }

    #[DataProvider('mapCardFamilyNameDataProvider')]
    public function testMapCardFamilyName(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, $this->mapper->mapCardFamilyName($input));
    }

    public static function mapTxTypeDataProvider(): array
    {
        return [
            ['S',       PosInterface::TX_TYPE_PAY_AUTH],
            ['I',       PosInterface::TX_TYPE_REFUND],
            ['unknown', null],
        ];
    }

    public static function mapCardFamilyNameDataProvider(): array
    {
        return [
            [null,         null],
            ['world',      CreditCardInterface::CARD_FAMILY_WORLD],
            ['axess',      CreditCardInterface::CARD_FAMILY_AXESS],
            ['cardfinans', CreditCardInterface::CARD_FAMILY_CARDFINANS],
            ['paraf',      CreditCardInterface::CARD_FAMILY_PARAF],
            ['advantage',  CreditCardInterface::CARD_FAMILY_ADVANTAGE],
            ['bonus',      CreditCardInterface::CARD_FAMILY_BONUS],
            ['saglamkart', CreditCardInterface::CARD_FAMILY_SAGLAMKART],
            ['unknown',    'unknown'],
        ];
    }

    public static function mapCurrencyDataProvider(): array
    {
        return [
            ['TL',  PosInterface::CURRENCY_TRY],
            ['TRY', PosInterface::CURRENCY_TRY],
            ['USD', PosInterface::CURRENCY_USD],
            ['EUR', PosInterface::CURRENCY_EUR],
            ['GBP', PosInterface::CURRENCY_GBP],
            ['RUB', PosInterface::CURRENCY_RUB],
            ['JPY', null],
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
            [null,         null],
            ['VISA',       CreditCardInterface::CARD_TYPE_VISA],
            ['MASTER',     CreditCardInterface::CARD_TYPE_MASTERCARD],
            ['MASTERCARD', CreditCardInterface::CARD_TYPE_MASTERCARD],
            ['AMEX',       CreditCardInterface::CARD_TYPE_AMEX],
            ['TROY',       CreditCardInterface::CARD_TYPE_TROY],
            ['UNKNOWN',    null],
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
            [null,      null],
            ['credit',  CreditCardInterface::CARD_CLASS_CREDIT],
            ['debit',   CreditCardInterface::CARD_CLASS_DEBIT],
            ['prepaid', CreditCardInterface::CARD_CLASS_PREPAID],
            ['unknown', null],
        ];
    }
}
