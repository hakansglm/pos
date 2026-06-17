<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Factory;

use Mews\Pos\DataMapper\RequestValueFormatter\RequestValueFormatterInterface;
use Mews\Pos\DataMapper\RequestValueMapper\RequestValueMapperInterface;
use Mews\Pos\Factory\RequestDataMapperFactory;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\Factory\RequestDataMapperFactory
 */
class RequestDataMapperFactoryTest extends TestCase
{
    /**
     * @dataProvider createForGatewayDataProvider
     */
    public function testCreateForGateway(string $gatewayClass, string $mapperClass): void
    {
        $valueMapper     = $this->createMock(RequestValueMapperInterface::class);
        $valueFormatter  = $this->createMock(RequestValueFormatterInterface::class);
        $eventDispatcher = $this->createMock(\Psr\EventDispatcher\EventDispatcherInterface::class);
        $crypt           = $this->createMock(\Mews\Pos\Crypt\CryptInterface::class);
        $mapper          = RequestDataMapperFactory::createForGateway(
            $gatewayClass,
            $valueMapper,
            $valueFormatter,
            $eventDispatcher,
            $crypt,
            PosInterface::LANG_EN
        );
        $this->assertInstanceOf($mapperClass, $mapper);
    }

    public function testCreateGatewayRequestMapperUnsupported(): void
    {
        $valueMapper     = $this->createMock(RequestValueMapperInterface::class);
        $valueFormatter  = $this->createMock(RequestValueFormatterInterface::class);
        $eventDispatcher = $this->createMock(\Psr\EventDispatcher\EventDispatcherInterface::class);
        $crypt           = $this->createMock(\Mews\Pos\Crypt\CryptInterface::class);
        $this->expectException(\DomainException::class);
        RequestDataMapperFactory::createForGateway(
            \stdClass::class,
            $valueMapper,
            $valueFormatter,
            $eventDispatcher,
            $crypt,
            PosInterface::LANG_EN
        );
    }

    public static function createForGatewayDataProvider(): array
    {
        return [
            [\Mews\Pos\Gateways\AkbankPos::class, \Mews\Pos\DataMapper\RequestDataMapper\AkbankPosRequestDataMapper::class],
            [\Mews\Pos\Gateways\EstV3Pos::class, \Mews\Pos\DataMapper\RequestDataMapper\EstV3PosRequestDataMapper::class],
            [\Mews\Pos\Gateways\GarantiPos::class, \Mews\Pos\DataMapper\RequestDataMapper\GarantiPosRequestDataMapper::class],
            [\Mews\Pos\Gateways\InterPos::class, \Mews\Pos\DataMapper\RequestDataMapper\InterPosRequestDataMapper::class],
            [\Mews\Pos\Gateways\KuveytPos::class, \Mews\Pos\DataMapper\RequestDataMapper\KuveytPosRequestDataMapper::class],
            [\Mews\Pos\Gateways\ParamPos::class, \Mews\Pos\DataMapper\RequestDataMapper\ParamPosRequestDataMapper::class],
            [\Mews\Pos\Gateways\Param3DHostPos::class, \Mews\Pos\DataMapper\RequestDataMapper\Param3DHostPosRequestDataMapper::class],
            [\Mews\Pos\Gateways\PayFlexCPV4Pos::class, \Mews\Pos\DataMapper\RequestDataMapper\PayFlexCPV4PosRequestDataMapper::class],
            [\Mews\Pos\Gateways\PayForPos::class, \Mews\Pos\DataMapper\RequestDataMapper\PayForPosRequestDataMapper::class],
            [\Mews\Pos\Gateways\PosNetPos::class, \Mews\Pos\DataMapper\RequestDataMapper\PosNetPosRequestDataMapper::class],
            [\Mews\Pos\Gateways\PosNetV1Pos::class, \Mews\Pos\DataMapper\RequestDataMapper\PosNetV1PosRequestDataMapper::class],
            [\Mews\Pos\Gateways\ToslaPos::class, \Mews\Pos\DataMapper\RequestDataMapper\ToslaPosRequestDataMapper::class],
            [\Mews\Pos\Gateways\VakifKatilimPos::class, \Mews\Pos\DataMapper\RequestDataMapper\VakifKatilimPosRequestDataMapper::class],
        ];
    }
}
