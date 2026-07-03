<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Factory;

use DomainException;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\PosQuery\Request\AkbankPosQueryRequestDataMapper;
use Mews\Pos\DataMapper\PosQuery\Request\AssecoPosQueryRequestDataMapper;
use Mews\Pos\DataMapper\PosQuery\Request\GarantiPosQueryRequestDataMapper;
use Mews\Pos\DataMapper\PosQuery\Request\InterPosQueryRequestDataMapper;
use Mews\Pos\DataMapper\PosQuery\Request\IyzicoPosQueryRequestDataMapper;
use Mews\Pos\DataMapper\PosQuery\Request\ParamPosQueryRequestDataMapper;
use Mews\Pos\DataMapper\PosQuery\Request\PayFlexCPV4PosQueryRequestDataMapper;
use Mews\Pos\DataMapper\PosQuery\Request\PayFlexV4PosQueryRequestDataMapper;
use Mews\Pos\DataMapper\PosQuery\Request\PayForPosQueryRequestDataMapper;
use Mews\Pos\DataMapper\PosQuery\Request\PayTrPosQueryRequestDataMapper;
use Mews\Pos\DataMapper\PosQuery\Request\PosNetPosQueryRequestDataMapper;
use Mews\Pos\DataMapper\PosQuery\Request\PosNetV1PosQueryRequestDataMapper;
use Mews\Pos\DataMapper\PosQuery\Request\ToslaPosQueryRequestDataMapper;
use Mews\Pos\DataMapper\PosQuery\Request\VakifKatilimPosQueryRequestDataMapper;
use Mews\Pos\Factory\PosQueryRequestMapperFactory;
use Mews\Pos\Factory\RequestValueFormatterFactory;
use Mews\Pos\Factory\RequestValueMapperFactory;
use Mews\Pos\Gateway\AkbankPos;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\GarantiPos;
use Mews\Pos\Gateway\InterPos;
use Mews\Pos\Gateway\IyzicoPos;
use Mews\Pos\Gateway\ParamPos;
use Mews\Pos\Gateway\PayFlexCPV4Pos;
use Mews\Pos\Gateway\PayFlexV4Pos;
use Mews\Pos\Gateway\PayForPos;
use Mews\Pos\Gateway\PayTrPos;
use Mews\Pos\Gateway\PosNetPos;
use Mews\Pos\Gateway\PosNetV1Pos;
use Mews\Pos\Gateway\ToslaPos;
use Mews\Pos\Gateway\VakifKatilimPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(PosQueryRequestMapperFactory::class)]
class PosQueryRequestMapperFactoryTest extends TestCase
{
    /** @var CryptInterface & MockObject */
    private MockObject $cryptMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cryptMock = $this->createMock(CryptInterface::class);
    }

    #[DataProvider('gatewayClassDataProvider')]
    public function testCreateForGateway(string $gatewayClass, string $expectedMapperClass): void
    {
        $mapper = PosQueryRequestMapperFactory::createForGateway(
            $gatewayClass,
            RequestValueMapperFactory::createForGateway($gatewayClass),
            RequestValueFormatterFactory::createForGateway($gatewayClass),
            $this->cryptMock,
            PosInterface::LANG_TR
        );

        $this->assertInstanceOf($expectedMapperClass, $mapper);
    }

    public function testCreateForGatewayThrowsForUnknownGateway(): void
    {
        $this->expectException(DomainException::class);

        PosQueryRequestMapperFactory::createForGateway(
            \stdClass::class,
            $this->createMock(\Mews\Pos\DataMapper\Request\ValueMapper\RequestValueMapperInterface::class),
            $this->createMock(\Mews\Pos\DataMapper\Request\ValueFormatter\RequestValueFormatterInterface::class),
            $this->cryptMock,
            PosInterface::LANG_TR
        );
    }

    public static function gatewayClassDataProvider(): array
    {
        return [
            [AkbankPos::class,      AkbankPosQueryRequestDataMapper::class],
            [AssecoPos::class,      AssecoPosQueryRequestDataMapper::class],
            [GarantiPos::class,     GarantiPosQueryRequestDataMapper::class],
            [InterPos::class,       InterPosQueryRequestDataMapper::class],
            [IyzicoPos::class,      IyzicoPosQueryRequestDataMapper::class],
            [ParamPos::class,       ParamPosQueryRequestDataMapper::class],
            [PayFlexCPV4Pos::class, PayFlexCPV4PosQueryRequestDataMapper::class],
            [PayFlexV4Pos::class,   PayFlexV4PosQueryRequestDataMapper::class],
            [PayForPos::class,      PayForPosQueryRequestDataMapper::class],
            [PayTrPos::class,       PayTrPosQueryRequestDataMapper::class],
            [PosNetPos::class,      PosNetPosQueryRequestDataMapper::class],
            [PosNetV1Pos::class,    PosNetV1PosQueryRequestDataMapper::class],
            [ToslaPos::class,       ToslaPosQueryRequestDataMapper::class],
            [VakifKatilimPos::class, VakifKatilimPosQueryRequestDataMapper::class],
        ];
    }
}
