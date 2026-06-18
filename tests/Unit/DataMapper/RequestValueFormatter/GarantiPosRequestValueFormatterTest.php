<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestValueFormatter;

use Mews\Pos\DataMapper\RequestValueFormatter\GarantiPosRequestValueFormatter;
use Mews\Pos\Gateways\AssecoPos;
use Mews\Pos\Gateways\GarantiPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\DataMapper\RequestValueFormatter\GarantiPosRequestValueFormatter
 */
class GarantiPosRequestValueFormatterTest extends TestCase
{
    private GarantiPosRequestValueFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new GarantiPosRequestValueFormatter();
    }

    public function testSupports(): void
    {
        $result = $this->formatter::supports(GarantiPos::class);
        $this->assertTrue($result);

        $result = $this->formatter::supports(AssecoPos::class);
        $this->assertFalse($result);
    }

    /**
     * @testWith [0, ""]
     *            [1, ""]
     *            [2, "2"]
     */
    public function testFormatInstallment(int $installment, string $expected): void
    {
        $actual = $this->formatter->formatInstallment($installment);
        $this->assertSame($expected, $actual);
    }

    /**
     * @testWith [1.1, 110]
     * [1.0, 100]
     */
    public function testFormatAmount(float $amount, $expected): void
    {
        $actual = $this->formatter->formatAmount($amount);
        $this->assertSame($expected, $actual);
    }

    /**
     * @testWith ["abc"]
     * [""]
     */
    public function testFormatCreditCardExpDateUnSupportedField(string $fieldName): void
    {
        $expDate = new \DateTime('2024-04-14T16:45:30.000');
        $this->expectException(\InvalidArgumentException::class);
        $this->formatter->formatCardExpDate($expDate, $fieldName);
    }

    /**
     * @testWith ["cardexpiredatemonth", "04"]
     * ["cardexpiredateyear", "24"]
     * ["ExpireDate", "0424"]
     */
    public function testFormatCreditCardExpDate(string $fieldName, string $expected): void
    {
        $expDate = new \DateTime('2024-04-14T16:45:30.000');
        $actual  = $this->formatter->formatCardExpDate($expDate, $fieldName);
        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider formatDateTimeDataProvider
     */
    public function testFormatDateTime(\DateTimeInterface $dateTime, ?string $fieldName, ?string $txType, string $expected): void
    {
        $actual = $this->formatter->formatDateTime($dateTime, $fieldName, $txType);
        $this->assertSame($expected, $actual);
    }

    public static function formatDateTimeDataProvider(): array
    {
        $dateTime = new \DateTime('2024-04-14T16:45:30.000');

        return [
            'StartDate_with_history_txType_uses_datetime_format' => [
                $dateTime,
                'StartDate',
                PosInterface::TX_TYPE_HISTORY,
                '14/04/2024 16:45',
            ],
            'StartDate_without_txType_uses_date_only_format' => [
                $dateTime,
                'StartDate',
                null,
                '20240414',
            ],
            'StartDate_with_non_history_txType_uses_date_only_format' => [
                $dateTime,
                'StartDate',
                PosInterface::TX_TYPE_PAY_AUTH,
                '20240414',
            ],
            'EndDate_uses_datetime_format' => [
                $dateTime,
                'EndDate',
                null,
                '20240414',
            ],
            'null_fieldName_uses_datetime_format' => [
                $dateTime,
                null,
                null,
                '20240414',
            ],
        ];
    }
}
