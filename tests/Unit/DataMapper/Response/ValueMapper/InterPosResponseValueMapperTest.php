<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\Response\ValueMapper;

use Mews\Pos\DataMapper\Response\ValueMapper\AbstractResponseValueMapper;
use Mews\Pos\DataMapper\Response\ValueMapper\InterPosResponseValueMapper;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\InterPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InterPosResponseValueMapper::class)]
#[CoversClass(AbstractResponseValueMapper::class)]
class InterPosResponseValueMapperTest extends TestCase
{
    private InterPosResponseValueMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new InterPosResponseValueMapper();
    }

    public function testSupports(): void
    {
        $result = $this->mapper::supports(InterPos::class);
        $this->assertTrue($result);

        $result = $this->mapper::supports(AssecoPos::class);
        $this->assertFalse($result);
    }

    public function testMapTxType(): void
    {
        $this->expectException(\LogicException::class);
        $this->mapper->mapTxType('Auth');
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
            ['949', PosInterface::TX_TYPE_PAY_AUTH, PosInterface::CURRENCY_TRY],
            ['949', '', PosInterface::CURRENCY_TRY],
            ['840', '', PosInterface::CURRENCY_USD],
            ['978', '', PosInterface::CURRENCY_EUR],
            ['826', '', PosInterface::CURRENCY_GBP],
            ['392', '', PosInterface::CURRENCY_JPY],
            ['643', '', PosInterface::CURRENCY_RUB],
            ['TRY', '', null],
        ];
    }
}
