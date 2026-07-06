<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Factory;

use Mews\Pos\Client\HttpClientStrategyInterface;
use Mews\Pos\Exception\GatewayClassNotConfiguredException;
use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\PosInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * PosFactory
 */
class PosFactory
{
    /**
     * @template T of PosInterface
     *
     * @phpstan-param array{
     *     class?: class-string<T>,
     *     gateway_configs?: array{
     *         lang?: PosInterface::LANG_*,
     *         test_mode?: bool,
     *         disable_3d_hash_check?: bool
     *     },
     *     gateway_endpoints: array{
     *         payment_api: non-empty-string,
     *         query_api?: non-empty-string}
     *     }                                   $config
     *
     * @param AbstractPosAccount               $posAccount
     * @param array                            $config
     * @param EventDispatcherInterface         $eventDispatcher
     * @param HttpClientStrategyInterface|null $httpClientStrategy
     * @param ClientInterface|null             $httpClient         PSR-18 client; pass a pre-configured instance
     * @param LoggerInterface|null             $logger
     *
     * @return T
     *
     * @throws GatewayClassNotConfiguredException
     */
    public static function create(
        AbstractPosAccount           $posAccount,
        array                        $config,
        EventDispatcherInterface     $eventDispatcher,
        ?HttpClientStrategyInterface $httpClientStrategy = null,
        ?ClientInterface             $httpClient = null,
        ?LoggerInterface             $logger = null
    ): PosInterface {
        if (!$logger instanceof LoggerInterface) {
            $logger = new NullLogger();
        }

        $gatewayClass = $config['class'] ?? null;

        if (null === $gatewayClass) {
            throw new GatewayClassNotConfiguredException();
        }

        if (!\in_array(PosInterface::class, \class_implements($gatewayClass), true)) {
            throw new \InvalidArgumentException(
                \sprintf('gateway class must be implementation of %s', PosInterface::class)
            );
        }

        $logger->debug('creating gateway for bank', ['bankName' => $posAccount->getBankName()]);

        return self::doCreatePosGateway(
            $gatewayClass,
            $posAccount,
            $config,
            $eventDispatcher,
            $httpClientStrategy,
            $httpClient,
            $logger
        );
    }

    /**
     * @template T of PosInterface
     *
     * @param class-string<T>    $gatewayClass
     * @param AbstractPosAccount $posAccount
     * @param array{
     *           class?: class-string,
     *           gateway_configs?: array{
     *               lang?: PosInterface::LANG_*,
     *               test_mode?: bool,
     *               disable_3d_hash_check?: bool
     *           },
     *           gateway_endpoints: array<HttpClientInterface::API_NAME_*, non-empty-string>
     *          }                              $apiConfig
     * @param EventDispatcherInterface         $eventDispatcher
     * @param HttpClientStrategyInterface|null $httpClientStrategy
     * @param ClientInterface|null             $httpClient
     * @param LoggerInterface                  $logger
     *
     * @return T
     */
    private static function doCreatePosGateway(
        string                       $gatewayClass,
        AbstractPosAccount           $posAccount,
        array                        $apiConfig,
        EventDispatcherInterface     $eventDispatcher,
        ?HttpClientStrategyInterface $httpClientStrategy = null,
        ?ClientInterface             $httpClient = null,
        ?LoggerInterface             $logger = null,
    ): PosInterface {
        $logger                ??= new NullLogger();
        $crypt                 = CryptFactory::createForGateway($gatewayClass, $logger);
        $requestValueMapper    = RequestValueMapperFactory::createForGateway($gatewayClass);
        $requestValueFormatter = RequestValueFormatterFactory::createForGateway($gatewayClass);
        $defaultLang           = $apiConfig['gateway_configs']['lang'] ?? PosInterface::LANG_TR;

        $requestDataMapper = RequestDataMapperFactory::createForGateway(
            $gatewayClass,
            $requestValueMapper,
            $requestValueFormatter,
            $eventDispatcher,
            $crypt,
            $defaultLang
        );

        $responseValueFormatter = ResponseValueFormatterFactory::createForGateway($gatewayClass);
        $responseValueMapper    = ResponseValueMapperFactory::createForGateway($gatewayClass);
        $responseDataMapper     = ResponseDataMapperFactory::createForGateway($gatewayClass, $responseValueFormatter, $responseValueMapper, $logger);

        if (!$httpClientStrategy instanceof HttpClientStrategyInterface) {
            $httpClientStrategy = PosHttpClientStrategyFactory::createForGateway(
                $gatewayClass,
                $apiConfig['gateway_endpoints'],
                $crypt,
                $requestValueMapper,
                $logger,
                $httpClient
            );
        }

        // Create Bank Class Instance
        return new $gatewayClass(
            $apiConfig,
            $posAccount,
            $requestValueMapper,
            $requestDataMapper,
            $responseDataMapper,
            $crypt,
            $eventDispatcher,
            $httpClientStrategy,
            $logger
        );
    }
}
