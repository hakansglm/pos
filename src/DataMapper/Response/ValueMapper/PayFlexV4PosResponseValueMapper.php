<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\Response\ValueMapper;

use Mews\Pos\Gateway\PayFlexV4Pos;
use Mews\Pos\PosInterface;

class PayFlexV4PosResponseValueMapper extends AbstractResponseValueMapper
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
        PosInterface::TX_TYPE_PAY_AUTH       => 'Sale',
        PosInterface::TX_TYPE_PAY_PRE_AUTH   => 'Auth',
        PosInterface::TX_TYPE_PAY_POST_AUTH  => 'Capture',
        PosInterface::TX_TYPE_CANCEL         => 'Cancel',
        PosInterface::TX_TYPE_REFUND         => 'Refund',
        PosInterface::TX_TYPE_REFUND_PARTIAL => 'Refund',
        PosInterface::TX_TYPE_STATUS         => 'status',
    ];

    /**
     * @inheritdocs
     */
    protected array $secureTypeMappings = [
        '1' => PosInterface::MODEL_NON_SECURE,
        '2' => PosInterface::MODEL_3D_SECURE,
        '3' => PosInterface::MODEL_3D_PAY,
    ];

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return PayFlexV4Pos::class === $gatewayClass;
    }
}
