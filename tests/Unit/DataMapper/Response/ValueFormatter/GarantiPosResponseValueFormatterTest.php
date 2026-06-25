<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\Response\ValueFormatter;

use PHPUnit\Framework\Attributes\DataProvider;
use Mews\Pos\DataMapper\Response\ValueFormatter\GarantiPosResponseValueFormatter;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\GarantiPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GarantiPosResponseValueFormatter::class)]
class GarantiPosResponseValueFormatterTest extends TestCase
{
    private GarantiPosResponseValueFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new GarantiPosResponseValueFormatter();
    }

    public function testSupports(): void
    {
        $result = $this->formatter::supports(GarantiPos::class);
        $this->assertTrue($result);

        $result = $this->formatter::supports(AssecoPos::class);
        $this->assertFalse($result);
    }

    #[DataProvider('formatAmountProvider')]
    public function testFormatAmount(string $amount, string $txType, float $expected): void
    {
        $actual = $this->formatter->formatAmount($amount, $txType);
        $this->assertSame($expected, $actual);
    }

    #[DataProvider('formatInstallmentProvider')]
    public function testFormatInstallment(?string $installment, string $txType, int $expected): void
    {
        $actual = $this->formatter->formatInstallment($installment, $txType);
        $this->assertSame($expected, $actual);
    }

    public static function formatInstallmentProvider(): array
    {
        return [
            ['1', PosInterface::TX_TYPE_PAY_AUTH, 0],
            ['1', '', 0],
            ['0', PosInterface::TX_TYPE_PAY_AUTH, 0],
            ['0', '', 0],
            [null, PosInterface::TX_TYPE_PAY_AUTH, 0],
            [null, '', 0],
            ['1', PosInterface::TX_TYPE_HISTORY, 0],
            ['Pesin', PosInterface::TX_TYPE_HISTORY, 0],
        ];
    }

    public static function formatAmountProvider(): array
    {
        return [
            ['1001', PosInterface::TX_TYPE_PAY_AUTH, 10.01],
            ['1001', PosInterface::TX_TYPE_PAY_PRE_AUTH, 10.01],
            ['1001', PosInterface::TX_TYPE_PAY_POST_AUTH, 10.01],
            ['1001', PosInterface::TX_TYPE_CANCEL, 10.01],
            ['1001', PosInterface::TX_TYPE_REFUND, 10.01],
            ['1001', PosInterface::TX_TYPE_REFUND_PARTIAL, 10.01],
            ['1001', PosInterface::TX_TYPE_STATUS, 10.01],
            ['1001', PosInterface::TX_TYPE_ORDER_HISTORY, 10.01],
            ['1001', '', 10.01],
        ];
    }
}
