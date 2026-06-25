<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\Response\ValueMapper;

use Mews\Pos\Gateway\PayTrPos;
use Mews\Pos\PosInterface;

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
}
