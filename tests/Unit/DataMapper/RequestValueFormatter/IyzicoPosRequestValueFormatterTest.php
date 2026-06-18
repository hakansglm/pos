<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestValueFormatter;

use Mews\Pos\DataMapper\RequestValueFormatter\IyzicoPosRequestValueFormatter;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\Gateways\IyzicoPos;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(IyzicoPosRequestValueFormatter::class)]
class IyzicoPosRequestValueFormatterTest extends TestCase
{
    private IyzicoPosRequestValueFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new IyzicoPosRequestValueFormatter();
    }

    public function testSupports(): void
    {
        $this->assertTrue($this->formatter::supports(IyzicoPos::class));
        $this->assertFalse($this->formatter::supports(AkbankPos::class));
    }

    #[TestWith([0, 1])]
    #[TestWith([1, 1])]
    #[TestWith([2, 2])]
    #[TestWith([12, 12])]
    public function testFormatInstallment(int $installment, int $expected): void
    {
        $this->assertSame($expected, $this->formatter->formatInstallment($installment));
    }

    #[TestWith([1.0, 1.0])]
    #[TestWith([100.25, 100.25])]
    #[TestWith([0.0, 0.0])]
    public function testFormatAmount(float $amount, float $expected): void
    {
        $this->assertSame($expected, $this->formatter->formatAmount($amount));
    }

    /**
     * @dataProvider formatCardExpDateDataProvider
     */
    public function testFormatCardExpDate(string $fieldName, string $expected): void
    {
        $expDate = new \DateTimeImmutable('2024-04-01');
        $actual  = $this->formatter->formatCardExpDate($expDate, $fieldName);

        $this->assertSame($expected, $actual);
    }

    public function testFormatCardExpDateUnsupportedField(): void
    {
        $expDate = new \DateTimeImmutable('2024-04-01');

        $this->expectException(\InvalidArgumentException::class);
        $this->formatter->formatCardExpDate($expDate, 'unsupportedField');
    }

    #[TestWith(['someField', '2024-06-15'])]
    public function testFormatDateTime(string $fieldName, string $expected): void
    {
        $dateTime = new \DateTimeImmutable('2024-06-15');
        $this->assertSame($expected, $this->formatter->formatDateTime($dateTime, $fieldName));
    }

    public static function formatCardExpDateDataProvider(): array
    {
        return [
            'expireMonth' => ['expireMonth', '04'],
            'expireYear'  => ['expireYear',  '2024'],
        ];
    }
}
