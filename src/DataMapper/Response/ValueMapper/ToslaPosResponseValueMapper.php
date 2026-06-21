<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\Response\ValueMapper;

use Mews\Pos\Gateways\ToslaPos;
use Mews\Pos\PosInterface;

class ToslaPosResponseValueMapper extends AbstractResponseValueMapper
{
    /** @var array<string|int, PosInterface::CURRENCY_*> */
    protected array $currencyMappings = [
        '949' => PosInterface::CURRENCY_TRY,
        '840' => PosInterface::CURRENCY_USD,
        '978' => PosInterface::CURRENCY_EUR,
        '826' => PosInterface::CURRENCY_GBP,
        '392' => PosInterface::CURRENCY_JPY,
        '643' => PosInterface::CURRENCY_RUB,
    ];

    /** @var array<PosInterface::TX_TYPE_*, string> */
    protected array $txTypeMappings = [
        PosInterface::TX_TYPE_PAY_AUTH       => '1',
        PosInterface::TX_TYPE_PAY_PRE_AUTH   => '2',
        PosInterface::TX_TYPE_PAY_POST_AUTH  => '3',
        PosInterface::TX_TYPE_CANCEL         => '4',
        PosInterface::TX_TYPE_REFUND         => '5',
        PosInterface::TX_TYPE_REFUND_PARTIAL => '5',
    ];

    /**
     * @inheritdoc
     */
    protected array $orderStatusMappings = [
        0 => PosInterface::PAYMENT_STATUS_ERROR,
        1 => PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED,
        2 => PosInterface::PAYMENT_STATUS_CANCELED,
        3 => PosInterface::PAYMENT_STATUS_PARTIALLY_REFUNDED,
        4 => PosInterface::PAYMENT_STATUS_FULLY_REFUNDED,
        5 => PosInterface::PAYMENT_STATUS_PRE_AUTH_COMPLETED,
    ];

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
    public function mapTxType($txType, ?string $paymentModel = null): ?string
    {
        if (0 === $txType) {
            return null;
        }

        return parent::mapTxType((string) $txType);
    }
}
