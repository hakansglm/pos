<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Factory;

use PHPUnit\Framework\Attributes\DataProvider;
use Psr\EventDispatcher\EventDispatcherInterface;
use Mews\Pos\Crypt\CryptInterface;
use DomainException;
use stdClass;
use Mews\Pos\Gateway\AkbankPos;
use Mews\Pos\DataMapper\Request\Mapper\AkbankPosRequestDataMapper;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\DataMapper\Request\Mapper\AssecoPosRequestDataMapper;
use Mews\Pos\Gateway\GarantiPos;
use Mews\Pos\DataMapper\Request\Mapper\GarantiPosRequestDataMapper;
use Mews\Pos\Gateway\InterPos;
use Mews\Pos\DataMapper\Request\Mapper\InterPosRequestDataMapper;
use Mews\Pos\Gateway\KuveytPos;
use Mews\Pos\DataMapper\Request\Mapper\KuveytPosRequestDataMapper;
use Mews\Pos\Gateway\ParamPos;
use Mews\Pos\DataMapper\Request\Mapper\ParamPosRequestDataMapper;
use Mews\Pos\Gateway\Param3DHostPos;
use Mews\Pos\DataMapper\Request\Mapper\Param3DHostPosRequestDataMapper;
use Mews\Pos\Gateway\PayFlexCPV4Pos;
use Mews\Pos\DataMapper\Request\Mapper\PayFlexCPV4PosRequestDataMapper;
use Mews\Pos\Gateway\PayForPos;
use Mews\Pos\DataMapper\Request\Mapper\PayForPosRequestDataMapper;
use Mews\Pos\Gateway\PosNetPos;
use Mews\Pos\DataMapper\Request\Mapper\PosNetPosRequestDataMapper;
use Mews\Pos\Gateway\PosNetV1Pos;
use Mews\Pos\DataMapper\Request\Mapper\PosNetV1PosRequestDataMapper;
use Mews\Pos\Gateway\ToslaPos;
use Mews\Pos\DataMapper\Request\Mapper\ToslaPosRequestDataMapper;
use Mews\Pos\Gateway\VakifKatilimPos;
use Mews\Pos\DataMapper\Request\Mapper\VakifKatilimPosRequestDataMapper;
use Mews\Pos\DataMapper\Request\ValueFormatter\RequestValueFormatterInterface;
use Mews\Pos\DataMapper\Request\ValueMapper\RequestValueMapperInterface;
use Mews\Pos\Factory\RequestDataMapperFactory;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequestDataMapperFactory::class)]
class RequestDataMapperFactoryTest extends TestCase
{
    #[DataProvider('createForGatewayDataProvider')]
    public function testCreateForGateway(string $gatewayClass, string $mapperClass): void
    {
        $valueMapper     = $this->createMock(RequestValueMapperInterface::class);
        $valueFormatter  = $this->createMock(RequestValueFormatterInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $crypt           = $this->createMock(CryptInterface::class);
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
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $crypt           = $this->createMock(CryptInterface::class);
        $this->expectException(DomainException::class);
        RequestDataMapperFactory::createForGateway(
            stdClass::class,
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
            [AkbankPos::class, AkbankPosRequestDataMapper::class],
            [AssecoPos::class, AssecoPosRequestDataMapper::class],
            [GarantiPos::class, GarantiPosRequestDataMapper::class],
            [InterPos::class, InterPosRequestDataMapper::class],
            [KuveytPos::class, KuveytPosRequestDataMapper::class],
            [ParamPos::class, ParamPosRequestDataMapper::class],
            [Param3DHostPos::class, Param3DHostPosRequestDataMapper::class],
            [PayFlexCPV4Pos::class, PayFlexCPV4PosRequestDataMapper::class],
            [PayForPos::class, PayForPosRequestDataMapper::class],
            [PosNetPos::class, PosNetPosRequestDataMapper::class],
            [PosNetV1Pos::class, PosNetV1PosRequestDataMapper::class],
            [ToslaPos::class, ToslaPosRequestDataMapper::class],
            [VakifKatilimPos::class, VakifKatilimPosRequestDataMapper::class],
        ];
    }
}
