<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\Request\ValueFormatter;

use Mews\Pos\DataMapper\Request\ValueFormatter\PayFlexV4PosRequestValueFormatter;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\PayFlexV4Pos;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(PayFlexV4PosRequestValueFormatter::class)]
class PayFlexV4PosRequestValueFormatterTest extends TestCase
{
    private PayFlexV4PosRequestValueFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new PayFlexV4PosRequestValueFormatter();
    }

    public function testSupports(): void
    {
        $result = $this->formatter::supports(PayFlexV4Pos::class);
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

    #[TestWith([1, '1.00'])]
    #[TestWith([1.1, '1.10'])]
    public function testFormatAmount(float $amount, $expected): void
    {
        $actual = $this->formatter->formatAmount($amount);
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

    #[TestWith(['ExpiryDate', '2404'])]
    #[TestWith(['Expiry', '202404'])]
    public function testFormatCreditCardExpDate(string $fieldName, string $expected): void
    {
        $expDate = new \DateTime('2024-04-14T16:45:30.000');
        $actual = $this->formatter->formatCardExpDate($expDate, $fieldName);
        $this->assertSame($expected, $actual);
    }

    public function testFormatDateTime(): void
    {
        $dateTime = new \DateTime('2024-04-14T16:45:30.000');
        $actual = $this->formatter->formatDateTime($dateTime);
        $this->assertSame('20240414', $actual);
    }
}
