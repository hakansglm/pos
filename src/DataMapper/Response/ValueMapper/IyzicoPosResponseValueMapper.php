<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\Response\ValueMapper;

use Mews\Pos\Gateway\IyzicoPos;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\PosInterface;

/**
 * @internal
 */
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
    public function mapCardFamilyName(?string $name): ?string
    {
        if (null === $name) {
            return null;
        }

        return match ($name) {
            'Paraf'      => CreditCardInterface::CARD_FAMILY_PARAF,
            'Axess'      => CreditCardInterface::CARD_FAMILY_AXESS,
            'Bonus'      => CreditCardInterface::CARD_FAMILY_BONUS,
            'World'      => CreditCardInterface::CARD_FAMILY_WORLD,
            'Maximum'    => CreditCardInterface::CARD_FAMILY_MAXIMUM,
            'CardFinans' => CreditCardInterface::CARD_FAMILY_CARDFINANS,
            'Advantage'  => CreditCardInterface::CARD_FAMILY_ADVANTAGE,
            default      => $name,
        };
    }

    /**
     * @inheritDoc
     */
    public function mapCardType(?string $cardType): ?string
    {
        if (null === $cardType) {
            return null;
        }

        return match ($cardType) {
            'MASTER_CARD'       => CreditCardInterface::CARD_TYPE_MASTERCARD,
            'VISA'              => CreditCardInterface::CARD_TYPE_VISA,
            'TROY'              => CreditCardInterface::CARD_TYPE_TROY,
            'AMERICAN_EXPRESS'  => CreditCardInterface::CARD_TYPE_AMEX,
            default             => null,
        };
    }

    /**
     * @inheritDoc
     */
    public function mapCardClass(?string $cardClass): ?string
    {
        if (null === $cardClass) {
            return null;
        }

        return match ($cardClass) {
            'CREDIT_CARD'  => CreditCardInterface::CARD_CLASS_CREDIT,
            'DEBIT_CARD'   => CreditCardInterface::CARD_CLASS_DEBIT,
            'PREPAID_CARD' => CreditCardInterface::CARD_CLASS_PREPAID,
            default        => null,
        };
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
