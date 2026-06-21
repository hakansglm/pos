<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\Request\ValueMapper;

use Mews\Pos\DataMapper\Request\ValueMapper\AbstractRequestValueMapper;
use Mews\Pos\DataMapper\Request\ValueMapper\PayFlexCPV4PosRequestValueMapper;
use Mews\Pos\Exception\UnsupportedTransactionTypeException;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\PayFlexCPV4Pos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(PayFlexCPV4PosRequestValueMapper::class)]
#[CoversClass(AbstractRequestValueMapper::class)]
class PayFlexCPV4PosRequestValueMapperTest extends TestCase
{
    private PayFlexCPV4PosRequestValueMapper $valueMapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->valueMapper = new PayFlexCPV4PosRequestValueMapper();
    }

    public function testSupports(): void
    {
        $result = $this->valueMapper::supports(PayFlexCPV4Pos::class);
        $this->assertTrue($result);

        $result = $this->valueMapper::supports(AssecoPos::class);
        $this->assertFalse($result);
    }

    /**
     * @dataProvider mapTxTypeDataProvider
     */
    public function testMapTxType(string $txType, string $expected): void
    {
        $actual = $this->valueMapper->mapTxType($txType);
        $this->assertSame($expected, $actual);
    }

    #[TestWith(['Auth'])]
    public function testMapTxTypeException(string $txType): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->valueMapper->mapTxType($txType);
    }

    public function testMapSecureType(): void
    {
        $this->expectException(\LogicException::class);
        $this->valueMapper->mapSecureType(PosInterface::MODEL_3D_SECURE);
    }

    public function testMapRecurringFrequency(): void
    {
        $this->expectException(\LogicException::class);
        $this->valueMapper->mapRecurringFrequency('DAY');
    }

    public function testMapLang(): void
    {
        $this->assertSame('tr-TR', $this->valueMapper->mapLang(PosInterface::LANG_TR));
        $this->assertSame('en-US', $this->valueMapper->mapLang(PosInterface::LANG_EN));
        $this->assertSame('tr-TR', $this->valueMapper->mapLang('ru'));
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
        $this->assertCount(2, $this->valueMapper->getLangMappings());
    }

    public function testGetRecurringOrderFrequencyMappings(): void
    {
        $this->assertCount(0, $this->valueMapper->getRecurringOrderFrequencyMappings());
    }

    public function testGetCurrencyMappings(): void
    {
        $this->assertCount(6, $this->valueMapper->getCurrencyMappings());
    }

    public function testGetTxTypeMappings(): void
    {
        $this->assertCount(8, $this->valueMapper->getTxTypeMappings());
    }

    public function testGetSecureTypeMappings(): void
    {
        $this->assertCount(0, $this->valueMapper->getSecureTypeMappings());
    }

    public function testGetCardTypeMappings(): void
    {
        $this->assertCount(4, $this->valueMapper->getCardTypeMappings());
    }

    public static function mapTxTypeDataProvider(): array
    {
        return [
            [PosInterface::TX_TYPE_PAY_AUTH,  'Sale'],
            [PosInterface::TX_TYPE_PAY_PRE_AUTH, 'Auth'],
            [PosInterface::TX_TYPE_PAY_POST_AUTH, 'Capture'],
        ];
    }
}
