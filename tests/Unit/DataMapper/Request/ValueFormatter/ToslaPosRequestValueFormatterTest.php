<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\Request\ValueFormatter;

use Mews\Pos\DataMapper\Request\ValueFormatter\ToslaPosRequestValueFormatter;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\ToslaPos;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(ToslaPosRequestValueFormatter::class)]
class ToslaPosRequestValueFormatterTest extends TestCase
{
    private ToslaPosRequestValueFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new ToslaPosRequestValueFormatter();
    }

    public function testSupports(): void
    {
        $result = $this->formatter::supports(ToslaPos::class);
        $this->assertTrue($result);

        $result = $this->formatter::supports(AssecoPos::class);
        $this->assertFalse($result);
    }


    #[TestWith([0, 0])]
    #[TestWith([1, 0])]
    #[TestWith([2, 2])]
    public function testFormatInstallment(int $installment, int $expected): void
    {
        $actual = $this->formatter->formatInstallment($installment);
        $this->assertSame($expected, $actual);
    }

    #[TestWith([1.1, 110])]
    #[TestWith([1, 100])]
    public function testFormatAmount(float $amount, $expected): void
    {
        $actual = $this->formatter->formatAmount($amount);
        $this->assertSame($expected, $actual);
    }

    #[TestWith(['ExpireDate', '04/24'])]
    #[TestWith(['expireDate', '0424'])]
    public function testFormatCreditCardExpDate(string $fieldName, string $expected): void
    {
        $expDate = new \DateTime('2024-04-14T16:45:30.000');
        $actual = $this->formatter->formatCardExpDate($expDate, $fieldName);
        $this->assertSame($expected, $actual);
    }

    #[TestWith(['abc'])]
    #[TestWith([''])]
    public function testFormatCreditCardExpDateUnSupportedField(string $fieldName): void
    {
        $expDate = new \DateTime('2024-04-14T16:45:30.000');
        $this->expectException(\InvalidArgumentException::class);
        $this->formatter->formatCardExpDate($expDate, $fieldName);
    }

    /**
     * @dataProvider formatDateTimeDataProvider
     */
    public function testFormatDateTime(?string $fieldName, string $expected): void
    {
        $dateTime = new \DateTime('2024-04-14T16:45:30.000');
        $actual = $this->formatter->formatDateTime($dateTime, $fieldName);
        $this->assertSame($expected, $actual);
    }

    #[TestWith(['abc'])]
    #[TestWith([null])]
    #[TestWith([''])]
    public function testFormatDateTimeUnsupportedField(?string $fieldName): void
    {
        $dateTime = new \DateTime('2024-04-14T16:45:30.000');
        $this->expectException(\InvalidArgumentException::class);
        $this->formatter->formatDateTime($dateTime, $fieldName);
    }

    public static function formatDateTimeDataProvider(): array
    {
        return [
            [
                'timeSpan',
                '20240414164530',
            ],
            [
                'transactionDate',
                '20240414',
            ],
        ];
    }
}
