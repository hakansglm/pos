<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\Response\ValueFormatter;

use Mews\Pos\Exception\NotImplementedException;
use Mews\Pos\Gateway\ToslaPos;

/**
 * @internal
 */
class ToslaPosResponseValueFormatter extends AbstractResponseValueFormatter
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return ToslaPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    public function formatInstallment(?string $installment, string $txType): int
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function formatAmount($amount, string $txType): float
    {
        return ((float) $amount) / 100;
    }
}
