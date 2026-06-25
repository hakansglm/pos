<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\Request\ValueFormatter;

use DateTime;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use DateTimeInterface;
use Mews\Pos\DataMapper\Request\ValueFormatter\VakifKatilimPosRequestValueFormatter;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\VakifKatilimPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(VakifKatilimPosRequestValueFormatter::class)]
class VakifKatilimPosRequestValueFormatterTest extends TestCase
{
    private VakifKatilimPosRequestValueFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new VakifKatilimPosRequestValueFormatter();
    }

    public function testSupports(): void
    {
        $result = $this->formatter::supports(VakifKatilimPos::class);
        $this->assertTrue($result);

        $result = $this->formatter::supports(AssecoPos::class);
        $this->assertFalse($result);
    }

    #[TestWith([0, '0'])]
    #[TestWith([1, '0'])]
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

    #[TestWith(['CardExpireDateMonth', '04'])]
    #[TestWith(['CardExpireDateYear', '24'])]
    public function testFormatCreditCardExpDate(string $fieldName, string $expected): void
    {
        $expDate = new DateTime('2024-04-14T16:45:30.000');
        $actual  = $this->formatter->formatCardExpDate($expDate, $fieldName);
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

    #[DataProvider('formatDateTimeDataProvider')]
    public function testFormatDateTime(DateTimeInterface $dateTime, ?string $fieldName, ?string $txType, string $expected): void
    {
        $actual = $this->formatter->formatDateTime($dateTime, $fieldName);
        $this->assertSame($expected, $actual);
    }

    #[TestWith(['abc'])]
    #[TestWith([null])]
    #[TestWith([''])]
    public function testFormatDateTimeUnsupportedField(?string $fieldName): void
    {
        $dateTime = new DateTime('2024-04-14T16:45:30.000');
        $this->expectException(InvalidArgumentException::class);
        $this->formatter->formatDateTime($dateTime, $fieldName);
    }


    public static function formatDateTimeDataProvider(): array
    {
        return [
            [
                new DateTime('2024-04-14T16:45:30.000'),
                'StartDate',
                PosInterface::TX_TYPE_HISTORY,
                '2024-04-14',
            ],
            [
                new DateTime('2024-04-14T16:45:30.000'),
                'EndDate',
                PosInterface::TX_TYPE_HISTORY,
                '2024-04-14',
            ],
        ];
    }
}
