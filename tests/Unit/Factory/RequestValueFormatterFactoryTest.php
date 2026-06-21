<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Factory;

use Mews\Pos\DataMapper\Request\ValueFormatter\AkbankPosRequestValueFormatter;
use Mews\Pos\DataMapper\Request\ValueFormatter\AssecoPosRequestValueFormatter;
use Mews\Pos\DataMapper\Request\ValueFormatter\GarantiPosRequestValueFormatter;
use Mews\Pos\DataMapper\Request\ValueFormatter\InterPosRequestValueFormatter;
use Mews\Pos\DataMapper\Request\ValueFormatter\KuveytPosRequestValueFormatter;
use Mews\Pos\DataMapper\Request\ValueFormatter\ParamPosRequestValueFormatter;
use Mews\Pos\DataMapper\Request\ValueFormatter\PayFlexCPV4PosRequestValueFormatter;
use Mews\Pos\DataMapper\Request\ValueFormatter\PayForPosRequestValueFormatter;
use Mews\Pos\DataMapper\Request\ValueFormatter\PosNetPosRequestValueFormatter;
use Mews\Pos\DataMapper\Request\ValueFormatter\PosNetV1PosRequestValueFormatter;
use Mews\Pos\DataMapper\Request\ValueFormatter\ToslaPosRequestValueFormatter;
use Mews\Pos\DataMapper\Request\ValueFormatter\VakifKatilimPosRequestValueFormatter;
use Mews\Pos\Factory\RequestValueFormatterFactory;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\Gateways\AssecoPos;
use Mews\Pos\Gateways\GarantiPos;
use Mews\Pos\Gateways\InterPos;
use Mews\Pos\Gateways\KuveytPos;
use Mews\Pos\Gateways\Param3DHostPos;
use Mews\Pos\Gateways\ParamPos;
use Mews\Pos\Gateways\PayFlexCPV4Pos;
use Mews\Pos\Gateways\PayForPos;
use Mews\Pos\Gateways\PosNetPos;
use Mews\Pos\Gateways\PosNetV1Pos;
use Mews\Pos\Gateways\ToslaPos;
use Mews\Pos\Gateways\VakifKatilimPos;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequestValueFormatterFactory::class)]
class RequestValueFormatterFactoryTest extends TestCase
{
    /**
     * @dataProvider gatewayClassDataProvider
     */
    public function testCreateForGateway(string $gatewayClass, string $expectedFormatterClass): void
    {
        $this->assertInstanceOf(
            $expectedFormatterClass,
            RequestValueFormatterFactory::createForGateway($gatewayClass)
        );
    }

    public function testCreateForGatewayInvalidGateway(): void
    {
        $this->expectException(\DomainException::class);
        RequestValueFormatterFactory::createForGateway(\stdClass::class);
    }

    public static function gatewayClassDataProvider(): array
    {
        return [
            [ToslaPos::class, ToslaPosRequestValueFormatter::class],
            [AkbankPos::class, AkbankPosRequestValueFormatter::class],
            [AssecoPos::class, AssecoPosRequestValueFormatter::class],
            [GarantiPos::class, GarantiPosRequestValueFormatter::class],
            [InterPos::class, InterPosRequestValueFormatter::class],
            [KuveytPos::class, KuveytPosRequestValueFormatter::class],
            [VakifKatilimPos::class, VakifKatilimPosRequestValueFormatter::class],
            [ParamPos::class, ParamPosRequestValueFormatter::class],
            [Param3DHostPos::class, ParamPosRequestValueFormatter::class],
            [PayForPos::class, PayForPosRequestValueFormatter::class],
            [PosNetPos::class, PosNetPosRequestValueFormatter::class],
            [PosNetV1Pos::class, PosNetV1PosRequestValueFormatter::class],
            [PayFlexCPV4Pos::class, PayFlexCPV4PosRequestValueFormatter::class],
        ];
    }
}
