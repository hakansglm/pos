<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\Response\ValueFormatter;

use Mews\Pos\Gateway\PosNetPos;
use Mews\Pos\Gateway\PosNetV1Pos;
use Mews\Pos\PosInterface;

/**
 * Value formatter for PosNet and PosNetV1Pos
 *
 * @internal
 */
class PosNetPosResponseValueFormatter extends AbstractResponseValueFormatter
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return PosNetPos::class === $gatewayClass
            || PosNetV1Pos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    public function formatAmount($amount, string $txType): float
    {
        if (PosInterface::TX_TYPE_STATUS === $txType) {
            // "1,16" => 1.16
            return (float) \str_replace(',', '.', \str_replace('.', '', (string) $amount));
        }

        return ((int) $amount) / 100;
    }
}
