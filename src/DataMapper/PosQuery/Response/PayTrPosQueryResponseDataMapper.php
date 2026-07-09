<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\PosQuery\Response;

use Mews\Pos\Gateway\PayTrPos;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\PosQuery\PosQueryInterface;

/**
 * @internal
 */
class PayTrPosQueryResponseDataMapper extends AbstractQueryResponseDataMapper
{
    public const PROCEDURE_SUCCESS_CODE = 'success';

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return PayTrPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    public function mapBinListResponse(array $rawResponseData): array
    {
        $isSuccess = self::PROCEDURE_SUCCESS_CODE === ($rawResponseData['status'] ?? null);

        $result                  = $this->getDefaultBinListResponse();
        $result['status']        = $isSuccess ? self::TX_STATUS_APPROVED : self::TX_STATUS_DECLINED;
        $result['error_message'] = $isSuccess ? null : ($rawResponseData['err_msg'] ?? ($rawResponseData['status'] ?? null));
        $result['all']           = $rawResponseData;

        if (!$isSuccess) {
            return $result;
        }

        $result['bin_list'][] = [
            'bin'         => null,
            'bank_code'   => isset($rawResponseData['bankCode']) ? (string) $rawResponseData['bankCode'] : null,
            'bank_name'   => $rawResponseData['bank'] ?? null,
            'card_type'   => $this->valueMapper->mapCardType($rawResponseData['schema'] ?? null),
            'card_class'  => $this->valueMapper->mapCardClass($rawResponseData['cardType'] ?? null),
            'card_family' => $this->valueMapper->mapCardFamilyName($rawResponseData['brand'] ?? null),
        ];

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function mapHistoryResponse(array $rawResponseData): array
    {
        $procReturnCode = $this->getProcReturnCode($rawResponseData);
        $status         = self::TX_STATUS_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_STATUS_APPROVED;
        }

        $transactions = [];
        if (self::TX_STATUS_APPROVED === $status) {
            foreach ($rawResponseData['list'] ?? [] as $rawTx) {
                $transactions[] = $this->mapSingleHistoryTransaction($rawTx);
            }
        }

        return [
            'proc_return_code' => $procReturnCode,
            'error_code'       => self::TX_STATUS_APPROVED === $status ? null : ($rawResponseData['err_no'] ?? $procReturnCode),
            'error_message'    => self::TX_STATUS_APPROVED === $status ? null : ($rawResponseData['err_msg'] ?? null),
            'trans_count'      => \count($transactions),
            'transactions'     => $transactions,
            'status'           => $status,
            'all'              => $rawResponseData,
        ];
    }

    /**
     * @inheritDoc
     */
    public function mapInstallmentRatesResponse(array $rawResponseData): array
    {
        $result    = $this->getDefaultInstallmentRatesResponse();
        $isSuccess = 'success' === ($rawResponseData['status'] ?? null);

        $result['status']        = $isSuccess ? self::TX_STATUS_APPROVED : self::TX_STATUS_DECLINED;
        $result['error_message'] = $isSuccess ? null : ($rawResponseData['err_msg'] ?? null);
        $result['all']           = $rawResponseData;

        if (self::TX_STATUS_APPROVED !== $result['status']) {
            return $result;
        }

        $result['installment_rates'] = $this->parseOranlar($rawResponseData['oranlar'] ?? []);

        return $result;
    }

    /**
     * Converts {"world": {"taksit_2": 7.28, ...}, "axess": {...}} into the unified grouped structure.
     *
     * @param array<string, array<string, float|int>> $oranlar
     *
     * @return array<int, array{
     *          bank_code: int|null,
     *          bank_name: string|null,
     *          card_prefix: string|null,
     *          card_type: CreditCardInterface::CARD_TYPE_*|null,
     *          card_class: CreditCardInterface::CARD_CLASS_*|null,
     *          card_family: CreditCardInterface::CARD_FAMILY_*|string|null,
     *          rates: array<int, array{installment: int, rate: float, constant: float}>
     *      }>
     */
    private function parseOranlar(array $oranlar): array
    {
        $groups = [];

        foreach ($oranlar as $familyName => $rates) {
            $parsed = [];

            foreach ($rates as $key => $rate) {
                // Keys are "taksit_2", "taksit_3", … — strip the prefix to get the count.
                $count = (int) \substr($key, \strlen('taksit_'));
                if ($count < 2) {
                    continue;
                }

                $parsed[] = ['installment' => $count, 'rate' => (float) $rate, 'constant' => 0.0];
            }

            \usort($parsed, static fn (array $a, array $b): int => $a['installment'] <=> $b['installment']);

            $groups[] = [
                'bank_code'   => null,
                'bank_name'   => null,
                'card_prefix' => null,
                'card_type'   => null,
                'card_class'  => null,
                'card_family' => $this->valueMapper->mapCardFamilyName($familyName),
                'rates'       => $parsed,
            ];
        }

        return $groups;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function getProcReturnCode(array $response): ?string
    {
        return $response['status'] ?? null;
    }

    /**
     * @param array<string, mixed> $rawTx
     *
     * @return array<string, mixed>
     */
    private function mapSingleHistoryTransaction(array $rawTx): array
    {
        $txType = PosQueryInterface::QUERY_TYPE_HISTORY;

        $transactionType = isset($rawTx['islem_tipi'])
            ? $this->valueMapper->mapTxType($rawTx['islem_tipi'])
            : null;

        $currency = isset($rawTx['para_birimi'])
            ? $this->valueMapper->mapCurrency($rawTx['para_birimi'], $txType)
            : null;

        $transactionTime = isset($rawTx['islem_tarihi'])
            ? $this->valueFormatter->formatDateTime($rawTx['islem_tarihi'], $txType)
            : null;

        $amount = isset($rawTx['islem_tutari'])
            ? $this->valueFormatter->formatAmount($rawTx['islem_tutari'], $txType)
            : null;

        $captureAmount = isset($rawTx['odeme_tutari'])
            ? $this->valueFormatter->formatAmount($rawTx['odeme_tutari'], $txType)
            : null;

        $installmentCount = isset($rawTx['taksit'])
            ? $this->valueFormatter->formatInstallment((string) $rawTx['taksit'], $txType)
            : null;

        $defaultResponse = $this->getDefaultHistoryTxResponse();

        return $this->mergeArraysPreferNonNullValues($defaultResponse, [
            'order_id'          => $rawTx['siparis_no'] ?? null,
            'transaction_type'  => $transactionType,
            'transaction_time'  => $transactionTime,
            'first_amount'      => $amount,
            'capture_amount'    => $captureAmount,
            'currency'          => $currency,
            'masked_number'     => $rawTx['kart_no'] ?? null,
            'installment_count' => $installmentCount,
            'status'            => self::TX_STATUS_APPROVED,
        ]);
    }
}
