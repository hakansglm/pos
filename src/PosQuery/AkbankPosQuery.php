<?php

/**
 * @license MIT
 */

namespace Mews\Pos\PosQuery;

use Mews\Pos\Gateway\AkbankPos;

class AkbankPosQuery extends AbstractMappedPosQuery
{
    protected static array $supportedQueries = [
        PosQueryInterface::QUERY_TYPE_CUSTOM_QUERY => true,
        PosQueryInterface::QUERY_TYPE_HISTORY      => true,
    ];

    public static function supports(string $gatewayClass): bool
    {
        return AkbankPos::class === $gatewayClass;
    }
}
