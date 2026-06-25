<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Factory;

use Mews\Pos\DataMapper\Response\ValueFormatter\BasicResponseValueFormatter;
use Mews\Pos\DataMapper\Response\ValueFormatter\BoaPosResponseValueFormatter;
use Mews\Pos\DataMapper\Response\ValueFormatter\AssecoPosResponseValueFormatter;
use Mews\Pos\DataMapper\Response\ValueFormatter\GarantiPosResponseValueFormatter;
use Mews\Pos\DataMapper\Response\ValueFormatter\InterPosResponseValueFormatter;
use Mews\Pos\DataMapper\Response\ValueFormatter\ParamPosResponseValueFormatter;
use Mews\Pos\DataMapper\Response\ValueFormatter\PayTrPosResponseValueFormatter;
use Mews\Pos\DataMapper\Response\ValueFormatter\PosNetPosResponseValueFormatter;
use Mews\Pos\DataMapper\Response\ValueFormatter\ToslaPosResponseValueFormatter;
use Mews\Pos\Factory\ResponseValueFormatterFactory;
use Mews\Pos\Gateway\AkbankPos;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\GarantiPos;
use Mews\Pos\Gateway\InterPos;
use Mews\Pos\Gateway\KuveytPos;
use Mews\Pos\Gateway\Param3DHostPos;
use Mews\Pos\Gateway\ParamPos;
use Mews\Pos\Gateway\PayFlexCPV4Pos;
use Mews\Pos\Gateway\PayFlexV4Pos;
use Mews\Pos\Gateway\PayForPos;
use Mews\Pos\Gateway\PayTrPos;
use Mews\Pos\Gateway\PosNetPos;
use Mews\Pos\Gateway\PosNetV1Pos;
use Mews\Pos\Gateway\ToslaPos;
use Mews\Pos\Gateway\VakifKatilimPos;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResponseValueFormatterFactory::class)]
class ResponseValueFormatterFactoryTest extends TestCase
{
    /**
     * @dataProvider createForGatewayProvider
     */
    public function testCreateForGateway(string $gatewayClass, string $expectedFormatterClass): void
    {
        $formatter = ResponseValueFormatterFactory::createForGateway($gatewayClass);
        $this->assertInstanceOf($expectedFormatterClass, $formatter);
    }

    public function testCreateForGatewayInvalidGateway(): void
    {
        $this->expectException(\DomainException::class);
        ResponseValueFormatterFactory::createForGateway(\stdClass::class);
    }

    public static function createForGatewayProvider(): array
    {
        return [
            [AkbankPos::class, BasicResponseValueFormatter::class],
            [AssecoPos::class, AssecoPosResponseValueFormatter::class],
            [GarantiPos::class, GarantiPosResponseValueFormatter::class],
            [InterPos::class, InterPosResponseValueFormatter::class],
            [KuveytPos::class, BoaPosResponseValueFormatter::class],
            [ParamPos::class, ParamPosResponseValueFormatter::class],
            [Param3DHostPos::class, ParamPosResponseValueFormatter::class],
            [PayFlexCPV4Pos::class, BasicResponseValueFormatter::class],
            [PayFlexV4Pos::class, BasicResponseValueFormatter::class],
            [PayForPos::class, BasicResponseValueFormatter::class],
            [PayTrPos::class, PayTrPosResponseValueFormatter::class],
            [PosNetPos::class, PosNetPosResponseValueFormatter::class],
            [PosNetV1Pos::class, PosNetPosResponseValueFormatter::class],
            [ToslaPos::class, ToslaPosResponseValueFormatter::class],
            [VakifKatilimPos::class, BoaPosResponseValueFormatter::class],
        ];
    }
}
