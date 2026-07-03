<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Factory;

use Mews\Pos\DataMapper\PosQuery\Response\AkbankPosQueryResponseDataMapper;
use Mews\Pos\DataMapper\PosQuery\Response\GarantiPosQueryResponseDataMapper;
use Mews\Pos\DataMapper\PosQuery\Response\IyzicoPosQueryResponseDataMapper;
use Mews\Pos\DataMapper\PosQuery\Response\ParamPosQueryResponseDataMapper;
use Mews\Pos\DataMapper\PosQuery\Response\PayForPosQueryResponseDataMapper;
use Mews\Pos\DataMapper\PosQuery\Response\PayTrPosQueryResponseDataMapper;
use Mews\Pos\DataMapper\PosQuery\Response\ToslaPosQueryResponseDataMapper;
use Mews\Pos\DataMapper\PosQuery\Response\VakifKatilimPosQueryResponseDataMapper;
use Mews\Pos\Factory\PosQueryResponseMapperFactory;
use Mews\Pos\Factory\ResponseValueFormatterFactory;
use Mews\Pos\Factory\ResponseValueMapperFactory;
use Mews\Pos\Gateway\AkbankPos;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\GarantiPos;
use Mews\Pos\Gateway\IyzicoPos;
use Mews\Pos\Gateway\ParamPos;
use Mews\Pos\Gateway\PayForPos;
use Mews\Pos\Gateway\PayTrPos;
use Mews\Pos\Gateway\ToslaPos;
use Mews\Pos\Gateway\VakifKatilimPos;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(PosQueryResponseMapperFactory::class)]
class PosQueryResponseMapperFactoryTest extends TestCase
{
    #[DataProvider('gatewayClassDataProvider')]
    public function testCreateForGateway(string $gatewayClass, string $expectedMapperClass): void
    {
        $mapper = PosQueryResponseMapperFactory::createForGateway(
            $gatewayClass,
            ResponseValueFormatterFactory::createForGateway($gatewayClass),
            ResponseValueMapperFactory::createForGateway($gatewayClass),
            new NullLogger()
        );

        $this->assertInstanceOf($expectedMapperClass, $mapper);
    }

    public function testCreateForGatewayReturnsNullForUnregisteredGateway(): void
    {
        $this->assertNull(
            PosQueryResponseMapperFactory::createForGateway(
                AssecoPos::class,
                ResponseValueFormatterFactory::createForGateway(AssecoPos::class),
                ResponseValueMapperFactory::createForGateway(AssecoPos::class),
                new NullLogger()
            )
        );
    }

    public static function gatewayClassDataProvider(): array
    {
        return [
            [AkbankPos::class,      AkbankPosQueryResponseDataMapper::class],
            [GarantiPos::class,     GarantiPosQueryResponseDataMapper::class],
            [IyzicoPos::class,      IyzicoPosQueryResponseDataMapper::class],
            [ParamPos::class,       ParamPosQueryResponseDataMapper::class],
            [PayForPos::class,      PayForPosQueryResponseDataMapper::class],
            [PayTrPos::class,       PayTrPosQueryResponseDataMapper::class],
            [ToslaPos::class,       ToslaPosQueryResponseDataMapper::class],
            [VakifKatilimPos::class, VakifKatilimPosQueryResponseDataMapper::class],
        ];
    }
}
