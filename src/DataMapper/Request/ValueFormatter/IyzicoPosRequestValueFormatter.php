<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\Request\ValueFormatter;

use Mews\Pos\Gateway\IyzicoPos;

class IyzicoPosRequestValueFormatter implements RequestValueFormatterInterface
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return IyzicoPos::class === $gatewayClass;
    }

    /**
     * iyzico requires installment as a plain integer (1 for single payment).
     *
     * @inheritDoc
     *
     * @return int
     */
    public function formatInstallment(int $installment): int
    {
        return max($installment, 1);
    }

    /**
     * @inheritDoc
     */
    public function formatAmount(float $amount, ?string $txType = null): float
    {
        return $amount;
    }

    /**
     * @inheritDoc
     */
    public function formatCardExpDate(\DateTimeInterface $expDate, string $fieldName): string
    {
        if ('expireMonth' === $fieldName) {
            return $expDate->format('m');
        }

        if ('expireYear' === $fieldName) {
            return $expDate->format('Y');
        }

        throw new \InvalidArgumentException(\sprintf('Unsupported field name "%s"', $fieldName));
    }

    /**
     * @inheritDoc
     */
    public function formatDateTime(\DateTimeInterface $dateTime, string $fieldName): string
    {
        return $dateTime->format('Y-m-d');
    }
}
