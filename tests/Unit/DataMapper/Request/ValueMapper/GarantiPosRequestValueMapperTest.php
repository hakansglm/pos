<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\Request\ValueMapper;

use LogicException;
use Mews\Pos\DataMapper\Request\ValueMapper\AbstractRequestValueMapper;
use Mews\Pos\DataMapper\Request\ValueMapper\GarantiPosRequestValueMapper;
use Mews\Pos\Exception\UnsupportedTransactionTypeException;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\GarantiPos;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\PosInterface;
use Mews\Pos\PosQuery\PosQueryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(GarantiPosRequestValueMapper::class)]
#[CoversClass(AbstractRequestValueMapper::class)]
class GarantiPosRequestValueMapperTest extends TestCase
{
    private GarantiPosRequestValueMapper $valueMapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->valueMapper = new GarantiPosRequestValueMapper();
    }

    public function testSupports(): void
    {
        $result = $this->valueMapper::supports(GarantiPos::class);
        $this->assertTrue($result);

        $result = $this->valueMapper::supports(AssecoPos::class);
        $this->assertFalse($result);
    }

    #[DataProvider('mapTxTypeDataProvider')]
    public function testMapTxType(string $txType, string $expected): void
    {
        $actual = $this->valueMapper->mapTxType($txType);
        $this->assertSame($expected, $actual);
    }

    #[TestWith(['sales'])]
    public function testMapTxTypeException(string $txType): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->valueMapper->mapTxType($txType);
    }

    #[DataProvider('mapSecureTypeDataProvider')]
    public function testMapSecureType(string $paymentModel, string $expected): void
    {
        $mappedSecureType = $this->valueMapper->mapSecureType($paymentModel);
        $this->assertSame($expected, $mappedSecureType);
    }

    #[TestWith(['DAY', 'D'])]
    #[TestWith(['WEEK', 'W'])]
    #[TestWith(['MONTH', 'M'])]
    public function testMapRecurringFrequency(string $frequency, string $expected): void
    {
        $this->assertSame($expected, $this->valueMapper->mapRecurringFrequency($frequency));
    }

    public function testMapLang(): void
    {
        $this->expectException(LogicException::class);
        $this->valueMapper->mapLang(PosInterface::LANG_TR);
    }

    /**
     * @return void
     */
    public function testMapCurrency(): void
    {
        $this->assertSame('949', $this->valueMapper->mapCurrency(PosInterface::CURRENCY_TRY));
        $this->assertSame('978', $this->valueMapper->mapCurrency(PosInterface::CURRENCY_EUR));
    }

    public function testGetLangMappings(): void
    {
        $this->assertCount(0, $this->valueMapper->getLangMappings());
    }

    public function testGetRecurringOrderFrequencyMappings(): void
    {
        $this->assertCount(3, $this->valueMapper->getRecurringOrderFrequencyMappings());
    }

    public function testGetCurrencyMappings(): void
    {
        $this->assertCount(6, $this->valueMapper->getCurrencyMappings());
    }

    public function testGetTxTypeMappings(): void
    {
        $this->assertCount(10, $this->valueMapper->getTxTypeMappings());
    }

    public function testGetSecureTypeMappings(): void
    {
        $this->assertCount(2, $this->valueMapper->getSecureTypeMappings());
    }

    public function testGetCardTypeMappings(): void
    {
        $this->assertCount(0, $this->valueMapper->getCardTypeMappings());
    }

    #[DataProvider('mapCardClassDataProvider')]
    public function testMapCardClass(?string $cardClass, string $expected): void
    {
        $this->assertSame($expected, $this->valueMapper->mapCardClass($cardClass));
    }

    public static function mapCardClassDataProvider(): array
    {
        return [
            [null, 'A'],
            [CreditCardInterface::CARD_CLASS_CREDIT, 'C'],
            [CreditCardInterface::CARD_CLASS_DEBIT, 'D'],
            [CreditCardInterface::CARD_CLASS_PREPAID, 'A'],
        ];
    }

    public static function mapSecureTypeDataProvider(): array
    {
        return [
            [PosInterface::MODEL_3D_SECURE, '3D'],
            [PosInterface::MODEL_3D_PAY, '3D_PAY'],
        ];
    }

    public static function mapTxTypeDataProvider(): array
    {
        return [
            [PosInterface::TX_TYPE_PAY_AUTH, 'sales'],
            [PosInterface::TX_TYPE_PAY_PRE_AUTH, 'preauth'],
            [PosQueryInterface::QUERY_TYPE_HISTORY, 'orderlistinq'],
            [PosQueryInterface::QUERY_TYPE_BIN_LIST, 'bininq'],
        ];
    }
}
