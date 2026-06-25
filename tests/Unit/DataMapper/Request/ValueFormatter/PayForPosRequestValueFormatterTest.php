<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\Request\ValueFormatter;

use DateTime;
use Mews\Pos\DataMapper\Request\ValueFormatter\PayForPosRequestValueFormatter;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\PayForPos;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(PayForPosRequestValueFormatter::class)]
class PayForPosRequestValueFormatterTest extends TestCase
{
    private PayForPosRequestValueFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new PayForPosRequestValueFormatter();
    }

    public function testSupports(): void
    {
        $result = $this->formatter::supports(PayForPos::class);
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

    #[TestWith([1.1, '1.1'])]
    #[TestWith([1.0, '1'])]
    public function testFormatAmount(float $amount, $expected): void
    {
        $actual = $this->formatter->formatAmount($amount);
        $this->assertSame($expected, $actual);
    }

    #[TestWith(['', '0424'])]
    #[TestWith(['abc', '0424'])]
    public function testFormatCreditCardExpDate(string $fieldName, string $expected): void
    {
        $expDate = new DateTime('2024-04-14T16:45:30.000');
        $actual = $this->formatter->formatCardExpDate($expDate, $fieldName);
        $this->assertSame($expected, $actual);
    }

    public function testFormatDateTime(): void
    {
        $dateTime = new DateTime('2024-04-14T16:45:30.000');
        $actual = $this->formatter->formatDateTime($dateTime);
        $this->assertSame('20240414', $actual);
    }
}
