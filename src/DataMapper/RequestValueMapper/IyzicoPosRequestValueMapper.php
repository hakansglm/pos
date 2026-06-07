<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestValueMapper;

use Mews\Pos\Gateways\IyzicoPos;
use Mews\Pos\PosInterface;

class IyzicoPosRequestValueMapper extends AbstractRequestValueMapper
{
    /**
     * {@inheritDoc}
     */
    protected array $txTypeMappings = [
        PosInterface::TX_TYPE_PAY_AUTH       => 'auth',
        PosInterface::TX_TYPE_PAY_PRE_AUTH   => 'preauth',
        PosInterface::TX_TYPE_PAY_POST_AUTH  => 'postauth',
        PosInterface::TX_TYPE_CANCEL         => 'cancel',
        PosInterface::TX_TYPE_REFUND         => 'refund',
        PosInterface::TX_TYPE_REFUND_PARTIAL => 'refund',
        PosInterface::TX_TYPE_STATUS         => 'detail',
        PosInterface::TX_TYPE_ORDER_HISTORY  => 'details',
        PosInterface::TX_TYPE_HISTORY        => 'transactions',
    ];

    /**
     * iyzico uses ISO 4217 currency codes as strings directly.
     *
     * @var non-empty-array<PosInterface::CURRENCY_*, string>
     */
    protected array $currencyMappings = [
        PosInterface::CURRENCY_TRY => 'TRY',
        PosInterface::CURRENCY_USD => 'USD',
        PosInterface::CURRENCY_EUR => 'EUR',
        PosInterface::CURRENCY_GBP => 'GBP',
        PosInterface::CURRENCY_JPY => 'JPY',
        PosInterface::CURRENCY_RUB => 'RUB',
    ];

    /** @var array<PosInterface::LANG_*, string> */
    protected array $langMappings = [
        PosInterface::LANG_TR => 'tr',
        PosInterface::LANG_EN => 'en',
    ];

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return IyzicoPos::class === $gatewayClass;
    }
}
