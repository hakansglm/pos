<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\Request\ValueMapper;

use Mews\Pos\DataMapper\Request\ValueMapper\AbstractRequestValueMapper;
use Mews\Pos\DataMapper\Request\ValueMapper\IyzicoPosRequestValueMapper;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\Gateways\IyzicoPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IyzicoPosRequestValueMapper::class)]
#[CoversClass(AbstractRequestValueMapper::class)]
class IyzicoPosRequestValueMapperTest extends TestCase
{
    private IyzicoPosRequestValueMapper $valueMapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->valueMapper = new IyzicoPosRequestValueMapper();
    }

    public function testSupports(): void
    {
        $this->assertTrue($this->valueMapper::supports(IyzicoPos::class));
        $this->assertFalse($this->valueMapper::supports(AkbankPos::class));
    }

    /**
     * @dataProvider mapCurrencyDataProvider
     */
    public function testMapCurrency(string $currency, string $expected): void
    {
        $this->assertSame($expected, $this->valueMapper->mapCurrency($currency));
    }

    public function testMapLang(): void
    {
        $this->assertSame('tr', $this->valueMapper->mapLang(PosInterface::LANG_TR));
        $this->assertSame('en', $this->valueMapper->mapLang(PosInterface::LANG_EN));
        $this->assertSame('tr', $this->valueMapper->mapLang('ru'));
    }

    public function testGetTxTypeMappings(): void
    {
        $this->assertSame([], $this->valueMapper->getTxTypeMappings());
    }

    public function testGetCurrencyMappings(): void
    {
        $this->assertCount(6, $this->valueMapper->getCurrencyMappings());
    }

    public function testGetLangMappings(): void
    {
        $this->assertCount(2, $this->valueMapper->getLangMappings());
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

    public static function mapCurrencyDataProvider(): array
    {
        return [
            [PosInterface::CURRENCY_TRY, 'TRY'],
            [PosInterface::CURRENCY_USD, 'USD'],
            [PosInterface::CURRENCY_EUR, 'EUR'],
            [PosInterface::CURRENCY_GBP, 'GBP'],
            [PosInterface::CURRENCY_JPY, 'JPY'],
            [PosInterface::CURRENCY_RUB, 'RUB'],
        ];
    }
}
