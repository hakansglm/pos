<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\Response\ValueMapper;

use Mews\Pos\Gateway\InterPos;
use Mews\Pos\PosInterface;

class InterPosResponseValueMapper extends AbstractResponseValueMapper
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

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return InterPos::class === $gatewayClass;
    }
}
