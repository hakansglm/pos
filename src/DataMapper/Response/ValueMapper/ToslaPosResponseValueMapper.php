<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\Response\ValueMapper;

use Mews\Pos\Gateway\ToslaPos;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\PosInterface;

/**
 * @internal
 */
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
    public function mapCardType(?string $cardType): ?string
    {
        if (null === $cardType) {
            return null;
        }

        return match (\strtolower($cardType)) {
            'visa'                                        => CreditCardInterface::CARD_TYPE_VISA,
            'mastercard', 'master card'                   => CreditCardInterface::CARD_TYPE_MASTERCARD,
            'amex', 'americanexpress', 'american express' => CreditCardInterface::CARD_TYPE_AMEX,
            'troy'                                        => CreditCardInterface::CARD_TYPE_TROY,
            default                                       => null,
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
            'Card Finans' => CreditCardInterface::CARD_FAMILY_CARDFINANS,
            default       => $name,
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
            'Kredi Kartı'     => CreditCardInterface::CARD_CLASS_CREDIT,
            'Banka Kartı'     => CreditCardInterface::CARD_CLASS_DEBIT,
            'Ön Ödemeli Kart' => CreditCardInterface::CARD_CLASS_PREPAID,
            default           => null,
        };
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
