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
use Mews\Pos\Gateway\AkbankPos;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\GarantiPos;
use Mews\Pos\Gateway\InterPos;
use Mews\Pos\Gateway\KuveytPos;
use Mews\Pos\Gateway\Param3DHostPos;
use Mews\Pos\Gateway\ParamPos;
use Mews\Pos\Gateway\PayFlexCPV4Pos;
use Mews\Pos\Gateway\PayForPos;
use Mews\Pos\Gateway\PosNetPos;
use Mews\Pos\Gateway\PosNetV1Pos;
use Mews\Pos\Gateway\ToslaPos;
use Mews\Pos\Gateway\VakifKatilimPos;
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
