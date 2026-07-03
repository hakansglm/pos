<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\Response\ValueMapper;

use LogicException;
use Mews\Pos\DataMapper\Response\ValueMapper\AbstractResponseValueMapper;
use Mews\Pos\DataMapper\Response\ValueMapper\ParamPosResponseValueMapper;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\Param3DHostPos;
use Mews\Pos\Gateway\ParamPos;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\PosInterface;
use Mews\Pos\PosQuery\PosQueryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('mapCardFamilyNameDataProvider')]
    public function testMapCardFamilyName(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, $this->mapper->mapCardFamilyName($input));
    }

    public static function mapCardFamilyNameDataProvider(): array
    {
        return [
            [null, null],
            ['World', CreditCardInterface::CARD_FAMILY_WORLD],
            ['Axess', CreditCardInterface::CARD_FAMILY_AXESS],
            ['Bonus', CreditCardInterface::CARD_FAMILY_BONUS],
            ['Maximum', CreditCardInterface::CARD_FAMILY_MAXIMUM],
            ['Paraf', CreditCardInterface::CARD_FAMILY_PARAF],
            ['Diğer Banka Kartları', 'Diğer Banka Kartları'],
            ['Ziraat', 'Ziraat'],
        ];
    }

    #[DataProvider('mapOrderStatusDataProvider')]
    public function testMapOrderStatus(
        string  $orderStatus,
        ?string $preAuthStatus,
        bool    $isRecurringOrder,
        string  $expected
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
            ['3D', PosQueryInterface::QUERY_TYPE_HISTORY, PosInterface::MODEL_3D_SECURE],
            ['NONSECURE', PosQueryInterface::QUERY_TYPE_HISTORY, PosInterface::MODEL_NON_SECURE],
            ['abc', PosQueryInterface::QUERY_TYPE_HISTORY, null],
            ['3D', PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_3D_SECURE],
            ['3D', PosInterface::TX_TYPE_PAY_PRE_AUTH, PosInterface::MODEL_3D_SECURE],
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
            ['Kredi Kartı', CreditCardInterface::CARD_CLASS_CREDIT],
            ['Debit Kart', CreditCardInterface::CARD_CLASS_DEBIT],
            ['Ön Ödemeli Kart', CreditCardInterface::CARD_CLASS_PREPAID],
            ['unknown', null],
        ];
    }
}
