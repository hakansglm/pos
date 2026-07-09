<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\Response\ValueMapper;

use Mews\Pos\Gateway\Param3DHostPos;
use Mews\Pos\Gateway\ParamPos;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\PosInterface;

/**
 * @internal
 */
class ParamPosResponseValueMapper extends AbstractResponseValueMapper
{
    /** @var array<string, PosInterface::CURRENCY_*> */
    protected array $currencyMappings = [
        'TRL' => PosInterface::CURRENCY_TRY,
        'TL'  => PosInterface::CURRENCY_TRY,
        'EUR' => PosInterface::CURRENCY_EUR,
        'USD' => PosInterface::CURRENCY_USD,
    ];

    /** @var array<string|int, PosInterface::MODEL_*> */
    protected array $secureTypeMappings = [
        'NONSECURE' => PosInterface::MODEL_NON_SECURE,
        '3D'        => PosInterface::MODEL_3D_SECURE,
    ];

    /**
     * @var array<string, PosInterface::PAYMENT_STATUS_*>
     */
    protected array $orderStatusMappings = [
        'FAIL'           => PosInterface::PAYMENT_STATUS_ERROR,
        'BANK_FAIL'      => PosInterface::PAYMENT_STATUS_ERROR,
        'SUCCESS'        => PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED,
        'CANCEL'         => PosInterface::PAYMENT_STATUS_CANCELED,
        'REFUND'         => PosInterface::PAYMENT_STATUS_FULLY_REFUNDED,
        'PARTIAL_REFUND' => PosInterface::PAYMENT_STATUS_PARTIALLY_REFUNDED,
    ];

    /**
     * @var array<string, PosInterface::TX_TYPE_*>
     */
    private array $statusRequestTxMappings = [
        'SALE'      => PosInterface::TX_TYPE_PAY_AUTH,
        'PRE_AUTH'  => PosInterface::TX_TYPE_PAY_PRE_AUTH,
        'POST_AUTH' => PosInterface::TX_TYPE_PAY_POST_AUTH,
    ];

    /**
     * @var array<string, PosInterface::TX_TYPE_*>
     */
    private array $historyRequestTxMappings = [
        'Satış' => PosInterface::TX_TYPE_PAY_AUTH,
        'İptal' => PosInterface::TX_TYPE_CANCEL,
        'İade'  => PosInterface::TX_TYPE_REFUND,
    ];

    /**
     * @inheritDoc
     */
    public function mapCardType(?string $cardType): ?string
    {
        if (null === $cardType) {
            return null;
        }

        return match ($cardType) {
            'VISA' => CreditCardInterface::CARD_TYPE_VISA,
            'MASTER' => CreditCardInterface::CARD_TYPE_MASTERCARD,
            'AMEX' => CreditCardInterface::CARD_TYPE_AMEX,
            'TROY' => CreditCardInterface::CARD_TYPE_TROY,
            default => null,
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
            'Kredi Kartı' => CreditCardInterface::CARD_CLASS_CREDIT,
            'Debit Kart' => CreditCardInterface::CARD_CLASS_DEBIT,
            'Ön Ödemeli Kart' => CreditCardInterface::CARD_CLASS_PREPAID,
            default => null,
        };
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
            'World' => CreditCardInterface::CARD_FAMILY_WORLD,
            'Axess' => CreditCardInterface::CARD_FAMILY_AXESS,
            'Bonus' => CreditCardInterface::CARD_FAMILY_BONUS,
            'Maximum' => CreditCardInterface::CARD_FAMILY_MAXIMUM,
            'Paraf' => CreditCardInterface::CARD_FAMILY_PARAF,
            default => $name,
        };
    }

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
    public function mapTxType($txType, ?string $paymentModel = null): ?string
    {
        return $this->statusRequestTxMappings[$txType]
            ?? $this->historyRequestTxMappings[$txType]
            ?? parent::mapTxType($txType);
    }
}
