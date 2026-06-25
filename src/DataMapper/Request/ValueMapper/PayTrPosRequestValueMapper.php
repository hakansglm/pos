<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\Request\ValueMapper;

use Mews\Pos\Gateway\PayTrPos;
use Mews\Pos\PosInterface;

class PayTrPosRequestValueMapper extends AbstractRequestValueMapper
{
    /** @var non-empty-array<PosInterface::CURRENCY_*, string> */
    protected array $currencyMappings = [
        PosInterface::CURRENCY_TRY => 'TL',
        PosInterface::CURRENCY_USD => 'USD',
        PosInterface::CURRENCY_EUR => 'EUR',
        PosInterface::CURRENCY_GBP => 'GBP',
        PosInterface::CURRENCY_RUB => 'RUB',
    ];

    /** @var array<PosInterface::LANG_*, string> */
    protected array $langMappings = [
        PosInterface::LANG_TR => 'tr',
        PosInterface::LANG_EN => 'en',
    ];

    /** @inheritDoc */
    public static function supports(string $gatewayClass): bool
    {
        return PayTrPos::class === $gatewayClass;
    }
}
