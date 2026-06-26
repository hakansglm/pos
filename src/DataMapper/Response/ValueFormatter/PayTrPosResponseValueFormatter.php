<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\Response\ValueFormatter;

use Mews\Pos\Gateway\PayTrPos;
use Mews\Pos\PosInterface;

/**
 * @internal
 */
class PayTrPosResponseValueFormatter extends AbstractResponseValueFormatter
{
    /** @inheritDoc */
    public static function supports(string $gatewayClass): bool
    {
        return PayTrPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     *
     * PayTR callback sends total_amount as integer × 100.
     * History and status responses send amounts as decimal strings ("10.00" or "1,16").
     */
    public function formatAmount($amount, string $txType): float
    {
        if (\in_array($txType, [PosInterface::TX_TYPE_STATUS, PosInterface::TX_TYPE_HISTORY], true)) {
            // "1,16" => 1.16
            return (float) \str_replace(',', '.', (string) $amount);
        }

        if (PosInterface::TX_TYPE_REFUND === $txType) {
            return (float) $amount;
        }

        return ((float) $amount) / 100;
    }
}
