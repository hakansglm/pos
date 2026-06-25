<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\Request\ValueMapper;

use Mews\Pos\DataMapper\Request\ValueMapper\AbstractRequestValueMapper;
use Mews\Pos\DataMapper\Request\ValueMapper\PayTrPosRequestValueMapper;
use Mews\Pos\Exception\UnsupportedTransactionTypeException;
use Mews\Pos\Gateway\AkbankPos;
use Mews\Pos\Gateway\PayTrPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(PayTrPosRequestValueMapper::class)]
#[CoversClass(AbstractRequestValueMapper::class)]
class PayTrPosRequestValueMapperTest extends TestCase
{
    private PayTrPosRequestValueMapper $valueMapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->valueMapper = new PayTrPosRequestValueMapper();
    }

    public function testSupports(): void
    {
        $this->assertTrue($this->valueMapper::supports(PayTrPos::class));
        $this->assertFalse($this->valueMapper::supports(AkbankPos::class));
    }

    #[TestWith([PosInterface::CURRENCY_TRY, 'TL'])]
    #[TestWith([PosInterface::CURRENCY_USD, 'USD'])]
    #[TestWith([PosInterface::CURRENCY_EUR, 'EUR'])]
    #[TestWith([PosInterface::CURRENCY_GBP, 'GBP'])]
    #[TestWith([PosInterface::CURRENCY_RUB, 'RUB'])]
    public function testMapCurrency(string $currency, string $expected): void
    {
        $this->assertSame($expected, $this->valueMapper->mapCurrency($currency));
    }

    public function testGetCurrencyMappings(): void
    {
        $this->assertCount(5, $this->valueMapper->getCurrencyMappings());
    }

    #[TestWith(['tr', PosInterface::LANG_TR])]
    #[TestWith(['en', PosInterface::LANG_EN])]
    #[TestWith(['tr', 'ru'])]
    public function testMapLang(string $mappedLang, string $libLang): void
    {
        $this->assertSame($mappedLang, $this->valueMapper->mapLang($libLang));
    }

    public function testGetLangMappings(): void
    {
        $this->assertCount(2, $this->valueMapper->getLangMappings());
    }

    public function testGetTxTypeMappings(): void
    {
        $this->assertSame([], $this->valueMapper->getTxTypeMappings());
    }

    public function testMapTxTypeThrowsUnsupported(): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->valueMapper->mapTxType(PosInterface::TX_TYPE_PAY_AUTH);
    }

    public function testGetSecureTypeMappings(): void
    {
        $this->assertSame([], $this->valueMapper->getSecureTypeMappings());
    }

    public function testMapSecureTypeThrows(): void
    {
        $this->expectException(\LogicException::class);
        $this->valueMapper->mapSecureType(PosInterface::MODEL_3D_SECURE);
    }

    public function testGetRecurringOrderFrequencyMappings(): void
    {
        $this->assertSame([], $this->valueMapper->getRecurringOrderFrequencyMappings());
    }

    public function testMapRecurringFrequencyThrows(): void
    {
        $this->expectException(\LogicException::class);
        $this->valueMapper->mapRecurringFrequency('MONTH');
    }

    public function testGetCardTypeMappings(): void
    {
        $this->assertSame([], $this->valueMapper->getCardTypeMappings());
    }

    public function testMapCardTypeThrows(): void
    {
        $this->expectException(\LogicException::class);
        $this->valueMapper->mapCardType('VISA');
    }
}
