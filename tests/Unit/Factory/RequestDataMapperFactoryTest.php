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
            [\Mews\Pos\Gateway\AkbankPos::class, \Mews\Pos\DataMapper\Request\Mapper\AkbankPosRequestDataMapper::class],
            [\Mews\Pos\Gateway\AssecoPos::class, \Mews\Pos\DataMapper\Request\Mapper\AssecoPosRequestDataMapper::class],
            [\Mews\Pos\Gateway\GarantiPos::class, \Mews\Pos\DataMapper\Request\Mapper\GarantiPosRequestDataMapper::class],
            [\Mews\Pos\Gateway\InterPos::class, \Mews\Pos\DataMapper\Request\Mapper\InterPosRequestDataMapper::class],
            [\Mews\Pos\Gateway\KuveytPos::class, \Mews\Pos\DataMapper\Request\Mapper\KuveytPosRequestDataMapper::class],
            [\Mews\Pos\Gateway\ParamPos::class, \Mews\Pos\DataMapper\Request\Mapper\ParamPosRequestDataMapper::class],
            [\Mews\Pos\Gateway\Param3DHostPos::class, \Mews\Pos\DataMapper\Request\Mapper\Param3DHostPosRequestDataMapper::class],
            [\Mews\Pos\Gateway\PayFlexCPV4Pos::class, \Mews\Pos\DataMapper\Request\Mapper\PayFlexCPV4PosRequestDataMapper::class],
            [\Mews\Pos\Gateway\PayForPos::class, \Mews\Pos\DataMapper\Request\Mapper\PayForPosRequestDataMapper::class],
            [\Mews\Pos\Gateway\PosNetPos::class, \Mews\Pos\DataMapper\Request\Mapper\PosNetPosRequestDataMapper::class],
            [\Mews\Pos\Gateway\PosNetV1Pos::class, \Mews\Pos\DataMapper\Request\Mapper\PosNetV1PosRequestDataMapper::class],
            [\Mews\Pos\Gateway\ToslaPos::class, \Mews\Pos\DataMapper\Request\Mapper\ToslaPosRequestDataMapper::class],
            [\Mews\Pos\Gateway\VakifKatilimPos::class, \Mews\Pos\DataMapper\Request\Mapper\VakifKatilimPosRequestDataMapper::class],
        ];
    }
}
