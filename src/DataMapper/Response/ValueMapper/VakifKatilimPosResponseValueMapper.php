<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\Response\ValueMapper;

use Mews\Pos\Gateways\VakifKatilimPos;
use Mews\Pos\PosInterface;

class VakifKatilimPosResponseValueMapper extends AbstractResponseValueMapper
{
    /** @var array<string, PosInterface::CURRENCY_*> */
    protected array $currencyMappings = [
        '0949' => PosInterface::CURRENCY_TRY,
        '0840' => PosInterface::CURRENCY_USD,
        '0978' => PosInterface::CURRENCY_EUR,
        '0826' => PosInterface::CURRENCY_GBP,
        '0392' => PosInterface::CURRENCY_JPY,
        '0810' => PosInterface::CURRENCY_RUB,
    ];

    /** @var array<string|int, PosInterface::MODEL_*> */
    protected array $secureTypeMappings = [
        '3' => PosInterface::MODEL_3D_SECURE,
        '5' => PosInterface::MODEL_NON_SECURE,
    ];

    /**
     * Order Status Codes
     *
     * @inheritDoc
     */
    protected array $orderStatusMappings = [
        1 => PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED,
        4 => PosInterface::PAYMENT_STATUS_FULLY_REFUNDED,
        5 => PosInterface::PAYMENT_STATUS_PARTIALLY_REFUNDED,
        6 => PosInterface::PAYMENT_STATUS_CANCELED,
    ];

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return VakifKatilimPos::class === $gatewayClass;
    }

    /**
     * in '0949' or '949' formats
     *
     * @inheritDoc
     */
    public function mapCurrency($currency, ?string $apiRequestTxType = null): ?string
    {
        // 949 => 0949; for the request gateway wants 0949 code, but in response they send 949 code.
        $currencyNormalized = \str_pad((string) $currency, 4, '0', STR_PAD_LEFT);

        return parent::mapCurrency($currencyNormalized, $apiRequestTxType);
    }
}
