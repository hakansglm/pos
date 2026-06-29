<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Factory;

use PHPUnit\Framework\Attributes\DataProvider;
use Mews\Pos\Gateway\AkbankPos;
use Mews\Pos\Crypt\AkbankPosCrypt;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Crypt\AssecoPosCrypt;
use Mews\Pos\Gateway\GarantiPos;
use Mews\Pos\Crypt\GarantiPosCrypt;
use Mews\Pos\Gateway\InterPos;
use Mews\Pos\Crypt\InterPosCrypt;
use Mews\Pos\Gateway\KuveytPos;
use Mews\Pos\Crypt\KuveytPosCrypt;
use Mews\Pos\Gateway\ParamPos;
use Mews\Pos\Crypt\ParamPosCrypt;
use Mews\Pos\Gateway\PayFlexV4Pos;
use Mews\Pos\Crypt\NullCrypt;
use Mews\Pos\Gateway\PayFlexCPV4Pos;
use Mews\Pos\Crypt\PayFlexCPV4PosCrypt;
use Mews\Pos\Gateway\PayForPos;
use Mews\Pos\Crypt\PayForPosCrypt;
use Mews\Pos\Gateway\PosNetPos;
use Mews\Pos\Crypt\PosNetPosCrypt;
use Mews\Pos\Gateway\PosNetV1Pos;
use Mews\Pos\Crypt\PosNetV1PosCrypt;
use Mews\Pos\Gateway\ToslaPos;
use Mews\Pos\Crypt\ToslaPosCrypt;
use Mews\Pos\Gateway\VakifKatilimPos;
use stdClass;
use Mews\Pos\Factory\CryptFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(CryptFactory::class)]
class CryptFactoryTest extends TestCase
{
    #[DataProvider('createForGatewayDataProvider')]
    public function testCreateForGateway(string $gatewayClass, string $cryptClass): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $crypt  = CryptFactory::createForGateway($gatewayClass, $logger);
        $this->assertInstanceOf($cryptClass, $crypt);
    }

    public static function createForGatewayDataProvider(): array
    {
        return [
            [AkbankPos::class, AkbankPosCrypt::class],
            [AssecoPos::class, AssecoPosCrypt::class],
            [GarantiPos::class, GarantiPosCrypt::class],
            [InterPos::class, InterPosCrypt::class],
            [KuveytPos::class, KuveytPosCrypt::class],
            [ParamPos::class, ParamPosCrypt::class],
            [PayFlexV4Pos::class, NullCrypt::class],
            [PayFlexCPV4Pos::class, PayFlexCPV4PosCrypt::class],
            [PayForPos::class, PayForPosCrypt::class],
            [PosNetPos::class, PosNetPosCrypt::class],
            [PosNetV1Pos::class, PosNetV1PosCrypt::class],
            [ToslaPos::class, ToslaPosCrypt::class],
            [VakifKatilimPos::class, KuveytPosCrypt::class],
            [stdClass::class, NullCrypt::class],
        ];
    }
}
