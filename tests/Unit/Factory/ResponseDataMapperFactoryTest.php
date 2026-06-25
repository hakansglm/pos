<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Factory;

use PHPUnit\Framework\Attributes\DataProvider;
use DomainException;
use stdClass;
use Mews\Pos\Gateway\AkbankPos;
use Mews\Pos\DataMapper\Response\Mapper\AkbankPosResponseDataMapper;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\DataMapper\Response\Mapper\AssecoPosResponseDataMapper;
use Mews\Pos\Gateway\GarantiPos;
use Mews\Pos\DataMapper\Response\Mapper\GarantiPosResponseDataMapper;
use Mews\Pos\Gateway\InterPos;
use Mews\Pos\DataMapper\Response\Mapper\InterPosResponseDataMapper;
use Mews\Pos\Gateway\KuveytPos;
use Mews\Pos\DataMapper\Response\Mapper\KuveytPosResponseDataMapper;
use Mews\Pos\Gateway\ParamPos;
use Mews\Pos\DataMapper\Response\Mapper\ParamPosResponseDataMapper;
use Mews\Pos\Gateway\Param3DHostPos;
use Mews\Pos\Gateway\PayFlexCPV4Pos;
use Mews\Pos\DataMapper\Response\Mapper\PayFlexCPV4PosResponseDataMapper;
use Mews\Pos\Gateway\PayFlexV4Pos;
use Mews\Pos\DataMapper\Response\Mapper\PayFlexV4PosResponseDataMapper;
use Mews\Pos\Gateway\PayForPos;
use Mews\Pos\DataMapper\Response\Mapper\PayForPosResponseDataMapper;
use Mews\Pos\Gateway\PayTrPos;
use Mews\Pos\DataMapper\Response\Mapper\PayTrPosResponseDataMapper;
use Mews\Pos\Gateway\PosNetPos;
use Mews\Pos\DataMapper\Response\Mapper\PosNetPosResponseDataMapper;
use Mews\Pos\Gateway\PosNetV1Pos;
use Mews\Pos\DataMapper\Response\Mapper\PosNetV1PosResponseDataMapper;
use Mews\Pos\Gateway\ToslaPos;
use Mews\Pos\DataMapper\Response\Mapper\ToslaPosResponseDataMapper;
use Mews\Pos\Gateway\VakifKatilimPos;
use Mews\Pos\DataMapper\Response\Mapper\VakifKatilimPosResponseDataMapper;
use Mews\Pos\DataMapper\Response\ValueFormatter\ResponseValueFormatterInterface;
use Mews\Pos\DataMapper\Response\ValueMapper\ResponseValueMapperInterface;
use Mews\Pos\Factory\ResponseDataMapperFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(ResponseDataMapperFactory::class)]
class ResponseDataMapperFactoryTest extends TestCase
{
    #[DataProvider('createForGatewayProvider')]
    public function testCreateForGateway(string $gatewayClass, string $mapperClass): void
    {
        $responseDataMapper     = $this->createMock(ResponseValueMapperInterface::class);
        $responseValueFormatter = $this->createMock(ResponseValueFormatterInterface::class);
        $logger                 = $this->createMock(LoggerInterface::class);
        $mapper                 = ResponseDataMapperFactory::createForGateway(
            $gatewayClass,
            $responseValueFormatter,
            $responseDataMapper,
            $logger
        );
        $this->assertInstanceOf($mapperClass, $mapper);
    }

    public function testCreateForGatewayUnsupported(): void
    {
        $responseDataMapper     = $this->createMock(ResponseValueMapperInterface::class);
        $responseValueFormatter = $this->createMock(ResponseValueFormatterInterface::class);
        $logger                 = $this->createMock(LoggerInterface::class);
        $this->expectException(DomainException::class);
        ResponseDataMapperFactory::createForGateway(
            stdClass::class,
            $responseValueFormatter,
            $responseDataMapper,
            $logger
        );
    }

    public static function createForGatewayProvider(): array
    {
        return [
            [AkbankPos::class, AkbankPosResponseDataMapper::class],
            [AssecoPos::class, AssecoPosResponseDataMapper::class],
            [GarantiPos::class, GarantiPosResponseDataMapper::class],
            [InterPos::class, InterPosResponseDataMapper::class],
            [KuveytPos::class, KuveytPosResponseDataMapper::class],
            [ParamPos::class, ParamPosResponseDataMapper::class],
            [Param3DHostPos::class, ParamPosResponseDataMapper::class],
            [PayFlexCPV4Pos::class, PayFlexCPV4PosResponseDataMapper::class],
            [PayFlexV4Pos::class, PayFlexV4PosResponseDataMapper::class],
            [PayForPos::class, PayForPosResponseDataMapper::class],
            [PayTrPos::class, PayTrPosResponseDataMapper::class],
            [PosNetPos::class, PosNetPosResponseDataMapper::class],
            [PosNetV1Pos::class, PosNetV1PosResponseDataMapper::class],
            [ToslaPos::class, ToslaPosResponseDataMapper::class],
            [VakifKatilimPos::class, VakifKatilimPosResponseDataMapper::class],
        ];
    }
}
