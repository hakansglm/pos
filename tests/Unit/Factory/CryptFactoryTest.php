<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Factory;

use Mews\Pos\Factory\CryptFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(CryptFactory::class)]
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
            [\Mews\Pos\Gateway\AkbankPos::class, \Mews\Pos\Crypt\AkbankPosCrypt::class],
            [\Mews\Pos\Gateway\AssecoPos::class, \Mews\Pos\Crypt\AssecoPosCrypt::class],
            [\Mews\Pos\Gateway\GarantiPos::class, \Mews\Pos\Crypt\GarantiPosCrypt::class],
            [\Mews\Pos\Gateway\InterPos::class, \Mews\Pos\Crypt\InterPosCrypt::class],
            [\Mews\Pos\Gateway\KuveytPos::class, \Mews\Pos\Crypt\KuveytPosCrypt::class],
            [\Mews\Pos\Gateway\ParamPos::class, \Mews\Pos\Crypt\ParamPosCrypt::class],
            [\Mews\Pos\Gateway\PayFlexV4Pos::class, \Mews\Pos\Crypt\NullCrypt::class],
            [\Mews\Pos\Gateway\PayFlexCPV4Pos::class, \Mews\Pos\Crypt\PayFlexCPV4Crypt::class],
            [\Mews\Pos\Gateway\PayForPos::class, \Mews\Pos\Crypt\PayForPosCrypt::class],
            [\Mews\Pos\Gateway\PosNetPos::class, \Mews\Pos\Crypt\PosNetPosCrypt::class],
            [\Mews\Pos\Gateway\PosNetV1Pos::class, \Mews\Pos\Crypt\PosNetV1PosCrypt::class],
            [\Mews\Pos\Gateway\ToslaPos::class, \Mews\Pos\Crypt\ToslaPosCrypt::class],
            [\Mews\Pos\Gateway\VakifKatilimPos::class, \Mews\Pos\Crypt\KuveytPosCrypt::class],
            [\stdClass::class, \Mews\Pos\Crypt\NullCrypt::class],
        ];
    }
}
