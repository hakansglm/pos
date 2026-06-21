<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Factory;

use Mews\Pos\DataMapper\Response\ValueMapper\AkbankPosResponseValueMapper;
use Mews\Pos\DataMapper\Response\ValueMapper\KuveytPosResponseValueMapper;
use Mews\Pos\DataMapper\Response\ValueMapper\AssecoPosResponseValueMapper;
use Mews\Pos\DataMapper\Response\ValueMapper\GarantiPosResponseValueMapper;
use Mews\Pos\DataMapper\Response\ValueMapper\InterPosResponseValueMapper;
use Mews\Pos\DataMapper\Response\ValueMapper\ParamPosResponseValueMapper;
use Mews\Pos\DataMapper\Response\ValueMapper\PayFlexCPV4PosResponseValueMapper;
use Mews\Pos\DataMapper\Response\ValueMapper\PayFlexV4PosResponseValueMapper;
use Mews\Pos\DataMapper\Response\ValueMapper\PayForPosResponseValueMapper;
use Mews\Pos\DataMapper\Response\ValueMapper\PosNetPosResponseValueMapper;
use Mews\Pos\DataMapper\Response\ValueMapper\PosNetV1PosResponseValueMapper;
use Mews\Pos\DataMapper\Response\ValueMapper\ToslaPosResponseValueMapper;
use Mews\Pos\DataMapper\Response\ValueMapper\VakifKatilimPosResponseValueMapper;
use Mews\Pos\Factory\ResponseValueMapperFactory;
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

#[CoversClass(ResponseValueMapperFactory::class)]
class ResponseValueMapperFactoryTest extends TestCase
{
    /**
     * @dataProvider gatewayClassDataProvider
     */
    public function testCreateForGateway(
        string $gatewayClass,
        string $expectedMapperClass
    ): void {
        $this->assertInstanceOf(
            $expectedMapperClass,
            ResponseValueMapperFactory::createForGateway($gatewayClass)
        );
    }

    public function testCreateForGatewayInvalidGateway(): void
    {
        $this->expectException(\DomainException::class);
        ResponseValueMapperFactory::createForGateway(\stdClass::class);
    }

    public static function gatewayClassDataProvider(): array
    {
        return [
            [AkbankPos::class, AkbankPosResponseValueMapper::class],
            [AssecoPos::class, AssecoPosResponseValueMapper::class],
            [GarantiPos::class, GarantiPosResponseValueMapper::class],
            [InterPos::class, InterPosResponseValueMapper::class],
            [KuveytPos::class, KuveytPosResponseValueMapper::class],
            [Param3DHostPos::class, ParamPosResponseValueMapper::class],
            [ParamPos::class, ParamPosResponseValueMapper::class],
            [PayForPos::class, PayForPosResponseValueMapper::class],
            [PayFlexV4Pos::class, PayFlexV4PosResponseValueMapper::class],
            [PayFlexCPV4Pos::class, PayFlexCPV4PosResponseValueMapper::class],
            [PosNetPos::class, PosNetPosResponseValueMapper::class],
            [PosNetV1Pos::class, PosNetV1PosResponseValueMapper::class],
            [ToslaPos::class, ToslaPosResponseValueMapper::class],
            [VakifKatilimPos::class, VakifKatilimPosResponseValueMapper::class],
        ];
    }
}
