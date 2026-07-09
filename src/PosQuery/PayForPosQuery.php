<?php

/**
 * @license MIT
 */

namespace Mews\Pos\PosQuery;

use Mews\Pos\Gateway\PayForPos;

class PayForPosQuery extends AbstractMappedPosQuery
{
    protected static array $supportedQueries = [
        PosQueryInterface::QUERY_TYPE_CUSTOM_QUERY => true,
        PosQueryInterface::QUERY_TYPE_HISTORY      => true,
    ];

    public static function supports(string $gatewayClass): bool
    {
        return PayForPos::class === $gatewayClass;
    }
}
