<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\PosQuery\Response;

use Mews\Pos\Exception\UnsupportedTransactionTypeException;
use Mews\Pos\Gateway\ToslaPos;
use Mews\Pos\PosQuery\PosQueryInterface;

/**
 * @internal
 */
class ToslaPosQueryResponseDataMapper extends AbstractQueryResponseDataMapper
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return ToslaPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    public function mapInstallmentRatesResponse(array $rawResponseData): array
    {
        $result = $this->getDefaultInstallmentRatesResponse();

        $isSuccess = isset($rawResponseData['Code']) && 0 === $rawResponseData['Code'];

        $result['status']        = $isSuccess ? self::TX_STATUS_APPROVED : self::TX_STATUS_DECLINED;
        $result['error_message'] = $isSuccess ? null : ($rawResponseData['Message'] ?? null);
        $result['all']           = $rawResponseData;

        if (self::TX_STATUS_APPROVED !== $result['status']) {
            return $result;
        }

        $firstPackage = $rawResponseData['CommissionPackages'][0] ?? [];
        $rates        = $this->parseInstallmentInfo($firstPackage['InstallmentRate'] ?? []);

        if ([] !== $rates) {
            $result['installment_rates'] = [[
                'bank_code'   => isset($rawResponseData['BankCode']) ? (int) $rawResponseData['BankCode'] : null,
                'bank_name'   => $rawResponseData['BankName'] ?? null,
                'card_prefix' => isset($rawResponseData['CardPrefix']) ? (string) $rawResponseData['CardPrefix'] : null,
                'card_type'   => $this->valueMapper->mapCardType($rawResponseData['CardType'] ?? null),
                'card_class'  => $this->valueMapper->mapCardClass($rawResponseData['CardClass'] ?? null),
                'card_family' => null,
                'rates'       => $rates,
            ]];
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function mapInstallmentPricesResponse(array $rawResponseData): array
    {
        $result    = $this->getDefaultInstallmentPricesResponse();
        $isSuccess = isset($rawResponseData['Code']) && 0 === $rawResponseData['Code'];

        $result['status']        = $isSuccess ? self::TX_STATUS_APPROVED : self::TX_STATUS_DECLINED;
        $result['error_message'] = $isSuccess ? null : ($rawResponseData['Message'] ?? null);
        $result['all']           = $rawResponseData;

        if (self::TX_STATUS_APPROVED !== $result['status']) {
            return $result;
        }

        $prices = $this->parseInstallmentOptions($rawResponseData['InstallmentOptions'] ?? []);
        if ([] !== $prices) {
            $result['installment_prices'] = [[
                'bank_code'   => null,
                'bank_name'   => null,
                'card_prefix' => null,
                'card_type'   => null,
                'card_class'  => null,
                'card_family' => null,
                'prices'      => $prices,
            ]];
        }

        return $result;
    }

    /**
     * ToslaPos does not support history queries.
     *
     * @inheritDoc
     */
    public function mapHistoryResponse(array $rawResponseData): array
    {
        throw new UnsupportedTransactionTypeException(PosQueryInterface::QUERY_TYPE_HISTORY);
    }

    /**
     * Converts [{"Installment": 1, "Amount": 10000}, ...] into the unified prices structure.
     * The response `Amount` is the total price; per-installment price is derived by dividing.
     *
     * @param array<int, array<string, int|float|string|bool>> $installmentOptions
     *
     * @return array<int, array{installment: int, installment_price: float, total_price: float}>
     */
    private function parseInstallmentOptions(array $installmentOptions): array
    {
        $result = [];

        foreach ($installmentOptions as $entry) {
            $installment = (int) $entry['Installment'];
            $totalPrice  = (float) $entry['Amount'];

            $result[] = [
                'installment'       => $installment,
                'installment_price' => $installment > 0 ? \round($totalPrice / $installment, 2) : $totalPrice,
                'total_price'       => $totalPrice,
            ];
        }

        \usort($result, static fn (array $a, array $b): int => $a['installment'] <=> $b['installment']);

        return $result;
    }

    /**
     * Converts {"T2": {"Rate": 2.99, "Constant": 2}, "T3": {...}} into a flat sorted array.
     *
     * @param array<string, array<string, float|int>> $installmentInfo
     *
     * @return array<int, array{installment: int, rate: float, constant: float}>
     */
    private function parseInstallmentInfo(array $installmentInfo): array
    {
        $installments = [];

        foreach ($installmentInfo as $key => $info) {
            // Keys are "T2", "T3", … — strip the leading "T" to get the count.
            $count = (int) \ltrim($key, 'T');
            if ($count < 2) {
                continue;
            }

            $installments[] = [
                'installment' => $count,
                'rate'        => (float) ($info['Rate'] ?? 0),
                'constant'    => (float) ($info['Constant'] ?? 0),
            ];
        }

        \usort($installments, static fn (array $a, array $b): int => $a['installment'] <=> $b['installment']);

        return $installments;
    }
}
