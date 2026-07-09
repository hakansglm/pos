<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\Response\ValueFormatter;

use PHPUnit\Framework\Attributes\DataProvider;
use Mews\Pos\DataMapper\Response\ValueFormatter\AssecoPosResponseValueFormatter;
use Mews\Pos\Gateway\AkbankPos;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AssecoPosResponseValueFormatter::class)]
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

    #[DataProvider('formatAmountProvider')]
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
