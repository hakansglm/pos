<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseValueFormatter;

use Mews\Pos\DataMapper\ResponseValueFormatter\PosNetPosResponseValueFormatter;
use Mews\Pos\Gateways\AssecoPos;
use Mews\Pos\Gateways\PosNetPos;
use Mews\Pos\Gateways\PosNetV1Pos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\ResponseValueFormatter\PosNetPosResponseValueFormatter
 */
class PosNetPosResponseValueFormatterTest extends TestCase
{
    private PosNetPosResponseValueFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new PosNetPosResponseValueFormatter();
    }

    public function testSupports(): void
    {
        $result = $this->formatter::supports(PosNetPos::class);
        $this->assertTrue($result);
        $result = $this->formatter::supports(PosNetV1Pos::class);
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
            ['10,1', PosInterface::TX_TYPE_STATUS, 10.1],
            ['1.056,2', PosInterface::TX_TYPE_STATUS, 1056.2],
        ];
    }
}
