<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\Response\ValueMapper;

use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\PosInterface;

class AkbankPosResponseValueMapper extends AbstractResponseValueMapper
{
    /** @var array<int, PosInterface::CURRENCY_*> */
    protected array $currencyMappings = [
        949 => PosInterface::CURRENCY_TRY,
        840 => PosInterface::CURRENCY_USD,
        978 => PosInterface::CURRENCY_EUR,
        826 => PosInterface::CURRENCY_GBP,
        392 => PosInterface::CURRENCY_JPY,
        643 => PosInterface::CURRENCY_RUB,
    ];

    /** @var array<PosInterface::TX_TYPE_*, string|array<PosInterface::MODEL_*, string>> */
    protected array $txTypeMappings = [
        PosInterface::TX_TYPE_PAY_AUTH      => [
            PosInterface::MODEL_NON_SECURE => '1000',
            PosInterface::MODEL_3D_SECURE  => '3000',
            PosInterface::MODEL_3D_PAY     => '3000',
            PosInterface::MODEL_3D_HOST    => '3000',
        ],
        PosInterface::TX_TYPE_PAY_PRE_AUTH  => [
            PosInterface::MODEL_NON_SECURE => '1004',
            PosInterface::MODEL_3D_SECURE  => '3004',
            PosInterface::MODEL_3D_PAY     => '3004',
            PosInterface::MODEL_3D_HOST    => '3004',
        ],
        PosInterface::TX_TYPE_PAY_POST_AUTH  => '1005',
        PosInterface::TX_TYPE_REFUND         => '1002',
        PosInterface::TX_TYPE_REFUND_PARTIAL => '1002',
        PosInterface::TX_TYPE_CANCEL         => '1003',
        PosInterface::TX_TYPE_ORDER_HISTORY  => '1010',
        PosInterface::TX_TYPE_HISTORY        => '1009',
    ];

    /**
     * N: Normal
     * S: Şüpheli
     * V: İptal
     * R: Reversal
     *
     * @var array<string, PosInterface::PAYMENT_STATUS_*>
     */
    protected array $orderStatusMappings = [
        'N'         => PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED,
        'S'         => PosInterface::PAYMENT_STATUS_ERROR,
        'V'         => PosInterface::PAYMENT_STATUS_CANCELED,
        'R'         => PosInterface::PAYMENT_STATUS_FULLY_REFUNDED,

        // status that are return on history request
        'Başarılı'  => PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED,
        'Başarısız' => PosInterface::PAYMENT_STATUS_ERROR,
        'İptal'     => PosInterface::PAYMENT_STATUS_CANCELED,
    ];

    /**
     * @var array<string, PosInterface::PAYMENT_STATUS_*>
     */
    private array $recurringOrderStatusMappings = [
        'S' => PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED,
        'W' => PosInterface::PAYMENT_STATUS_PAYMENT_PENDING,
        // when fulfilled payment is canceled
        'V' => PosInterface::PAYMENT_STATUS_CANCELED,
        // when unfulfilled payment is canceled
        'C' => PosInterface::PAYMENT_STATUS_CANCELED,
    ];

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return AkbankPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    public function mapOrderStatus($orderStatus, ?string $preAuthStatus = null, bool $isRecurringOrder = false)
    {
        if ($isRecurringOrder) {
            return $this->recurringOrderStatusMappings[$orderStatus] ?? $orderStatus;
        }

        $mappedOrderStatus = $this->orderStatusMappings[$orderStatus] ?? $orderStatus;
        /**
         * preAuthStatus
         * "O": Açık
         * "C": Kapalı
         */
        if (PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED === $mappedOrderStatus && 'O' === $preAuthStatus) {
            return PosInterface::PAYMENT_STATUS_PRE_AUTH_COMPLETED;
        }

        return $mappedOrderStatus;
    }
}
