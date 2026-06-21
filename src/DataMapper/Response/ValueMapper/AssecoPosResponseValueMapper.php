<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\Response\ValueMapper;

use Mews\Pos\Gateways\AssecoPos;
use Mews\Pos\PosInterface;

class AssecoPosResponseValueMapper extends AbstractResponseValueMapper
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

    /** @var array<string, PosInterface::MODEL_*> */
    protected array $secureTypeMappings = [
        '3d'             => PosInterface::MODEL_3D_SECURE,
        '3d_pay'         => PosInterface::MODEL_3D_PAY,
        '3d_pay_hosting' => PosInterface::MODEL_3D_PAY_HOSTING,
        '3d_host'        => PosInterface::MODEL_3D_HOST,
        'regular'        => PosInterface::MODEL_NON_SECURE,
    ];

    /**
     * D : Başarısız işlem
     * A : Otorizasyon, gün sonu kapanmadan
     * C : Ön otorizasyon kapama, gün sonu kapanmadan
     * PN : Bekleyen İşlem
     * CNCL : İptal Edilmiş İşlem
     * ERR : Hata Almış İşlem
     * S : Satış
     * R : Teknik İptal gerekiyor
     * V : İptal
     *
     * @inheritdoc
     */
    protected array $orderStatusMappings = [
        'D'    => PosInterface::PAYMENT_STATUS_ERROR,
        'ERR'  => PosInterface::PAYMENT_STATUS_ERROR,
        'A'    => PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED,
        'C'    => PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED,
        'S'    => PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED,
        'PN'   => PosInterface::PAYMENT_STATUS_PAYMENT_PENDING,
        'CNCL' => PosInterface::PAYMENT_STATUS_CANCELED,
        'V'    => PosInterface::PAYMENT_STATUS_CANCELED,
    ];

    /**
     * @var array<string, PosInterface::TX_TYPE_*>
     */
    private array $historyResponseTxTypeMappings = [
        /**
         * S: Auth/PreAuth/PostAuth
         * C: Refund
         */
        'S' => PosInterface::TX_TYPE_PAY_AUTH,
        'C' => PosInterface::TX_TYPE_REFUND,
    ];

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return AssecoPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    public function mapTxType($txType, ?string $paymentModel = null): ?string
    {
        return $this->historyResponseTxTypeMappings[$txType] ?? null;
    }
}
