<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Factory;

use DomainException;
use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Exception\GatewayClassNotConfiguredException;
use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\PosInterface;
use Mews\Pos\PosQuery\AkbankPosQuery;
use Mews\Pos\PosQuery\AssecoPosQuery;
use Mews\Pos\PosQuery\GarantiPosQuery;
use Mews\Pos\PosQuery\InterPosQuery;
use Mews\Pos\PosQuery\IyzicoPosQuery;
use Mews\Pos\PosQuery\ParamPosQuery;
use Mews\Pos\PosQuery\PayFlexCPV4PosQuery;
use Mews\Pos\PosQuery\PayFlexV4PosQuery;
use Mews\Pos\PosQuery\PayForPosQuery;
use Mews\Pos\PosQuery\PayTrPosQuery;
use Mews\Pos\PosQuery\PosNetPosQuery;
use Mews\Pos\PosQuery\PosNetV1PosQuery;
use Mews\Pos\PosQuery\PosQueryInterface;
use Mews\Pos\PosQuery\ToslaPosQuery;
use Mews\Pos\PosQuery\VakifKatilimPosQuery;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class PosQueryFactory
{
    /**
     * @var class-string<PosQueryInterface>[]
     */
    private static array $posQueryClasses = [
        AkbankPosQuery::class,
        AssecoPosQuery::class,
        GarantiPosQuery::class,
        InterPosQuery::class,
        IyzicoPosQuery::class,
        ParamPosQuery::class,
        PayFlexCPV4PosQuery::class,
        PayFlexV4PosQuery::class,
        PayForPosQuery::class,
        PayTrPosQuery::class,
        PosNetPosQuery::class,
        PosNetV1PosQuery::class,
        ToslaPosQuery::class,
        VakifKatilimPosQuery::class,
    ];

    /**
     * @param class-string<PosInterface> $gatewayClass
     *
     * @return class-string<PosQueryInterface>|null
     */
    public static function getPosQueryClassForGateway(string $gatewayClass): ?string
    {
        foreach (self::$posQueryClasses as $candidateClass) {
            if ($candidateClass::supports($gatewayClass)) {
                return $candidateClass;
            }
        }

        return null;
    }

    /**
     * @phpstan-param array{
     *     class?: class-string<PosInterface>,
     *     gateway_configs?: array{
     *         test_mode?: bool,
     *         lang?: PosInterface::LANG_*
     *     },
     *     gateway_endpoints: array<HttpClientInterface::API_NAME_*, non-empty-string>
     * } $config
     *
     * @throws GatewayClassNotConfiguredException
     * @throws DomainException                    when no PosQuery implementation is registered for this gateway
     */
    public static function create(
        AbstractPosAccount       $posAccount,
        array                    $config,
        EventDispatcherInterface $eventDispatcher,
        ?ClientInterface         $httpClient = null,
        ?LoggerInterface         $logger = null
    ): PosQueryInterface {
        if (!$logger instanceof LoggerInterface) {
            $logger = new NullLogger();
        }

        /** @var class-string<PosInterface>|null $gatewayClass */
        $gatewayClass = $config['class'] ?? null;

        if (null === $gatewayClass) {
            throw new GatewayClassNotConfiguredException();
        }

        $posQueryClass = self::getPosQueryClassForGateway($gatewayClass);

        if (null === $posQueryClass) {
            throw new DomainException(\sprintf('PosQuery not found for gateway %s', $gatewayClass));
        }

        $logger->debug('creating PosQuery for bank', ['bankName' => $posAccount->getBankName(), 'class' => $posQueryClass]);

        $crypt = CryptFactory::createForGateway($gatewayClass, $logger);

        $requestDataMapper = PosQueryRequestMapperFactory::createForGateway(
            $gatewayClass,
            RequestValueMapperFactory::createForGateway($gatewayClass),
            RequestValueFormatterFactory::createForGateway($gatewayClass),
            $crypt,
            $config['gateway_configs']['lang'] ?? PosInterface::LANG_TR
        );

        $responseDataMapper = PosQueryResponseMapperFactory::createForGateway(
            $gatewayClass,
            ResponseValueFormatterFactory::createForGateway($gatewayClass),
            ResponseValueMapperFactory::createForGateway($gatewayClass),
            $logger
        );

        $clientStrategy = PosHttpClientStrategyFactory::createForGateway(
            $gatewayClass,
            $config['gateway_endpoints'],
            $crypt,
            RequestValueMapperFactory::createForGateway($gatewayClass),
            $logger,
            $httpClient
        );

        if ($responseDataMapper !== null) {
            return new $posQueryClass(
                $config,
                $posAccount,
                $requestDataMapper,
                $responseDataMapper,
                $clientStrategy,
                $eventDispatcher,
                $logger
            );
        }

        return new $posQueryClass(
            $config,
            $posAccount,
            $requestDataMapper,
            $clientStrategy,
            $eventDispatcher,
            $logger
        );
    }
}
