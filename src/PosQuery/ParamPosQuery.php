<?php

/**
 * @license MIT
 */

namespace Mews\Pos\PosQuery;

use Mews\Pos\Gateway\ParamPos;

class ParamPosQuery extends AbstractMappedPosQuery
{
    protected static array $supportedQueries = [
        PosQueryInterface::QUERY_TYPE_CUSTOM_QUERY      => true,
        PosQueryInterface::QUERY_TYPE_HISTORY           => true,
        PosQueryInterface::QUERY_TYPE_INSTALLMENT_RATES => true,
        PosQueryInterface::QUERY_TYPE_BIN_LIST          => true,
    ];

    public static function supports(string $gatewayClass): bool
    {
        return ParamPos::class === $gatewayClass;
    }
}
