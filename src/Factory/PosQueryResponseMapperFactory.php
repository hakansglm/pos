<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Factory;

use Mews\Pos\DataMapper\PosQuery\Response\AkbankPosQueryResponseDataMapper;
use Mews\Pos\DataMapper\PosQuery\Response\GarantiPosQueryResponseDataMapper;
use Mews\Pos\DataMapper\PosQuery\Response\IyzicoPosQueryResponseDataMapper;
use Mews\Pos\DataMapper\PosQuery\Response\ParamPosQueryResponseDataMapper;
use Mews\Pos\DataMapper\PosQuery\Response\PayForPosQueryResponseDataMapper;
use Mews\Pos\DataMapper\PosQuery\Response\PayTrPosQueryResponseDataMapper;
use Mews\Pos\DataMapper\PosQuery\Response\QueryResponseDataMapperInterface;
use Mews\Pos\DataMapper\PosQuery\Response\ToslaPosQueryResponseDataMapper;
use Mews\Pos\DataMapper\PosQuery\Response\VakifKatilimPosQueryResponseDataMapper;
use Mews\Pos\DataMapper\Response\ValueFormatter\ResponseValueFormatterInterface;
use Mews\Pos\DataMapper\Response\ValueMapper\ResponseValueMapperInterface;
use Mews\Pos\PosInterface;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
class PosQueryResponseMapperFactory
{
    /**
     * @var class-string<QueryResponseDataMapperInterface>[]
     */
    private static array $mapperClasses = [
        AkbankPosQueryResponseDataMapper::class,
        GarantiPosQueryResponseDataMapper::class,
        IyzicoPosQueryResponseDataMapper::class,
        PayForPosQueryResponseDataMapper::class,
        ParamPosQueryResponseDataMapper::class,
        PayTrPosQueryResponseDataMapper::class,
        ToslaPosQueryResponseDataMapper::class,
        VakifKatilimPosQueryResponseDataMapper::class,
    ];

    /**
     * Returns null for gateways that have no response mapper registered.
     *
     * @param class-string<PosInterface> $gatewayClass
     */
    public static function createForGateway(
        string                          $gatewayClass,
        ResponseValueFormatterInterface $valueFormatter,
        ResponseValueMapperInterface    $valueMapper,
        LoggerInterface                 $logger
    ): ?QueryResponseDataMapperInterface {
        foreach (self::$mapperClasses as $mapperClass) {
            if ($mapperClass::supports($gatewayClass)) {
                return new $mapperClass($valueFormatter, $valueMapper, $logger);
            }
        }

        return null;
    }
}
