<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Factory;

use Mews\Pos\Factory\CryptFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \Mews\Pos\Factory\CryptFactory
 */
class CryptFactoryTest extends TestCase
{
    /**
     * @dataProvider createForGatewayDataProvider
     */
    public function testCreateForGateway(string $gatewayClass, string $cryptClass): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $crypt  = CryptFactory::createForGateway($gatewayClass, $logger);
        $this->assertInstanceOf($cryptClass, $crypt);
    }

    public static function createForGatewayDataProvider(): array
    {
        return [
            [\Mews\Pos\Gateways\AkbankPos::class, \Mews\Pos\Crypt\AkbankPosCrypt::class],
            [\Mews\Pos\Gateways\EstV3Pos::class, \Mews\Pos\Crypt\EstV3PosCrypt::class],
            [\Mews\Pos\Gateways\GarantiPos::class, \Mews\Pos\Crypt\GarantiPosCrypt::class],
            [\Mews\Pos\Gateways\InterPos::class, \Mews\Pos\Crypt\InterPosCrypt::class],
            [\Mews\Pos\Gateways\KuveytPos::class, \Mews\Pos\Crypt\KuveytPosCrypt::class],
            [\Mews\Pos\Gateways\ParamPos::class, \Mews\Pos\Crypt\ParamPosCrypt::class],
            [\Mews\Pos\Gateways\PayFlexV4Pos::class, \Mews\Pos\Crypt\NullCrypt::class],
            [\Mews\Pos\Gateways\PayFlexCPV4Pos::class, \Mews\Pos\Crypt\PayFlexCPV4Crypt::class],
            [\Mews\Pos\Gateways\PayForPos::class, \Mews\Pos\Crypt\PayForPosCrypt::class],
            [\Mews\Pos\Gateways\PosNetPos::class, \Mews\Pos\Crypt\PosNetPosCrypt::class],
            [\Mews\Pos\Gateways\PosNetV1Pos::class, \Mews\Pos\Crypt\PosNetV1PosCrypt::class],
            [\Mews\Pos\Gateways\ToslaPos::class, \Mews\Pos\Crypt\ToslaPosCrypt::class],
            [\Mews\Pos\Gateways\VakifKatilimPos::class, \Mews\Pos\Crypt\KuveytPosCrypt::class],
            [\stdClass::class, \Mews\Pos\Crypt\NullCrypt::class],
        ];
    }
}
