<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\Request\ValueFormatter;

use DateTimeImmutable;
use InvalidArgumentException;
use Mews\Pos\DataMapper\Request\ValueFormatter\PayTrPosRequestValueFormatter;
use Mews\Pos\Gateway\AkbankPos;
use Mews\Pos\Gateway\PayTrPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(PayTrPosRequestValueFormatter::class)]
class PayTrPosRequestValueFormatterTest extends TestCase
{
    private PayTrPosRequestValueFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new PayTrPosRequestValueFormatter();
    }

    public function testSupports(): void
    {
        $this->assertTrue($this->formatter::supports(PayTrPos::class));
        $this->assertFalse($this->formatter::supports(AkbankPos::class));
    }

    #[TestWith([0, 0])]
    #[TestWith([1, 0])]
    #[TestWith([2, 2])]
    #[TestWith([12, 12])]
    public function testFormatInstallment(int $installment, int $expected): void
    {
        $this->assertSame($expected, $this->formatter->formatInstallment($installment));
    }

    #[DataProvider('formatAmountDataProvider')]
    public function testFormatAmount(float $amount, ?string $txType, int|string $expected): void
    {
        $this->assertSame($expected, $this->formatter->formatAmount($amount, $txType));
    }

    #[DataProvider('formatCardExpDateDataProvider')]
    public function testFormatCardExpDate(string $fieldName, string $expected): void
    {
        $expDate = new DateTimeImmutable('2030-12-01');
        $this->assertSame($expected, $this->formatter->formatCardExpDate($expDate, $fieldName));
    }

    public function testFormatCardExpDateThrowsOnUnsupportedField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported field name "invalid_field"');
        $this->formatter->formatCardExpDate(new DateTimeImmutable(), 'invalid_field');
    }

    public function testFormatDateTime(): void
    {
        $dt = new DateTimeImmutable('2025-06-23 14:30:00');
        $this->assertSame('2025-06-23 14:30:00', $this->formatter->formatDateTime($dt, 'anyField'));
    }

    public static function formatAmountDataProvider(): array
    {
        return [
            // iFrame API: integer × 100
            'iframe_whole_number'   => [10.50, PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD, 1050],
            'iframe_rounds_to_int'  => [10.005, PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD, 1001],
            // Direct payment and refund: decimal string
            'non_secure_string'     => [10.50, PosInterface::TX_TYPE_PAY_AUTH, '10.50'],
            'refund_string'         => [9.99, PosInterface::TX_TYPE_REFUND, '9.99'],
            'null_tx_type_string'   => [50.00, null, '50.00'],
        ];
    }

    public static function formatCardExpDateDataProvider(): array
    {
        return [
            'expiry_month' => ['expiry_month', '12'],
            'expiry_year'  => ['expiry_year',  '30'],
        ];
    }
}
