<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\Request\ValueMapper;

use Mews\Pos\Gateway\GarantiPos;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\PosInterface;
use Mews\Pos\PosQuery\PosQueryInterface;

/**
 * @internal
 */
class GarantiPosRequestValueMapper extends AbstractRequestValueMapper
{
    /**
     * {@inheritDoc}
     */
    protected array $txTypeMappings = [
        PosInterface::TX_TYPE_PAY_AUTH         => 'sales',
        PosInterface::TX_TYPE_PAY_PRE_AUTH     => 'preauth',
        PosInterface::TX_TYPE_PAY_POST_AUTH    => 'postauth',
        PosInterface::TX_TYPE_CANCEL           => 'void',
        PosInterface::TX_TYPE_REFUND           => 'refund',
        PosInterface::TX_TYPE_REFUND_PARTIAL   => 'refund',
        PosInterface::TX_TYPE_ORDER_HISTORY    => 'orderhistoryinq',
        PosQueryInterface::QUERY_TYPE_HISTORY  => 'orderlistinq',
        PosQueryInterface::QUERY_TYPE_BIN_LIST => 'bininq',
        PosInterface::TX_TYPE_STATUS           => 'orderinq',
    ];

    /**
     * {@inheritdoc}
     */
    protected array $recurringOrderFrequencyMappings = [
        'DAY'   => 'D',
        'WEEK'  => 'W',
        'MONTH' => 'M',
    ];

    /**
     * {@inheritdoc}
     */
    protected array $secureTypeMappings = [
        PosInterface::MODEL_3D_SECURE => '3D',
        PosInterface::MODEL_3D_PAY    => '3D_PAY',
    ];

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return GarantiPos::class === $gatewayClass;
    }

    /**
     * Maps a unified card class constant to the Garanti BINInq CardType value.
     * Returns 'A' (all) when no class is specified or the class has no dedicated filter.
     *
     * @param CreditCardInterface::CARD_CLASS_*|null $cardClass
     */
    public function mapCardClass(?string $cardClass): string
    {
        return match ($cardClass) {
            CreditCardInterface::CARD_CLASS_CREDIT => 'C',
            CreditCardInterface::CARD_CLASS_DEBIT => 'D',
            default => 'A',
        };
    }
}
