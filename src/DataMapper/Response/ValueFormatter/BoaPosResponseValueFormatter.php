<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\Response\ValueFormatter;

use Mews\Pos\Gateway\KuveytPos;
use Mews\Pos\Gateway\VakifKatilimPos;
use Mews\Pos\PosInterface;
use Mews\Pos\PosQuery\PosQueryInterface;

/**
 * Boa Pos is used by Kuveyt and VakifKatilim
 *
 * @internal
 */
class BoaPosResponseValueFormatter extends AbstractResponseValueFormatter
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return KuveytPos::class === $gatewayClass
            || VakifKatilimPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    public function formatAmount($amount, string $txType): float
    {
        if (\in_array(
            $txType,
            [
            PosInterface::TX_TYPE_STATUS,
            PosInterface::TX_TYPE_ORDER_HISTORY,
            PosQueryInterface::QUERY_TYPE_HISTORY],
            true
        )) {
            return parent::formatAmount($amount, $txType);
        }

        return (float) $amount / 100;
    }
}
