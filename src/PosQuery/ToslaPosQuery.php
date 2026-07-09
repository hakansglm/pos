<?php

/**
 * @license MIT
 */

namespace Mews\Pos\PosQuery;

use Mews\Pos\Gateway\ToslaPos;

class ToslaPosQuery extends AbstractMappedPosQuery
{
    protected static array $supportedQueries = [
        PosQueryInterface::QUERY_TYPE_CUSTOM_QUERY       => true,
        PosQueryInterface::QUERY_TYPE_INSTALLMENT_RATES  => true,
        PosQueryInterface::QUERY_TYPE_INSTALLMENT_PRICES => true,
        PosQueryInterface::QUERY_TYPE_HISTORY            => false,
    ];

    public static function supports(string $gatewayClass): bool
    {
        return ToslaPos::class === $gatewayClass;
    }
}
