<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\Request\ValueFormatter;

use DateTime;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use DateTimeInterface;
use Mews\Pos\DataMapper\Request\ValueFormatter\GarantiPosRequestValueFormatter;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\GarantiPos;
use Mews\Pos\PosInterface;
use Mews\Pos\PosQuery\PosQueryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(GarantiPosRequestValueFormatter::class)]
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

    #[TestWith([0, ''])]
    #[TestWith([1, ''])]
    #[TestWith([2, '2'])]
    public function testFormatInstallment(int $installment, string $expected): void
    {
        $actual = $this->formatter->formatInstallment($installment);
        $this->assertSame($expected, $actual);
    }

    #[TestWith([1.1, 110])]
    #[TestWith([1.0, 100])]
    public function testFormatAmount(float $amount, $expected): void
    {
        $actual = $this->formatter->formatAmount($amount);
        $this->assertSame($expected, $actual);
    }

    #[TestWith(['abc'])]
    #[TestWith([''])]
    public function testFormatCreditCardExpDateUnSupportedField(string $fieldName): void
    {
        $expDate = new DateTime('2024-04-14T16:45:30.000');
        $this->expectException(InvalidArgumentException::class);
        $this->formatter->formatCardExpDate($expDate, $fieldName);
    }

    #[TestWith(['cardexpiredatemonth', '04'])]
    #[TestWith(['cardexpiredateyear', '24'])]
    #[TestWith(['ExpireDate', '0424'])]
    public function testFormatCreditCardExpDate(string $fieldName, string $expected): void
    {
        $expDate = new DateTime('2024-04-14T16:45:30.000');
        $actual  = $this->formatter->formatCardExpDate($expDate, $fieldName);
        $this->assertSame($expected, $actual);
    }

    #[DataProvider('formatDateTimeDataProvider')]
    public function testFormatDateTime(DateTimeInterface $dateTime, ?string $fieldName, ?string $txType, string $expected): void
    {
        $actual = $this->formatter->formatDateTime($dateTime, $fieldName, $txType);
        $this->assertSame($expected, $actual);
    }

    public static function formatDateTimeDataProvider(): array
    {
        $dateTime = new DateTime('2024-04-14T16:45:30.000');

        return [
            'StartDate_with_history_txType_uses_datetime_format' => [
                $dateTime,
                'StartDate',
                PosQueryInterface::QUERY_TYPE_HISTORY,
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
