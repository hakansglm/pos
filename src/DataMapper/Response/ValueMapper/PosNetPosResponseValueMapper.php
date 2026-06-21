<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\Response\ValueMapper;

use Mews\Pos\Gateway\PosNetPos;
use Mews\Pos\PosInterface;

class PosNetPosResponseValueMapper extends AbstractResponseValueMapper
{
    /** @var array<string, PosInterface::CURRENCY_*> */
    protected array $currencyMappings = [
        'TL' => PosInterface::CURRENCY_TRY,
        'US' => PosInterface::CURRENCY_USD,
        'EU' => PosInterface::CURRENCY_EUR,
        'GB' => PosInterface::CURRENCY_GBP,
        'JP' => PosInterface::CURRENCY_JPY,
        'RU' => PosInterface::CURRENCY_RUB,
    ];

    /** @var array<PosInterface::TX_TYPE_*, string> */
    protected array $txTypeMappings = [
        PosInterface::TX_TYPE_PAY_AUTH       => 'Sale',
        PosInterface::TX_TYPE_PAY_PRE_AUTH   => 'Auth',
        PosInterface::TX_TYPE_PAY_POST_AUTH  => 'Capt',
        PosInterface::TX_TYPE_CANCEL         => 'reverse',
        PosInterface::TX_TYPE_REFUND         => 'return',
        PosInterface::TX_TYPE_REFUND_PARTIAL => 'return',
        PosInterface::TX_TYPE_STATUS         => 'agreement',
    ];

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return PosNetPos::class === $gatewayClass;
    }
}
