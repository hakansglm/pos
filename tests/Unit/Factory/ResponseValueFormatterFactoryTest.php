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
use Mews\Pos\DataMapper\Response\ValueFormatter\PosNetPosResponseValueFormatter;
use Mews\Pos\DataMapper\Response\ValueFormatter\ToslaPosResponseValueFormatter;
use Mews\Pos\Factory\ResponseValueFormatterFactory;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\Gateways\AssecoPos;
use Mews\Pos\Gateways\GarantiPos;
use Mews\Pos\Gateways\InterPos;
use Mews\Pos\Gateways\KuveytPos;
use Mews\Pos\Gateways\Param3DHostPos;
use Mews\Pos\Gateways\ParamPos;
use Mews\Pos\Gateways\PayFlexCPV4Pos;
use Mews\Pos\Gateways\PayFlexV4Pos;
use Mews\Pos\Gateways\PayForPos;
use Mews\Pos\Gateways\PosNetPos;
use Mews\Pos\Gateways\PosNetV1Pos;
use Mews\Pos\Gateways\ToslaPos;
use Mews\Pos\Gateways\VakifKatilimPos;
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
            [PosNetPos::class, PosNetPosResponseValueFormatter::class],
            [PosNetV1Pos::class, PosNetPosResponseValueFormatter::class],
            [ToslaPos::class, ToslaPosResponseValueFormatter::class],
            [VakifKatilimPos::class, BoaPosResponseValueFormatter::class],
        ];
    }
}
