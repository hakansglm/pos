<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Factory;

use Mews\Pos\Client\HttpClientStrategyInterface;
use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Exceptions\BankClassNullException;
use Mews\Pos\Exceptions\BankNotFoundException;
use Mews\Pos\PosInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
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
     *     banks: array<string, array{
     *          name: string,
     *          class?: class-string<T>,
     *          lang?: PosInterface::LANG_*,
     *          gateway_endpoints: array{
     *              payment_api: non-empty-string,
     *              query_api?: non-empty-string}
     *         }>
     *     }                                   $config
     *
     * @param AbstractPosAccount               $posAccount
     * @param array                            $config
     * @param EventDispatcherInterface         $eventDispatcher
     * @param HttpClientStrategyInterface|null $httpClientStrategy
     * @param LoggerInterface|null             $logger
     *
     * @return T
     *
     * @throws BankClassNullException
     * @throws BankNotFoundException
     */
    public static function createPosGateway(
        AbstractPosAccount           $posAccount,
        array                        $config,
        EventDispatcherInterface     $eventDispatcher,
        ?HttpClientStrategyInterface $httpClientStrategy = null,
        ?LoggerInterface             $logger = null
    ): PosInterface {
        if (!$logger instanceof \Psr\Log\LoggerInterface) {
            $logger = new NullLogger();
        }

        // Bank Config Exist
        if (!\array_key_exists($posAccount->getBankName(), $config['banks'])) {
            throw new BankNotFoundException();
        }

        $gatewayClass = $config['banks'][$posAccount->getBankName()]['class'] ?? null;

        if (null === $gatewayClass) {
            throw new BankClassNullException();
        }

        if (!\in_array(PosInterface::class, \class_implements($gatewayClass), true)) {
            throw new \InvalidArgumentException(
                \sprintf('gateway class must be implementation of %s', PosInterface::class)
            );
        }

        $logger->debug('creating gateway for bank', ['bankName'   => $posAccount->getBankName()]);

        return self::doCreatePosGateway(
            $gatewayClass,
            $posAccount,
            $config['banks'][$posAccount->getBankName()],
            $eventDispatcher,
            $logger,
            $httpClientStrategy
        );
    }

    /**
     * @template T of PosInterface
     *
     * @param class-string<T>    $gatewayClass
     * @param AbstractPosAccount $posAccount
     * @param array{
     *           name: string,
     *           class?: class-string,
     *           lang?: PosInterface::LANG_*,
     *           gateway_endpoints: array<HttpClientInterface::API_NAME_*, non-empty-string>
     *          }                              $apiConfig
     * @param EventDispatcherInterface         $eventDispatcher
     * @param LoggerInterface                  $logger
     * @param HttpClientStrategyInterface|null $httpClientStrategy
     *
     * @return T
     */
    private static function doCreatePosGateway(
        string                   $gatewayClass,
        AbstractPosAccount       $posAccount,
        array                    $apiConfig,
        EventDispatcherInterface $eventDispatcher,
        LoggerInterface          $logger,
        ?HttpClientStrategyInterface $httpClientStrategy = null
    ): PosInterface {
        $crypt                 = CryptFactory::createForGateway($gatewayClass, $logger);
        $requestValueMapper    = RequestValueMapperFactory::createForGateway($gatewayClass);
        $requestValueFormatter = RequestValueFormatterFactory::createForGateway($gatewayClass);
        $defaultLang           = $apiConfig['lang'] ?? PosInterface::LANG_TR;

        $requestDataMapper     = RequestDataMapperFactory::createForGateway(
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
                $logger
            );
        }

        // Create Bank Class Instance
        return new $gatewayClass(
            $apiConfig,
            $posAccount,
            $requestValueMapper,
            $requestDataMapper,
            $responseDataMapper,
            $eventDispatcher,
            $httpClientStrategy,
            $logger
        );
    }
}
