<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\Response\ValueFormatter;

use Mews\Pos\Gateway\Param3DHostPos;
use Mews\Pos\Gateway\ParamPos;
use Mews\Pos\PosInterface;

class ParamPosResponseValueFormatter extends AbstractResponseValueFormatter
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return ParamPos::class === $gatewayClass
            || Param3DHostPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    public function formatAmount($amount, ?string $txType = null): float
    {
        if (PosInterface::TX_TYPE_STATUS === $txType) {
            return (float) $amount;
        }

        return ((float) \str_replace(',', '.', (string) $amount));
    }
}
