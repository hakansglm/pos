<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Factory;

use DomainException;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\PosQuery\Request\AkbankPosQueryRequestDataMapper;
use Mews\Pos\DataMapper\PosQuery\Request\AssecoPosQueryRequestDataMapper;
use Mews\Pos\DataMapper\PosQuery\Request\GarantiPosQueryRequestDataMapper;
use Mews\Pos\DataMapper\PosQuery\Request\InterPosQueryRequestDataMapper;
use Mews\Pos\DataMapper\PosQuery\Request\IyzicoPosQueryRequestDataMapper;
use Mews\Pos\DataMapper\PosQuery\Request\ParamPosQueryRequestDataMapper;
use Mews\Pos\DataMapper\PosQuery\Request\PayFlexCPV4PosQueryRequestDataMapper;
use Mews\Pos\DataMapper\PosQuery\Request\PayFlexV4PosQueryRequestDataMapper;
use Mews\Pos\DataMapper\PosQuery\Request\PayForPosQueryRequestDataMapper;
use Mews\Pos\DataMapper\PosQuery\Request\PayTrPosQueryRequestDataMapper;
use Mews\Pos\DataMapper\PosQuery\Request\PosNetPosQueryRequestDataMapper;
use Mews\Pos\DataMapper\PosQuery\Request\PosNetV1PosQueryRequestDataMapper;
use Mews\Pos\DataMapper\PosQuery\Request\QueryRequestDataMapperInterface;
use Mews\Pos\DataMapper\PosQuery\Request\ToslaPosQueryRequestDataMapper;
use Mews\Pos\DataMapper\PosQuery\Request\VakifKatilimPosQueryRequestDataMapper;
use Mews\Pos\DataMapper\Request\ValueFormatter\RequestValueFormatterInterface;
use Mews\Pos\DataMapper\Request\ValueMapper\RequestValueMapperInterface;
use Mews\Pos\PosInterface;

/**
 * @internal
 */
class PosQueryRequestMapperFactory
{
    /**
     * @var class-string<QueryRequestDataMapperInterface>[]
     */
    private static array $requestDataMapperClasses = [
        AkbankPosQueryRequestDataMapper::class,
        AssecoPosQueryRequestDataMapper::class,
        GarantiPosQueryRequestDataMapper::class,
        InterPosQueryRequestDataMapper::class,
        IyzicoPosQueryRequestDataMapper::class,
        ParamPosQueryRequestDataMapper::class,
        PayFlexCPV4PosQueryRequestDataMapper::class,
        PayFlexV4PosQueryRequestDataMapper::class,
        PayForPosQueryRequestDataMapper::class,
        PayTrPosQueryRequestDataMapper::class,
        PosNetPosQueryRequestDataMapper::class,
        PosNetV1PosQueryRequestDataMapper::class,
        ToslaPosQueryRequestDataMapper::class,
        VakifKatilimPosQueryRequestDataMapper::class,
    ];

    /**
     * @param class-string<PosInterface> $gatewayClass
     */
    public static function createForGateway(
        string                         $gatewayClass,
        RequestValueMapperInterface    $valueMapper,
        RequestValueFormatterInterface $valueFormatter,
        CryptInterface                 $crypt,
        string                         $defaultLang
    ): QueryRequestDataMapperInterface {
        foreach (self::$requestDataMapperClasses as $requestDataMapperClass) {
            if ($requestDataMapperClass::supports($gatewayClass)) {
                return new $requestDataMapperClass(
                    $valueMapper,
                    $valueFormatter,
                    $crypt,
                    $defaultLang
                );
            }
        }

        throw new DomainException(\sprintf('PosQuery request mapper not found for gateway %s', $gatewayClass));
    }
}
