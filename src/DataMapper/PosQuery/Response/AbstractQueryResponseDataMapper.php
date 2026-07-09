<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\PosQuery\Response;

use Mews\Pos\DataMapper\Response\ValueFormatter\ResponseValueFormatterInterface;
use Mews\Pos\DataMapper\Response\ValueMapper\ResponseValueMapperInterface;
use Mews\Pos\Exception\UnsupportedTransactionTypeException;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\PosQuery\PosQueryInterface;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
abstract class AbstractQueryResponseDataMapper implements QueryResponseDataMapperInterface
{
    /** @var string */
    public const PROCEDURE_SUCCESS_CODE = '00';

    public function __construct(
        protected ResponseValueFormatterInterface $valueFormatter,
        protected ResponseValueMapperInterface    $valueMapper,
        protected LoggerInterface                 $logger
    ) {
    }

    /**
     * Default implementation: throws because most banks require a specific override.
     *
     * @inheritDoc
     */
    public function mapInstallmentRatesResponse(array $rawResponseData): array
    {
        throw new UnsupportedTransactionTypeException(PosQueryInterface::QUERY_TYPE_INSTALLMENT_RATES);
    }

    /**
     * Default implementation: throws because most banks require a specific override.
     *
     * @inheritDoc
     */
    public function mapInstallmentPricesResponse(array $rawResponseData): array
    {
        throw new UnsupportedTransactionTypeException(PosQueryInterface::QUERY_TYPE_INSTALLMENT_PRICES);
    }

    /**
     * @inheritDoc
     */
    public function mapBinListResponse(array $rawResponseData): array
    {
        throw new UnsupportedTransactionTypeException(PosQueryInterface::QUERY_TYPE_BIN_LIST);
    }

    /**
     * @return array{
     *     status: QueryResponseDataMapperInterface::TX_STATUS_*,
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
    protected function getDefaultBinListResponse(): array
    {
        return [
            'status'        => self::TX_STATUS_DECLINED,
            'error_message' => null,
            'bin_list'      => [],
            'all'           => [],
        ];
    }

    /**
     * @return array{
     *     status: QueryResponseDataMapperInterface::TX_STATUS_*,
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
    protected function getDefaultInstallmentPricesResponse(): array
    {
        return [
            'status'             => self::TX_STATUS_DECLINED,
            'error_message'      => null,
            'installment_prices' => [],
            'all'                => [],
        ];
    }

    /**
     * @return array{
     *     status: QueryResponseDataMapperInterface::TX_STATUS_*,
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
    protected function getDefaultInstallmentRatesResponse(): array
    {
        return [
            'status'            => self::TX_STATUS_DECLINED,
            'error_message'     => null,
            'installment_rates' => [],
            'all'               => [],
        ];
    }

    /**
     * @return array<string, int|string|null|float|bool|\DateTimeImmutable>
     */
    protected function getDefaultHistoryTxResponse(): array
    {
        return [
            'auth_code'        => null,
            'proc_return_code' => null,
            'transaction_id'   => null,
            'order_id'         => null,
            'transaction_time' => null,
            'capture_time'     => null,
            'error_message'    => null,
            'ref_ret_num'      => null,
            'order_status'     => null,
            'transaction_type' => null,
            'first_amount'     => null,
            'capture_amount'   => null,
            'status'           => self::TX_STATUS_DECLINED,
            'error_code'       => null,
            'capture'          => null,
            'currency'         => null,
            'masked_number'    => null,
        ];
    }

    /**
     * If two arrays share a key, prefers the non-null value; when both are non-null, $arr2 wins.
     *
     * @param array<string, mixed> $arr1
     * @param array<string, mixed> $arr2
     *
     * @return array<string, mixed>
     */
    protected function mergeArraysPreferNonNullValues(array $arr1, array $arr2): array
    {
        $resultArray     = \array_diff_key($arr1, $arr2) + \array_diff_key($arr2, $arr1);
        $commonArrayKeys = \array_keys(\array_intersect_key($arr1, $arr2));
        foreach ($commonArrayKeys as $key) {
            $resultArray[$key] = $arr2[$key] ?? $arr1[$key];
        }

        return $resultArray;
    }

    /**
     * Recursively converts empty strings to null and trims string values.
     *
     * @param mixed $data
     *
     * @return mixed
     */
    protected function emptyStringsToNull($data)
    {
        $result = null;
        if (\is_string($data)) {
            $data   = \trim($data);
            $result = '' === $data ? null : $data;
        } elseif (\is_numeric($data)) {
            $result = $data;
        } elseif (\is_iterable($data)) {
            $result = [];
            foreach ($data as $key => $value) {
                $result[$key] = self::emptyStringsToNull($value);
            }
        }

        return $result;
    }
}
