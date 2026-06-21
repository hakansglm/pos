<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\Response\ValueFormatter;

use Mews\Pos\Gateway\IyzicoPos;
use Mews\Pos\PosInterface;

class IyzicoPosResponseValueFormatter extends AbstractResponseValueFormatter
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return IyzicoPos::class === $gatewayClass;
    }

    /**
     * @inheritdoc
     *
     * Handles epoch milliseconds (payment responses) and ISO-8601 / "Y-m-d H:i:s" strings
     * (order-history createdDate and history transactionDate).
     */
    public function formatDateTime(string $dateTime, string $txType): \DateTimeImmutable
    {
        if (PosInterface::TX_TYPE_HISTORY === $txType || PosInterface::TX_TYPE_ORDER_HISTORY === $txType) {
            return new \DateTimeImmutable($dateTime);
        }

        $date = new \DateTimeImmutable('@' . (int) \floor((float) $dateTime / 1000));

        return $date->setTimezone(new \DateTimeZone('UTC'));
    }
}
