<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\Request\ValueFormatter;

use Mews\Pos\Gateway\PayTrPos;
use Mews\Pos\PosInterface;

class PayTrPosRequestValueFormatter implements RequestValueFormatterInterface
{
    /** @inheritDoc */
    public static function supports(string $gatewayClass): bool
    {
        return PayTrPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     *
     * iFrame API requires integer × 100 (TX_TYPE_INTERNAL_3D_FORM_BUILD).
     * Direct payment and refund use decimal string with dot separator.
     *
     * @return int|string
     */
    public function formatAmount(float $amount, ?string $txType = null): int|string
    {
        if (PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD === $txType) {
            return (int) \round($amount * 100);
        }

        return \number_format($amount, 2, '.', '');
    }

    /**
     * @inheritDoc
     *
     * PayTR uses 0 for single payment, 2-12 for installments.
     *
     * @return int
     */
    public function formatInstallment(int $installment): int
    {
        return $installment > 1 ? $installment : 0;
    }

    /** @inheritDoc */
    public function formatCardExpDate(\DateTimeInterface $expDate, string $fieldName): string
    {
        return match ($fieldName) {
            'expiry_month' => $expDate->format('m'),
            'expiry_year'  => $expDate->format('y'),
            default        => throw new \InvalidArgumentException(\sprintf('Unsupported field name "%s"', $fieldName)),
        };
    }

    /** @inheritDoc */
    public function formatDateTime(\DateTimeInterface $dateTime, string $fieldName): string
    {
        return $dateTime->format('Y-m-d H:i:s');
    }
}
