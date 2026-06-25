<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\Response\ValueFormatter;

use DateTimeImmutable;
use Mews\Pos\DataMapper\Response\ValueFormatter\AbstractResponseValueFormatter;
use Mews\Pos\DataMapper\Response\ValueFormatter\PayTrPosResponseValueFormatter;
use Mews\Pos\Gateway\AkbankPos;
use Mews\Pos\Gateway\PayTrPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PayTrPosResponseValueFormatter::class)]
#[CoversClass(AbstractResponseValueFormatter::class)]
class PayTrPosResponseValueFormatterTest extends TestCase
{
    private PayTrPosResponseValueFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new PayTrPosResponseValueFormatter();
    }

    public function testSupports(): void
    {
        $this->assertTrue($this->formatter::supports(PayTrPos::class));
        $this->assertFalse($this->formatter::supports(AkbankPos::class));
    }

    #[DataProvider('formatAmountDataProvider')]
    public function testFormatAmount(string $amount, string $txType, float $expected): void
    {
        $this->assertSame($expected, $this->formatter->formatAmount($amount, $txType));
    }

    #[DataProvider('formatInstallmentDataProvider')]
    public function testFormatInstallment(?string $installment, int $expected): void
    {
        $this->assertSame($expected, $this->formatter->formatInstallment($installment, PosInterface::TX_TYPE_PAY_AUTH));
    }

    #[DataProvider('formatDateTimeDataProvider')]
    public function testFormatDateTime(string $dateTime, DateTimeImmutable $expected): void
    {
        $this->assertEquals($expected, $this->formatter->formatDateTime($dateTime, PosInterface::TX_TYPE_STATUS));
    }

    public static function formatAmountDataProvider(): array
    {
        return [
            'status_dot_decimal'   => ['10.01',  PosInterface::TX_TYPE_STATUS,       10.01],
            'status_comma_decimal' => ['1,16',   PosInterface::TX_TYPE_STATUS,       1.16],
            'status_whole'         => ['100.00', PosInterface::TX_TYPE_STATUS,       100.0],
            'history_dot_decimal'  => ['10.00',  PosInterface::TX_TYPE_HISTORY,      10.0],
            'history_comma_decimal' => ['1,16',   PosInterface::TX_TYPE_HISTORY,      1.16],
            'refund_decimal'       => ['6.00',   PosInterface::TX_TYPE_REFUND,       6.0],
            'refund_zero'          => ['0.00',   PosInterface::TX_TYPE_REFUND,       0.0],
            'callback_cents'       => ['1001',   PosInterface::TX_TYPE_PAY_AUTH,     10.01],
            'callback_pre_auth'    => ['500',    PosInterface::TX_TYPE_PAY_PRE_AUTH, 5.0],
            'callback_zero'        => ['0',      PosInterface::TX_TYPE_PAY_AUTH,     0.0],
        ];
    }

    public static function formatInstallmentDataProvider(): array
    {
        return [
            ['0',  0],
            ['1',  0],
            ['2',  2],
            ['12', 12],
            [null, 0],
        ];
    }

    public static function formatDateTimeDataProvider(): array
    {
        return [
            ['23.06.2026',          new DateTimeImmutable('23.06.2026')],
            ['2026-06-23 14:30:00', new DateTimeImmutable('2026-06-23 14:30:00')],
        ];
    }
}
