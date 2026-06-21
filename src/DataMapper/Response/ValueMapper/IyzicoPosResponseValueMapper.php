<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\Response\ValueMapper;

use Mews\Pos\Gateways\IyzicoPos;
use Mews\Pos\PosInterface;

class IyzicoPosResponseValueMapper extends AbstractResponseValueMapper
{
    /** @var array<string, PosInterface::CURRENCY_*> */
    protected array $currencyMappings = [
        'TRY' => PosInterface::CURRENCY_TRY,
        'USD' => PosInterface::CURRENCY_USD,
        'EUR' => PosInterface::CURRENCY_EUR,
        'GBP' => PosInterface::CURRENCY_GBP,
        'JPY' => PosInterface::CURRENCY_JPY,
        'RUB' => PosInterface::CURRENCY_RUB,
    ];

    /** @var array<string, PosInterface::PAYMENT_STATUS_*> */
    protected array $orderStatusMappings = [
        'SUCCESS'          => PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED,
        'FAILURE'          => PosInterface::PAYMENT_STATUS_ERROR,
        'INIT_THREEDS'     => PosInterface::PAYMENT_STATUS_PAYMENT_PENDING,
        'CALLBACK_THREEDS' => PosInterface::PAYMENT_STATUS_PAYMENT_PENDING,
        'CALLBACK_PECCO'   => PosInterface::PAYMENT_STATUS_PAYMENT_PENDING,
        'BKM_POS_PENDING'  => PosInterface::PAYMENT_STATUS_PAYMENT_PENDING,
    ];

    /** @var array<string|int, PosInterface::MODEL_*> */
    protected array $secureTypeMappings = [
        0 => PosInterface::MODEL_NON_SECURE,
        1  => PosInterface::MODEL_3D_SECURE,
    ];

    /** @var array<PosInterface::TX_TYPE_*, string|array<PosInterface::MODEL_*, string>> */
    protected array $txTypeMappings = [
        PosInterface::TX_TYPE_PAY_AUTH => 'AUTH',
        PosInterface::TX_TYPE_PAY_PRE_AUTH => 'PRE_AUTH',
        PosInterface::TX_TYPE_PAY_POST_AUTH => 'POST_AUTH',
    ];

    /** @var array<string, PosInterface::TX_TYPE_*> */
    private array $historyTxTypeMappings = [
        'CANCEL'  => PosInterface::TX_TYPE_CANCEL,
        'PAYMENT' => PosInterface::TX_TYPE_PAY_AUTH,
        'REFUND'  => PosInterface::TX_TYPE_REFUND,
    ];

    /** @var array<string, PosInterface::PAYMENT_STATUS_*> */
    private array $orderHistoryStatusMappings = [
        'CANCELED'         => PosInterface::PAYMENT_STATUS_CANCELED,
        /**
         * we also get this status when the payment is canceled.
         */
        'PARTIALLY_REFUNDED' => PosInterface::PAYMENT_STATUS_PARTIALLY_REFUNDED,
        'REFUNDED'         => PosInterface::PAYMENT_STATUS_FULLY_REFUNDED,
    ];

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return IyzicoPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    public function mapTxType($txType, ?string $paymentModel = null): ?string
    {
        $mappedTxType = parent::mapTxType($txType, $paymentModel);
        if (null !== $mappedTxType) {
            return $mappedTxType;
        }

        return $this->historyTxTypeMappings[$txType] ?? null;
    }

    /**
     * @param PosInterface::TX_TYPE_*|null $requestTxType API request transaction type
     *
     * @inheritDoc
     */
    public function mapOrderStatus($orderStatus, ?string $requestTxType = null)
    {
        if (PosInterface::TX_TYPE_STATUS === $requestTxType) {
            parent::mapOrderStatus($orderStatus);
        }

        if (PosInterface::TX_TYPE_ORDER_HISTORY === $requestTxType) {
            return $this->orderHistoryStatusMappings[$orderStatus] ?? $orderStatus;
        }

        return $orderStatus;
    }

    /**
     * @param bool|int|string|null $securityType
     *
     * @inheritDoc
     */
    public function mapSecureType($securityType, ?string $apiRequestTxType = null): ?string
    {
        if (null === $securityType) {
            // Non Secure odeme threeDS deger null donuyor.
            return PosInterface::MODEL_NON_SECURE;
        }

        return parent::mapSecureType($securityType, $apiRequestTxType);
    }
}
