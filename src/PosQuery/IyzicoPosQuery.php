<?php

/**
 * @license MIT
 */

namespace Mews\Pos\PosQuery;

use Mews\Pos\Gateway\IyzicoPos;

class IyzicoPosQuery extends AbstractMappedPosQuery
{
    protected static array $supportedQueries = [
        PosQueryInterface::QUERY_TYPE_CUSTOM_QUERY       => true,
        PosQueryInterface::QUERY_TYPE_HISTORY            => true,
        PosQueryInterface::QUERY_TYPE_INSTALLMENT_PRICES => true,
        PosQueryInterface::QUERY_TYPE_BIN_LIST           => true,
    ];

    public static function supports(string $gatewayClass): bool
    {
        return IyzicoPos::class === $gatewayClass;
    }
}
