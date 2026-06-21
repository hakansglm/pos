<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\Response\ValueMapper;

use Mews\Pos\Gateway\PayForPos;
use Mews\Pos\PosInterface;

class PayForPosResponseValueMapper extends AbstractResponseValueMapper
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
        PosInterface::TX_TYPE_PAY_AUTH       => 'Auth',
        PosInterface::TX_TYPE_PAY_PRE_AUTH   => 'PreAuth',
        PosInterface::TX_TYPE_PAY_POST_AUTH  => 'PostAuth',
        PosInterface::TX_TYPE_CANCEL         => 'Void',
        PosInterface::TX_TYPE_REFUND         => 'Refund',
        PosInterface::TX_TYPE_REFUND_PARTIAL => 'Refund',
        PosInterface::TX_TYPE_HISTORY        => 'TxnHistory',
        PosInterface::TX_TYPE_STATUS         => 'OrderInquiry',
    ];

    /** @var array<string, PosInterface::MODEL_*> */
    protected array $secureTypeMappings = [
        '3DModel'   => PosInterface::MODEL_3D_SECURE,
        '3DPay'     => PosInterface::MODEL_3D_PAY,
        '3DHost'    => PosInterface::MODEL_3D_HOST,
        'NonSecure' => PosInterface::MODEL_NON_SECURE,
    ];

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return PayForPos::class === $gatewayClass;
    }
}
