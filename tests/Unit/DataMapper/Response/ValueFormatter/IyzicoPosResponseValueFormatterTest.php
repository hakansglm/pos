<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\Response\ValueFormatter;

use PHPUnit\Framework\Attributes\DataProvider;
use DateTimeImmutable;
use DateTimeZone;
use Mews\Pos\DataMapper\Response\ValueFormatter\AbstractResponseValueFormatter;
use Mews\Pos\DataMapper\Response\ValueFormatter\IyzicoPosResponseValueFormatter;
use Mews\Pos\Gateway\AkbankPos;
use Mews\Pos\Gateway\IyzicoPos;
use Mews\Pos\PosInterface;
use Mews\Pos\PosQuery\PosQueryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IyzicoPosResponseValueFormatter::class)]
#[CoversClass(AbstractResponseValueFormatter::class)]
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

    #[DataProvider('formatDateTimeDataProvider')]
    public function testFormatDateTime(string $dateTime, string $txType, DateTimeImmutable $expected): void
    {
        $actual = $this->formatter->formatDateTime($dateTime, $txType);
        $this->assertEquals($expected, $actual);
    }

    #[DataProvider('formatInstallmentDataProvider')]
    public function testFormatInstallment(?string $installment, int $expected): void
    {
        $actual = $this->formatter->formatInstallment($installment, PosInterface::TX_TYPE_PAY_AUTH);
        $this->assertSame($expected, $actual);
    }

    #[DataProvider('formatAmountDataProvider')]
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
                PosQueryInterface::QUERY_TYPE_HISTORY,
                new DateTimeImmutable('2026-06-11 23:17:51'),
            ],
            'tx_type_order_history_iso_string' => [
                '2026-06-11 23:29:31',
                PosInterface::TX_TYPE_ORDER_HISTORY,
                new DateTimeImmutable('2026-06-11 23:29:31'),
            ],
            'tx_type_pay_auth_epoch_ms'        => [
                '1781209772355',
                PosInterface::TX_TYPE_PAY_AUTH,
                (new DateTimeImmutable('@1781209772'))->setTimezone(new DateTimeZone('UTC')),
            ],
            'tx_type_status_epoch_ms'          => [
                '1781209772000',
                PosInterface::TX_TYPE_STATUS,
                (new DateTimeImmutable('@1781209772'))->setTimezone(new DateTimeZone('UTC')),
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
