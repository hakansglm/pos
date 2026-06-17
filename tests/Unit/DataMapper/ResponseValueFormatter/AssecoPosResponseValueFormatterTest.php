<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseValueFormatter;

use Mews\Pos\DataMapper\ResponseValueFormatter\AssecoPosResponseValueFormatter;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\Gateways\AssecoPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\ResponseValueFormatter\AssecoPosResponseValueFormatter
 */
class AssecoPosResponseValueFormatterTest extends TestCase
{
    private AssecoPosResponseValueFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new AssecoPosResponseValueFormatter();
    }

    public function testSupports(): void
    {
        $result = $this->formatter::supports(AssecoPos::class);
        $this->assertTrue($result);

        $result = $this->formatter::supports(AkbankPos::class);
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
            ['1.00', PosInterface::TX_TYPE_PAY_AUTH, 1.0],
            ['1.00', PosInterface::TX_TYPE_PAY_PRE_AUTH, 1.0],
            ['1.00', PosInterface::TX_TYPE_PAY_POST_AUTH, 1.0],
            ['1.00', PosInterface::TX_TYPE_CANCEL, 1.0],
            ['1.00', PosInterface::TX_TYPE_REFUND, 1.0],
            ['1.00', PosInterface::TX_TYPE_REFUND_PARTIAL, 1.0],
            ['1.00', '', 1.0],
            ['1001', PosInterface::TX_TYPE_STATUS, 10.01],
            ['1001', PosInterface::TX_TYPE_ORDER_HISTORY, 10.01],
        ];
    }
}
