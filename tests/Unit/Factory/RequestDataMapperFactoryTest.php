<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Factory;

use Mews\Pos\DataMapper\Request\ValueFormatter\RequestValueFormatterInterface;
use Mews\Pos\DataMapper\Request\ValueMapper\RequestValueMapperInterface;
use Mews\Pos\Factory\RequestDataMapperFactory;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequestDataMapperFactory::class)]
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
            [\Mews\Pos\Gateways\AkbankPos::class, \Mews\Pos\DataMapper\Request\Mapper\AkbankPosRequestDataMapper::class],
            [\Mews\Pos\Gateways\AssecoPos::class, \Mews\Pos\DataMapper\Request\Mapper\AssecoPosRequestDataMapper::class],
            [\Mews\Pos\Gateways\GarantiPos::class, \Mews\Pos\DataMapper\Request\Mapper\GarantiPosRequestDataMapper::class],
            [\Mews\Pos\Gateways\InterPos::class, \Mews\Pos\DataMapper\Request\Mapper\InterPosRequestDataMapper::class],
            [\Mews\Pos\Gateways\KuveytPos::class, \Mews\Pos\DataMapper\Request\Mapper\KuveytPosRequestDataMapper::class],
            [\Mews\Pos\Gateways\ParamPos::class, \Mews\Pos\DataMapper\Request\Mapper\ParamPosRequestDataMapper::class],
            [\Mews\Pos\Gateways\Param3DHostPos::class, \Mews\Pos\DataMapper\Request\Mapper\Param3DHostPosRequestDataMapper::class],
            [\Mews\Pos\Gateways\PayFlexCPV4Pos::class, \Mews\Pos\DataMapper\Request\Mapper\PayFlexCPV4PosRequestDataMapper::class],
            [\Mews\Pos\Gateways\PayForPos::class, \Mews\Pos\DataMapper\Request\Mapper\PayForPosRequestDataMapper::class],
            [\Mews\Pos\Gateways\PosNetPos::class, \Mews\Pos\DataMapper\Request\Mapper\PosNetPosRequestDataMapper::class],
            [\Mews\Pos\Gateways\PosNetV1Pos::class, \Mews\Pos\DataMapper\Request\Mapper\PosNetV1PosRequestDataMapper::class],
            [\Mews\Pos\Gateways\ToslaPos::class, \Mews\Pos\DataMapper\Request\Mapper\ToslaPosRequestDataMapper::class],
            [\Mews\Pos\Gateways\VakifKatilimPos::class, \Mews\Pos\DataMapper\Request\Mapper\VakifKatilimPosRequestDataMapper::class],
        ];
    }
}
