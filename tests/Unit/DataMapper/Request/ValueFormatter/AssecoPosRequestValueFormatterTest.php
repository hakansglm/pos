<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\Request\ValueFormatter;

use Mews\Pos\DataMapper\Request\ValueFormatter\AssecoPosRequestValueFormatter;
use Mews\Pos\Exception\NotImplementedException;
use Mews\Pos\Gateway\AkbankPos;
use Mews\Pos\Gateway\AssecoPos;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(AssecoPosRequestValueFormatter::class)]
class AssecoPosRequestValueFormatterTest extends TestCase
{
    private AssecoPosRequestValueFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new AssecoPosRequestValueFormatter();
    }

    public function testSupports(): void
    {
        $result = $this->formatter::supports(AssecoPos::class);
        $this->assertTrue($result);

        $result = $this->formatter::supports(AkbankPos::class);
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

    #[TestWith([1.1, '1.1'])]
    #[TestWith([1.0, '1'])]
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

    #[TestWith(['Ecom_Payment_Card_ExpDate_Month', '04'])]
    #[TestWith(['Ecom_Payment_Card_ExpDate_Year', '24'])]
    #[TestWith(['Expires', '04/24'])]
    public function testFormatCreditCardExpDate(string $fieldName, string $expected): void
    {
        $expDate = new \DateTime('2024-04-14T16:45:30.000');
        $actual = $this->formatter->formatCardExpDate($expDate, $fieldName);
        $this->assertSame($expected, $actual);
    }

    public function testFormatDateTime(): void
    {
        $dateTime = new \DateTime('2024-04-14T16:45:30.000');
        $this->expectException(NotImplementedException::class);
        $this->formatter->formatDateTime($dateTime);
    }
}
