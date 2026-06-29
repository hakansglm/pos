<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Factory;

use PHPUnit\Framework\Attributes\DataProvider;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Mews\Pos\Gateway\AkbankPos;
use Generator;
use stdClass;
use InvalidArgumentException;
use DomainException;
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
use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Exception\GatewayClassNotConfiguredException;
use Mews\Pos\Exception\GatewayConfigNotFoundException;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PosFactory::class)]
class PosFactoryTest extends TestCase
{
    #[DataProvider('createPosGatewayDataProvider')]
    public function testCreatePosGateway(array $config, string $configKey, bool $cardTypeMapping, string $expectedGatewayClass): void
    {
        $account = $this->createMock(AbstractPosAccount::class);
        $account->expects(self::atLeastOnce())
            ->method('getBankName')
            ->willReturn($configKey);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $logger          = $this->createMock(LoggerInterface::class);

        $gateway = PosFactory::createPosGateway(
            $account,
            $config,
            $eventDispatcher,
            null,
            $logger
        );
        $this->assertInstanceOf($expectedGatewayClass, $gateway);

        $this->assertSame($account, $gateway->getAccount());
        $this->assertNotEmpty($gateway->getCurrencies());
        if ($cardTypeMapping) {
            $this->assertNotEmpty($gateway->getCardTypeMapping());
        } else {
            $this->assertEmpty($gateway->getCardTypeMapping());
        }
    }


    public function testCreatePosGatewayWithOnlyRequiredParameters(): void
    {
        $gatewayClass = AkbankPos::class;
        $config       = [
            'banks' => [
                'akbank' => [
                    'name'              => 'Akbank',
                    'class'             => $gatewayClass,
                    'gateway_endpoints' => [
                        'payment_api'     => 'https://apipre.akbank.com/api/v1/payment/virtualpos',
                        'gateway_3d'      => 'https://virtualpospaymentgatewaypre.akbank.com/securepay',
                        'gateway_3d_host' => 'https://virtualpospaymentgatewaypre.akbank.com/payhosting',
                    ],
                ],
            ],
        ];
        $account      = $this->createMock(AbstractPosAccount::class);
        $account->expects(self::atLeastOnce())
            ->method('getBankName')
            ->willReturn('akbank');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $gateway = PosFactory::createPosGateway(
            $account,
            $config,
            $eventDispatcher,
        );
        $this->assertInstanceOf($gatewayClass, $gateway);
    }

    #[DataProvider('createPosGatewayDataExceptionProvider')]
    public function testCreatePosGatewayFail(array $config, string $configKey, string $expectedExceptionClass): void
    {
        $account = $this->createMock(AbstractPosAccount::class);
        $account->expects(self::atLeastOnce())
            ->method('getBankName')
            ->willReturn($configKey);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->expectException($expectedExceptionClass);
        PosFactory::createPosGateway(
            $account,
            $config,
            $eventDispatcher,
        );
    }

    public static function createPosGatewayDataExceptionProvider(): Generator
    {
        yield 'missing_gateway_class_in_config' => [
            'config'                   => [
                'banks' => [
                    'akbank' => [
                        'name'              => 'Akbank',
                        'gateway_endpoints' => [
                            'payment_api'     => 'https://apipre.akbank.com/api/v1/payment/virtualpos',
                            'gateway_3d'      => 'https://virtualpospaymentgatewaypre.akbank.com/securepay',
                            'gateway_3d_host' => 'https://virtualpospaymentgatewaypre.akbank.com/payhosting',
                        ],
                    ],
                ],
            ],
            'config_key'               => 'akbank',
            'expected_exception_class' => GatewayClassNotConfiguredException::class,
        ];

        yield 'invalid_gateway_class' => [
            'config'                   => [
                'banks' => [
                    'akbank' => [
                        'name'              => 'Akbank',
                        'class'             => stdClass::class,
                        'gateway_endpoints' => [
                            'payment_api'     => 'https://apipre.akbank.com/api/v1/payment/virtualpos',
                            'gateway_3d'      => 'https://virtualpospaymentgatewaypre.akbank.com/securepay',
                            'gateway_3d_host' => 'https://virtualpospaymentgatewaypre.akbank.com/payhosting',
                        ],
                    ],
                ],
            ],
            'config_key'               => 'akbank',
            'expected_exception_class' => InvalidArgumentException::class,
        ];

        yield 'serializer_not_found' => [
            'config'                   => [
                'banks' => [
                    'akbank' => [
                        'name'              => 'Akbank',
                        'class'             => AkbankPos::class,
                        'gateway_endpoints' => [
                        ],
                    ],
                ],
            ],
            'config_key'               => 'akbank',
            'expected_exception_class' => DomainException::class,
        ];

        yield 'bank_not_found' => [
            'config'                   => [
                'banks' => [
                    'akbank' => [
                        'name'              => 'Akbank',
                        'class'             => AkbankPos::class,
                        'gateway_endpoints' => [
                        ],
                    ],
                ],
            ],
            'config_key'               => 'akbank2',
            'expected_exception_class' => GatewayConfigNotFoundException::class,
        ];
    }

    public static function createPosGatewayDataProvider(): Generator
    {
        $gatewayClasses = [
            AkbankPos::class        => false,
            AssecoPos::class        => false,
            GarantiPos::class      => false,
            InterPos::class        => true,
            KuveytPos::class       => true,
            Param3DHostPos::class  => false,
            ParamPos::class        => false,
            PayFlexCPV4Pos::class  => true,
            PayFlexV4Pos::class    => true,
            PayForPos::class       => false,
            PosNetPos::class       => false,
            PosNetV1Pos::class     => false,
            ToslaPos::class        => false,
            VakifKatilimPos::class => false,
        ];

        foreach ($gatewayClasses as $gatewayClass => $cardTypeMapping) {
            $lang = array_rand([
                PosInterface::LANG_EN,
                PosInterface::LANG_TR,
                null,
            ]);
            $configKey = 'abcdse';
            $gatewayEndpoints = [
                'payment_api'     => 'https://apipre.akbank.com/api/v1/payment/virtualpos',
                'gateway_3d'      => 'https://virtualpospaymentgatewaypre.akbank.com/securepay',
                'gateway_3d_host' => 'https://virtualpospaymentgatewaypre.akbank.com/payhosting',
                'query_api'       => 'https://apipre.akbank.com/api/v1/query_api',
            ];

            $config    = [
                'banks' => [
                    $configKey => [
                        'name'            => 'Akbank',
                        'class'           => $gatewayClass,
                        'gateway_configs' => ['lang' => $lang],
                        'gateway_endpoints' => $gatewayEndpoints,
                    ],
                ],
            ];
            yield [
                $config,
                $configKey,
                $cardTypeMapping,
                $gatewayClass,
            ];
        }
    }
}
