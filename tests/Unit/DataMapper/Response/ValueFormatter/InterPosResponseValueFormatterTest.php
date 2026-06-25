<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\Response\ValueFormatter;

use PHPUnit\Framework\Attributes\DataProvider;
use Mews\Pos\Exception\NotImplementedException;
use Mews\Pos\DataMapper\Response\ValueFormatter\InterPosResponseValueFormatter;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\InterPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InterPosResponseValueFormatter::class)]
class InterPosResponseValueFormatterTest extends TestCase
{
    private InterPosResponseValueFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new InterPosResponseValueFormatter();
    }

    public function testSupports(): void
    {
        $result = $this->formatter::supports(InterPos::class);
        $this->assertTrue($result);

        $result = $this->formatter::supports(AssecoPos::class);
        $this->assertFalse($result);
    }

    #[DataProvider('formatAmountProvider')]
    public function testFormatAmount(string $amount, string $txType, float $expected): void
    {
        $actual = $this->formatter->formatAmount($amount, $txType);
        $this->assertSame($expected, $actual);
    }

    public function testFormatInstallment(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->formatter->formatInstallment("2", PosInterface::TX_TYPE_PAY_AUTH);
    }

    public static function formatAmountProvider(): array
    {
        return [
            ['0', '', 0.0],
            ['1.056,2', '', 1056.2],
            ['1,01', '', 1.01],
        ];
    }
}
