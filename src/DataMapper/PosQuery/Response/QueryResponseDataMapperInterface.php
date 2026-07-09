<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\PosQuery\Response;

use Mews\Pos\DataMapper\Response\Mapper\ResponseDataMapperInterface;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\PosInterface;

/**
 * @internal
 */
interface QueryResponseDataMapperInterface
{
    /** @var string */
    public const TX_STATUS_APPROVED = ResponseDataMapperInterface::TX_APPROVED;

    /** @var string */
    public const TX_STATUS_DECLINED = ResponseDataMapperInterface::TX_DECLINED;

    /**
     * Normalizes the bank's raw history response into the library's unified format.
     *
     * @param array<string, mixed> $rawResponseData
     *
     * @return array<string, mixed>
     */
    public function mapHistoryResponse(array $rawResponseData): array;

    /**
     * Normalizes the bank's raw installment rates response into the library's unified format.
     *
     * @param array<string, mixed> $rawResponseData
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
     */
    public function mapInstallmentRatesResponse(array $rawResponseData): array;

    /**
     * Normalizes the bank's raw installment prices response into the library's unified format.
     *
     * Unlike mapInstallmentRatesResponse() which returns percentage rates, this returns
     * the actual amounts the customer would pay per installment and in total.
     * Results are grouped by card program so that a single call without a BIN can return
     * prices for multiple card families at once.
     *
     * @param array<string, mixed> $rawResponseData
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
     */
    public function mapInstallmentPricesResponse(array $rawResponseData): array;

    /**
     * Normalizes the bank's raw BIN response into the library's unified format.
     *
     * Always returns `bin_list` as an array of 0 or more entries — whether the query
     * was a specific BIN lookup (Iyzico, PayTr: always 0 or 1 entry) or a full table
     * request (Garanti, Param without bin: many entries).
     *
     * @param array<string, mixed> $rawResponseData
     *
     * @return array{
     *     status: string,
     *     error_message: string|null,
     *     bin_list: array<int, array{
     *         bin: string|null,
     *         bank_code: string|null,
     *         bank_name: string|null,
     *         card_type: CreditCardInterface::CARD_TYPE_*|null,
     *         card_class: CreditCardInterface::CARD_CLASS_*|null,
     *         card_family: CreditCardInterface::CARD_FAMILY_*|string|null
     *     }>,
     *     all: array<string, mixed>
     * }
     */
    public function mapBinListResponse(array $rawResponseData): array;


    /**
     * @param class-string<PosInterface> $gatewayClass
     */
    public static function supports(string $gatewayClass): bool;
}
