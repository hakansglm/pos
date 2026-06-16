<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Factory;

use Mews\Pos\Client\AkbankPosHttpClient;
use Mews\Pos\Client\EstPosHttpClient;
use Mews\Pos\Client\GarantiPosHttpClient;
use Mews\Pos\Client\InterPosHttpClient;
use Mews\Pos\Client\IyzicoPosHttpClient;
use Mews\Pos\Client\IyzicoPosQueryApiHttpClient;
use Mews\Pos\Client\KuveytPosHttpClient;
use Mews\Pos\Client\ParamPosHttpClient;
use Mews\Pos\Client\PayFlexCPV4PosHttpClient;
use Mews\Pos\Client\PayFlexV4Pos3DFormHttpClient;
use Mews\Pos\Client\PayFlexV4PosHttpClient;
use Mews\Pos\Client\PayFlexV4PosSearchApiHttpClient;
use Mews\Pos\Client\PayForPosHttpClient;
use Mews\Pos\Client\PosNetPosHttpClient;
use Mews\Pos\Client\PosNetV1PosHttpClient;
use Mews\Pos\Client\ToslaPosHttpClient;
use Mews\Pos\Client\VakifKatilimPosHttpClient;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\Crypt\IyzicoPosCrypt;
use Mews\Pos\DataMapper\RequestValueMapper\RequestValueMapperInterface;
use Mews\Pos\Factory\PosHttpClientFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

class HttpClientFactoryTest extends TestCase
{
    /**
     * @dataProvider createForGatewayDataProvider
     */
    public function testCreateForGateway(string $clientClass, string $cryptClass = CryptInterface::class): void
    {
        $client = PosHttpClientFactory::create(
            $clientClass,
            '',
            $this->createMock($cryptClass),
            $this->createMock(RequestValueMapperInterface::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(ClientInterface::class),
            $this->createMock(RequestFactoryInterface::class),
            $this->createMock(StreamFactoryInterface::class),
        );

        $this->assertInstanceOf($clientClass, $client);
    }

    public static function createForGatewayDataProvider(): array
    {
        return [
            [AkbankPosHttpClient::class],
            [EstPosHttpClient::class],
            [GarantiPosHttpClient::class],
            [InterPosHttpClient::class],
            [IyzicoPosHttpClient::class,           IyzicoPosCrypt::class],
            [IyzicoPosQueryApiHttpClient::class,   IyzicoPosCrypt::class],
            [KuveytPosHttpClient::class],
            [ParamPosHttpClient::class],
            [PayFlexCPV4PosHttpClient::class],
            [PayFlexV4Pos3DFormHttpClient::class],
            [PayFlexV4PosHttpClient::class],
            [PayFlexV4PosSearchApiHttpClient::class],
            [PayForPosHttpClient::class],
            [PosNetPosHttpClient::class],
            [PosNetV1PosHttpClient::class],
            [ToslaPosHttpClient::class],
            [VakifKatilimPosHttpClient::class],
        ];
    }
}
