<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Factory;

use Mews\Pos\DataMapper\Response\ValueFormatter\ResponseValueFormatterInterface;
use Mews\Pos\DataMapper\Response\ValueMapper\ResponseValueMapperInterface;
use Mews\Pos\Factory\ResponseDataMapperFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(ResponseDataMapperFactory::class)]
class ResponseDataMapperFactoryTest extends TestCase
{
    /**
     * @dataProvider createForGatewayProvider
     */
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
        $this->expectException(\DomainException::class);
        ResponseDataMapperFactory::createForGateway(
            \stdClass::class,
            $responseValueFormatter,
            $responseDataMapper,
            $logger
        );
    }

    public static function createForGatewayProvider(): array
    {
        return [
            [\Mews\Pos\Gateways\AkbankPos::class, \Mews\Pos\DataMapper\Response\Mapper\AkbankPosResponseDataMapper::class],
            [\Mews\Pos\Gateways\AssecoPos::class, \Mews\Pos\DataMapper\Response\Mapper\AssecoPosResponseDataMapper::class],
            [\Mews\Pos\Gateways\GarantiPos::class, \Mews\Pos\DataMapper\Response\Mapper\GarantiPosResponseDataMapper::class],
            [\Mews\Pos\Gateways\InterPos::class, \Mews\Pos\DataMapper\Response\Mapper\InterPosResponseDataMapper::class],
            [\Mews\Pos\Gateways\KuveytPos::class, \Mews\Pos\DataMapper\Response\Mapper\KuveytPosResponseDataMapper::class],
            [\Mews\Pos\Gateways\ParamPos::class, \Mews\Pos\DataMapper\Response\Mapper\ParamPosResponseDataMapper::class],
            [\Mews\Pos\Gateways\Param3DHostPos::class, \Mews\Pos\DataMapper\Response\Mapper\ParamPosResponseDataMapper::class],
            [\Mews\Pos\Gateways\PayFlexCPV4Pos::class, \Mews\Pos\DataMapper\Response\Mapper\PayFlexCPV4PosResponseDataMapper::class],
            [\Mews\Pos\Gateways\PayFlexV4Pos::class, \Mews\Pos\DataMapper\Response\Mapper\PayFlexV4PosResponseDataMapper::class],
            [\Mews\Pos\Gateways\PayForPos::class, \Mews\Pos\DataMapper\Response\Mapper\PayForPosResponseDataMapper::class],
            [\Mews\Pos\Gateways\PosNetPos::class, \Mews\Pos\DataMapper\Response\Mapper\PosNetPosResponseDataMapper::class],
            [\Mews\Pos\Gateways\PosNetV1Pos::class, \Mews\Pos\DataMapper\Response\Mapper\PosNetV1PosResponseDataMapper::class],
            [\Mews\Pos\Gateways\ToslaPos::class, \Mews\Pos\DataMapper\Response\Mapper\ToslaPosResponseDataMapper::class],
            [\Mews\Pos\Gateways\VakifKatilimPos::class, \Mews\Pos\DataMapper\Response\Mapper\VakifKatilimPosResponseDataMapper::class],
        ];
    }
}
