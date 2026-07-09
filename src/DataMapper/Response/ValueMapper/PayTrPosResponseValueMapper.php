<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\Response\ValueMapper;

use Mews\Pos\Gateway\PayTrPos;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\PosInterface;

/**
 * @internal
 */
class PayTrPosResponseValueMapper extends AbstractResponseValueMapper
{
    /**
     * @var array<PosInterface::TX_TYPE_*, string>
     *
     * islem_tipi values: S = sale (auth), I = refund (iade)
     */
    protected array $txTypeMappings = [
        PosInterface::TX_TYPE_PAY_AUTH => 'S',
        PosInterface::TX_TYPE_REFUND   => 'I',
    ];

    /** @var array<string, PosInterface::CURRENCY_*> */
    protected array $currencyMappings = [
        'TL'  => PosInterface::CURRENCY_TRY,
        'TRY' => PosInterface::CURRENCY_TRY,
        'USD' => PosInterface::CURRENCY_USD,
        'EUR' => PosInterface::CURRENCY_EUR,
        'GBP' => PosInterface::CURRENCY_GBP,
        'RUB' => PosInterface::CURRENCY_RUB,
    ];

    /** @inheritDoc */
    public static function supports(string $gatewayClass): bool
    {
        return PayTrPos::class === $gatewayClass;
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
            'VISA'                     => CreditCardInterface::CARD_TYPE_VISA,
            'MASTER', 'MASTERCARD'     => CreditCardInterface::CARD_TYPE_MASTERCARD,
            'AMEX', 'AMERICAN EXPRESS' => CreditCardInterface::CARD_TYPE_AMEX,
            'TROY'                     => CreditCardInterface::CARD_TYPE_TROY,
            default                    => null,
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
            'credit'  => CreditCardInterface::CARD_CLASS_CREDIT,
            'debit'   => CreditCardInterface::CARD_CLASS_DEBIT,
            'prepaid' => CreditCardInterface::CARD_CLASS_PREPAID,
            default   => null,
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
            'world'      => CreditCardInterface::CARD_FAMILY_WORLD,
            'axess'      => CreditCardInterface::CARD_FAMILY_AXESS,
            'cardfinans' => CreditCardInterface::CARD_FAMILY_CARDFINANS,
            'paraf'      => CreditCardInterface::CARD_FAMILY_PARAF,
            'advantage'  => CreditCardInterface::CARD_FAMILY_ADVANTAGE,
            'bonus'      => CreditCardInterface::CARD_FAMILY_BONUS,
            'saglamkart' => CreditCardInterface::CARD_FAMILY_SAGLAMKART,
            default      => $name,
        };
    }
}
