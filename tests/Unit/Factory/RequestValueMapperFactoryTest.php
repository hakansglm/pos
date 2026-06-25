<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Factory;

use PHPUnit\Framework\Attributes\DataProvider;
use DomainException;
use stdClass;
use Mews\Pos\DataMapper\Request\ValueMapper\AkbankPosRequestValueMapper;
use Mews\Pos\DataMapper\Request\ValueMapper\AssecoPosRequestValueMapper;
use Mews\Pos\DataMapper\Request\ValueMapper\GarantiPosRequestValueMapper;
use Mews\Pos\DataMapper\Request\ValueMapper\InterPosRequestValueMapper;
use Mews\Pos\DataMapper\Request\ValueMapper\KuveytPosRequestValueMapper;
use Mews\Pos\DataMapper\Request\ValueMapper\ParamPosRequestValueMapper;
use Mews\Pos\DataMapper\Request\ValueMapper\PayFlexCPV4PosRequestValueMapper;
use Mews\Pos\DataMapper\Request\ValueMapper\PayFlexV4PosRequestValueMapper;
use Mews\Pos\DataMapper\Request\ValueMapper\PayForPosRequestValueMapper;
use Mews\Pos\DataMapper\Request\ValueMapper\PosNetPosRequestValueMapper;
use Mews\Pos\DataMapper\Request\ValueMapper\PosNetV1PosRequestValueMapper;
use Mews\Pos\DataMapper\Request\ValueMapper\ToslaPosRequestValueMapper;
use Mews\Pos\DataMapper\Request\ValueMapper\VakifKatilimPosRequestValueMapper;
use Mews\Pos\Factory\RequestValueMapperFactory;
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
use Mews\Pos\Gateway\PosNetPos;
use Mews\Pos\Gateway\PosNetV1Pos;
use Mews\Pos\Gateway\ToslaPos;
use Mews\Pos\Gateway\VakifKatilimPos;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequestValueMapperFactory::class)]
class RequestValueMapperFactoryTest extends TestCase
{
    #[DataProvider('gatewayClassDataProvider')]
    public function testCreateForGateway(string $gatewayClass, string $expectedFormatterClass): void
    {
        $this->assertInstanceOf(
            $expectedFormatterClass,
            RequestValueMapperFactory::createForGateway($gatewayClass)
        );
    }

    public function testCreateForGatewayInvalidGateway(): void
    {
        $this->expectException(DomainException::class);
        RequestValueMapperFactory::createForGateway(stdClass::class);
    }

    public static function gatewayClassDataProvider(): array
    {
        return [
            [AkbankPos::class, AkbankPosRequestValueMapper::class],
            [AssecoPos::class, AssecoPosRequestValueMapper::class],
            [GarantiPos::class, GarantiPosRequestValueMapper::class],
            [InterPos::class, InterPosRequestValueMapper::class],
            [KuveytPos::class, KuveytPosRequestValueMapper::class],
            [ParamPos::class, ParamPosRequestValueMapper::class],
            [Param3DHostPos::class, ParamPosRequestValueMapper::class],
            [PayFlexCPV4Pos::class, PayFlexCPV4PosRequestValueMapper::class],
            [PayFlexV4Pos::class, PayFlexV4PosRequestValueMapper::class],
            [PayForPos::class, PayForPosRequestValueMapper::class],
            [PosNetPos::class, PosNetPosRequestValueMapper::class],
            [PosNetV1Pos::class, PosNetV1PosRequestValueMapper::class],
            [ToslaPos::class, ToslaPosRequestValueMapper::class],
            [VakifKatilimPos::class, VakifKatilimPosRequestValueMapper::class],
        ];
    }
}
