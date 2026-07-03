<?php

/**
 * @license MIT
 */

namespace Mews\Pos\PosQuery;

use Mews\Pos\Gateway\AssecoPos;

class AssecoPosQuery extends AbstractPosQuery
{
    protected static array $supportedQueries = [
        PosQueryInterface::QUERY_TYPE_CUSTOM_QUERY => true,
        PosQueryInterface::QUERY_TYPE_HISTORY      => false,
    ];

    public static function supports(string $gatewayClass): bool
    {
        return AssecoPos::class === $gatewayClass;
    }
}
