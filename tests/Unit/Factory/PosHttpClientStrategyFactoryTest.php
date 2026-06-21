<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Factory;

use Mews\Pos\Client\GenericPosHttpClientStrategy;
use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\Request\ValueMapper\RequestValueMapperInterface;
use Mews\Pos\Factory\PosHttpClientStrategyFactory;
use Mews\Pos\Gateway\AkbankPos;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\GarantiPos;
use Mews\Pos\Gateway\InterPos;
use Mews\Pos\Gateway\KuveytPos;
use Mews\Pos\Gateway\Param3DHostPos;
use Mews\Pos\Gateway\ParamPos;
use Mews\Pos\Gateway\PayFlexCPV4Pos;
use Mews\Pos\Gateway\PayFlexV4Pos;
use Mews\Pos\Gateway\PayForPos;
use Mews\Pos\Gateway\PosNetPos;
use Mews\Pos\Gateway\PosNetV1Pos;
use Mews\Pos\Gateway\ToslaPos;
use Mews\Pos\Gateway\VakifKatilimPos;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(PosHttpClientStrategyFactory::class)]
class PosHttpClientStrategyFactoryTest extends TestCase
{
    /**
     * @dataProvider createForGatewayDataProvider
     */
    public function testCreateForGateway(string $gatewayClass, array $expectedClients): void
    {
        $crypt              = $this->createMock(CryptInterface::class);
        $requestValueMapper = $this->createMock(RequestValueMapperInterface::class);
        $logger             = $this->createMock(LoggerInterface::class);


        $clientStrategy = PosHttpClientStrategyFactory::createForGateway(
            $gatewayClass,
            [
                HttpClientInterface::API_NAME_PAYMENT_API    => 'https://example.com/payment_api',
                HttpClientInterface::API_NAME_QUERY_API      => 'https://example.com/query_api',
                HttpClientInterface::API_NAME_GATEWAY_3D_API => 'https://example.com/gateway_3d',
                'gateway_3d_host'                            => 'https://example.com/gateway_3d_host',
            ],
            $crypt,
            $requestValueMapper,
            $logger
        );

        $this->assertInstanceOf(GenericPosHttpClientStrategy::class, $clientStrategy);
        $clients = $clientStrategy->getAllClients();
        $this->assertCount(
            count($expectedClients),
            $clients,
            sprintf(
                'Available clients for %s: %s',
                $gatewayClass,
                implode(
                    ', ',
                    array_keys($clients)
                )
            )
        );
        foreach ($expectedClients as $apiName) {
            $this->assertArrayHasKey($apiName, $clients);
        }
    }

    public function testCreateForGatewayWithUnsupportedGateway(): void
    {
        $crypt              = $this->createMock(CryptInterface::class);
        $requestValueMapper = $this->createMock(RequestValueMapperInterface::class);
        $logger             = $this->createMock(LoggerInterface::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Client not found for the gateway UnsupportedGateway');

        PosHttpClientStrategyFactory::createForGateway(
            'UnsupportedGateway',
            [
                'payment_api' => 'https://example.com/api',
            ],
            $crypt,
            $requestValueMapper,
            $logger
        );
    }

    public static function createForGatewayDataProvider(): array
    {
        return [
            [
                AkbankPos::class,
                [
                    HttpClientInterface::API_NAME_PAYMENT_API,
                ],
            ],
            [
                AssecoPos::class,
                [
                    HttpClientInterface::API_NAME_PAYMENT_API,
                ],
            ],
            [
                GarantiPos::class,
                [
                    HttpClientInterface::API_NAME_PAYMENT_API,
                ],
            ],
            [
                InterPos::class,
                [
                    HttpClientInterface::API_NAME_PAYMENT_API,
                ],
            ],
            [
                KuveytPos::class,
                [
                    HttpClientInterface::API_NAME_PAYMENT_API,
                    HttpClientInterface::API_NAME_QUERY_API,
                    HttpClientInterface::API_NAME_GATEWAY_3D_API,
                ],
            ],
            [
                Param3DHostPos::class,
                [
                    HttpClientInterface::API_NAME_PAYMENT_API,
                ],
            ],
            [
                ParamPos::class,
                [
                    HttpClientInterface::API_NAME_PAYMENT_API,
                ],
            ],
            [
                PayFlexCPV4Pos::class,
                [
                    HttpClientInterface::API_NAME_PAYMENT_API,
                    HttpClientInterface::API_NAME_GATEWAY_3D_API,
                ],
            ],
            [
                PayFlexV4Pos::class,
                [
                    HttpClientInterface::API_NAME_PAYMENT_API,
                    HttpClientInterface::API_NAME_QUERY_API,
                    HttpClientInterface::API_NAME_GATEWAY_3D_API,
                ],
            ],
            [
                PayForPos::class,
                [
                    HttpClientInterface::API_NAME_PAYMENT_API,
                    HttpClientInterface::API_NAME_GATEWAY_3D_API,
                ],
            ],
            [
                PosNetPos::class,
                [
                    HttpClientInterface::API_NAME_PAYMENT_API,
                ],
            ],
            [
                PosNetV1Pos::class,
                [
                    HttpClientInterface::API_NAME_PAYMENT_API,
                ],
            ],
            [
                ToslaPos::class,
                [
                    HttpClientInterface::API_NAME_PAYMENT_API,
                ],
            ],
            [
                VakifKatilimPos::class,
                [
                    HttpClientInterface::API_NAME_PAYMENT_API,
                    HttpClientInterface::API_NAME_GATEWAY_3D_API,
                ],
            ],
        ];
    }
}
