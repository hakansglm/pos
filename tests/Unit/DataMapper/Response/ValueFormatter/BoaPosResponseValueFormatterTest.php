<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\Response\ValueFormatter;

use Mews\Pos\DataMapper\Response\ValueFormatter\BoaPosResponseValueFormatter;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\KuveytPos;
use Mews\Pos\Gateway\VakifKatilimPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BoaPosResponseValueFormatter::class)]
class BoaPosResponseValueFormatterTest extends TestCase
{
    private BoaPosResponseValueFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new BoaPosResponseValueFormatter();
    }

    public function testSupports(): void
    {
        $result = $this->formatter::supports(KuveytPos::class);
        $this->assertTrue($result);
        $result = $this->formatter::supports(VakifKatilimPos::class);
        $this->assertTrue($result);

        $result = $this->formatter::supports(AssecoPos::class);
        $this->assertFalse($result);
    }

    /**
     * @dataProvider formatAmountProvider
     */
    public function testFormatAmount(string $amount, string $txType, float $expected): void
    {
        $actual = $this->formatter->formatAmount($amount, $txType);
        $this->assertSame($expected, $actual);
    }

    public static function formatAmountProvider(): array
    {
        return [
            ['101', '', 1.01],
            ['101', PosInterface::TX_TYPE_PAY_AUTH, 1.01],
            ['101', PosInterface::TX_TYPE_STATUS, 101],
            ['101', PosInterface::TX_TYPE_HISTORY, 101],
            ['101', PosInterface::TX_TYPE_ORDER_HISTORY, 101],
        ];
    }
}
