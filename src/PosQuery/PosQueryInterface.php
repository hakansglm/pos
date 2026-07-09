<?php

/**
 * @license MIT
 */

namespace Mews\Pos\PosQuery;

use Mews\Pos\Exception\UnsupportedTransactionTypeException;
use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\PosInterface;
use Psr\Http\Client\ClientExceptionInterface;

/**
 * Sipariş ile ilişkili olmayan banka sorguları için giriş noktası: ham API çağrıları ve genel işlem geçmişi.
 *
 * Örnek oluşturmak için PosQueryFactory::create() kullanın.
 */
interface PosQueryInterface
{
    /** @var string */
    public const QUERY_TYPE_CUSTOM_QUERY = 'custom_query';

    /** @var string */
    public const QUERY_TYPE_HISTORY = 'history';

    /** @var string */
    public const QUERY_TYPE_INSTALLMENT_RATES = 'installment_rates';

    /** @var string */
    public const QUERY_TYPE_INSTALLMENT_PRICES = 'installment_prices';

    /** @var string */
    public const QUERY_TYPE_BIN_LIST = 'bin_list';

    /**
     * Ham, bankaya özgü bir API isteği gönderir.
     *
     * Kimlik bilgileri ve hash, $requestData içinde yoksa kütüphane tarafından eklenir.
     * Yanıt diziye çözümlenir ancak normalize EDİLMEZ — banka API'sinden olduğu gibi döner.
     *
     * @param array<string, mixed>  $requestData
     * @param non-empty-string|null $apiUrl      Yapılandırmadaki varsayılan uç noktanın üzerine yazar.
     *
     * @return array<string, mixed>
     *
     * @throws ClientExceptionInterface
     */
    public function customQuery(array $requestData, ?string $apiUrl = null): array;

    /**
     * Belirli bir siparişe bağlı olmayan genel işlem geçmişini getirir.
     *
     * customQuery'nin aksine yanıt, QueryResponseDataMapperInterface aracılığıyla normalize EDİLİR.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     *
     * @throws UnsupportedTransactionTypeException gateway geçmiş sorgusunu desteklemiyorsa
     * @throws ClientExceptionInterface
     */
    public function history(array $data): array;

    /**
     * Verilen BIN numarası için taksit oranı seçeneklerini getirir.
     *
     * Yanıt, QueryResponseDataMapperInterface::mapInstallmentRatesResponse() aracılığıyla normalize EDİLİR.
     *
     * @param array<string, mixed> $params En az `bin` anahtarını içermeli (int, kartın ilk 6 hanesi).
     *
     * @return array{
     *     status: string,
     *     error_message: string|null,
     *     installment_rates: array<int, array{
     *         bank_code: int|null,
     *         bank_name: string|null,
     *         card_prefix: string|null,
     *         card_type: CreditCardInterface::CARD_TYPE_*|null,
     *         card_class: CreditCardInterface::CARD_CLASS_*|null,
     *         card_family: CreditCardInterface::CARD_FAMILY_*|string|null,
     *         rates: array<int, array{installment: int, rate: float, constant: float}>
     *     }>,
     *     all: array<string, mixed>
     * }
     *
     * @throws UnsupportedTransactionTypeException gateway bu sorguyu desteklemiyorsa
     * @throws ClientExceptionInterface
     */
    public function getInstallmentRates(array $params): array;

    /**
     * Verilen BIN ve işlem tutarı için hesaplanmış taksit tutarlarını getirir.
     *
     * getInstallmentRates()'in yüzde oranı döndürmesinin aksine bu metot,
     * müşterinin her taksitte ve toplamda ödeyeceği gerçek tutarları döndürür.
     *
     * Yanıt, QueryResponseDataMapperInterface::mapInstallmentPricesResponse() aracılığıyla normalize EDİLİR.
     *
     * @param array<string, mixed> $params `bin` (string) ve `amount` (float) anahtarlarını içermeli.
     *
     * @return array{
     *     status: string,
     *     error_message: string|null,
     *     installment_prices: array<int, array{
     *         bank_code: int|null,
     *         bank_name: string|null,
     *         card_prefix: string|null,
     *         card_type: CreditCardInterface::CARD_TYPE_*|null,
     *         card_class: CreditCardInterface::CARD_CLASS_*|null,
     *         card_family: CreditCardInterface::CARD_FAMILY_*|string|null,
     *         prices: array<int, array{installment: int, installment_price: float, total_price: float|null}>
     *     }>,
     *     all: array<string, mixed>
     * }
     *
     * @throws UnsupportedTransactionTypeException gateway bu sorguyu desteklemiyorsa
     * @throws ClientExceptionInterface
     */
    public function getInstallmentPrices(array $params): array;

    /**
     * Verilen BIN numarası için eşleşen kart kayıtlarını listeler.
     *
     * `$params['bin']` (string, kartın ilk 6–8 hanesi) opsiyoneldir:
     * - IyzicoPos ve PayTrPos için zorunludur (her zaman tek sonuç döner).
     * - ParamPos için opsiyoneldir: verilirse eşleşen kayıtlar, verilmezse tüm BIN tablosu döner.
     * - GarantiPos için opsiyoneldir: verilirse filtrelenmiş sonuçlar, verilmezse tüm BIN tablosu döner.
     *
     * Yanıt her zaman `bin_list` anahtarında bir dizi içerir (0 veya daha fazla kayıt).
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     *
     * @throws UnsupportedTransactionTypeException gateway bu sorguyu desteklemiyorsa
     * @throws ClientExceptionInterface
     */
    public function getBinList(array $params): array;

    /**
     * Bu gateway sınıfının verilen sorgu türünü destekleyip desteklemediğini döndürür.
     *
     * @param PosQueryInterface::QUERY_TYPE_* $queryType sabitlerinden biri.
     */
    public static function isSupportedQuery(string $queryType): bool;

    /**
     * @param class-string<PosInterface> $gatewayClass
     */
    public static function supports(string $gatewayClass): bool;

    /**
     * @return array<string, mixed>|null Null until a normalized query has been made.
     */
    public function getResponse(): ?array;

    /**
     * Returns true if the last normalized query (history / getInstallmentRates /
     * getInstallmentPrices) completed successfully.
     *
     * Always returns false after customQuery() — that response is not normalized.
     * Returns false if no normalized query has been made yet.
     */
    public function isSuccess(): bool;

    public function getAccount(): AbstractPosAccount;

    public function isTestMode(): bool;
}
