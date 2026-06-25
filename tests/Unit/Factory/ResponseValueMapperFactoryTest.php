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
use Mews\Pos\DataMapper\Response\ValueMapper\PayTrPosResponseValueMapper;
use Mews\Pos\DataMapper\Response\ValueMapper\PosNetPosResponseValueMapper;
use Mews\Pos\DataMapper\Response\ValueMapper\PosNetV1PosResponseValueMapper;
use Mews\Pos\DataMapper\Response\ValueMapper\ToslaPosResponseValueMapper;
use Mews\Pos\DataMapper\Response\ValueMapper\VakifKatilimPosResponseValueMapper;
use Mews\Pos\Factory\ResponseValueMapperFactory;
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
            [PayTrPos::class, PayTrPosResponseValueMapper::class],
            [PosNetPos::class, PosNetPosResponseValueMapper::class],
            [PosNetV1Pos::class, PosNetV1PosResponseValueMapper::class],
            [ToslaPos::class, ToslaPosResponseValueMapper::class],
            [VakifKatilimPos::class, VakifKatilimPosResponseValueMapper::class],
        ];
    }
}
