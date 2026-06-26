<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\Response\ValueFormatter;

use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\PosInterface;

/**
 * @internal
 */
class AssecoPosResponseValueFormatter extends AbstractResponseValueFormatter
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return AssecoPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    public function formatAmount($amount, string $txType): float
    {
        if (PosInterface::TX_TYPE_STATUS === $txType || PosInterface::TX_TYPE_ORDER_HISTORY === $txType) {
            return (float) $amount / 100;
        }

        return (float) $amount;
    }
}
