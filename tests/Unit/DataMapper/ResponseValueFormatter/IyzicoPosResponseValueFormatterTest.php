<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseValueFormatter;

use Mews\Pos\DataMapper\ResponseValueFormatter\IyzicoPosResponseValueFormatter;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\Gateways\IyzicoPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\ResponseValueFormatter\IyzicoPosResponseValueFormatter
 * @covers \Mews\Pos\DataMapper\ResponseValueFormatter\AbstractResponseValueFormatter
 */
class IyzicoPosResponseValueFormatterTest extends TestCase
{
    private IyzicoPosResponseValueFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new IyzicoPosResponseValueFormatter();
    }

    public function testSupports(): void
    {
        $result = $this->formatter::supports(IyzicoPos::class);
        $this->assertTrue($result);

        $result = $this->formatter::supports(AkbankPos::class);
        $this->assertFalse($result);
    }

    /**
     * @dataProvider formatDateTimeDataProvider
     */
    public function testFormatDateTime(string $dateTime, string $txType, \DateTimeImmutable $expected): void
    {
        $actual = $this->formatter->formatDateTime($dateTime, $txType);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider formatInstallmentDataProvider
     */
    public function testFormatInstallment(?string $installment, int $expected): void
    {
        $actual = $this->formatter->formatInstallment($installment, PosInterface::TX_TYPE_PAY_AUTH);
        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider formatAmountDataProvider
     */
    public function testFormatAmount(string $amount, float $expected): void
    {
        $actual = $this->formatter->formatAmount($amount, PosInterface::TX_TYPE_PAY_AUTH);
        $this->assertSame($expected, $actual);
    }

    public static function formatDateTimeDataProvider(): array
    {
        return [
            'tx_type_history_iso_string'       => [
                '2026-06-11 23:17:51',
                PosInterface::TX_TYPE_HISTORY,
                new \DateTimeImmutable('2026-06-11 23:17:51'),
            ],
            'tx_type_order_history_iso_string' => [
                '2026-06-11 23:29:31',
                PosInterface::TX_TYPE_ORDER_HISTORY,
                new \DateTimeImmutable('2026-06-11 23:29:31'),
            ],
            'tx_type_pay_auth_epoch_ms'        => [
                '1781209772355',
                PosInterface::TX_TYPE_PAY_AUTH,
                (new \DateTimeImmutable('@1781209772'))->setTimezone(new \DateTimeZone('UTC')),
            ],
            'tx_type_status_epoch_ms'          => [
                '1781209772000',
                PosInterface::TX_TYPE_STATUS,
                (new \DateTimeImmutable('@1781209772'))->setTimezone(new \DateTimeZone('UTC')),
            ],
        ];
    }

    public static function formatInstallmentDataProvider(): array
    {
        return [
            [null, 0],
            ['1', 0],
            ['2', 2],
            ['0', 0],
        ];
    }

    public static function formatAmountDataProvider(): array
    {
        return [
            ['10.01', 10.01],
            ['100', 100.0],
        ];
    }
}
