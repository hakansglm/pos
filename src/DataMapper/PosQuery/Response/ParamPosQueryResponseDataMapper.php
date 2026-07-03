<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\PosQuery\Response;

use Mews\Pos\Gateway\ParamPos;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\PosInterface;
use Mews\Pos\PosQuery\PosQueryInterface;

/**
 * @internal
 */
class ParamPosQueryResponseDataMapper extends AbstractQueryResponseDataMapper
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return ParamPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    public function mapBinListResponse(array $rawResponseData): array
    {
        $result = $this->getDefaultBinListResponse();

        $rawResult      = $rawResponseData['BIN_SanalPosResponse']['BIN_SanalPosResult'] ?? [];
        $procReturnCode = $this->getProcReturnCode($rawResult);
        $isSuccess      = null !== $procReturnCode && $procReturnCode > 0;

        $result['status']        = $isSuccess ? self::TX_STATUS_APPROVED : self::TX_STATUS_DECLINED;
        $result['error_message'] = $isSuccess ? null : ($rawResult['Sonuc_Str'] ?? null);
        $result['all']           = $rawResponseData;

        if (!$isSuccess) {
            return $result;
        }

        $rawBins = $rawResult['DT_Bilgi']['diffgr:diffgram']['NewDataSet']['Temp'] ?? [];
        // Single-item datasets are decoded as an associative array, not an array of arrays.
        if (isset($rawBins['BIN'])) {
            $rawBins = [$rawBins];
        }

        foreach ($rawBins as $rawBin) {
            $result['bin_list'][] = $this->mapSingleBin($rawBin);
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function mapInstallmentRatesResponse(array $rawResponseData): array
    {
        $result = $this->getDefaultInstallmentRatesResponse();

        $rawResult      = $rawResponseData['TP_Ozel_Oran_SK_ListeResponse']['TP_Ozel_Oran_SK_ListeResult'] ?? [];
        $procReturnCode = $this->getProcReturnCode($rawResult);
        $isSuccess      = $procReturnCode > 0;

        $result['status']        = $isSuccess ? self::TX_STATUS_APPROVED : self::TX_STATUS_DECLINED;
        $result['error_message'] = $isSuccess ? null : ($rawResult['Sonuc_Str'] ?? null);
        $result['all']           = $rawResponseData;

        if (self::TX_STATUS_APPROVED !== $result['status']) {
            return $result;
        }

        $rawItems = $rawResult['DT_Bilgi']['diffgr:diffgram']['NewDataSet']['DT_Ozel_Oranlar_SK'] ?? [];
        // Single-item datasets are decoded as an associative array, not an array of arrays.
        if (isset($rawItems['Kredi_Karti_Banka'])) {
            $rawItems = [$rawItems];
        }

        $result['installment_rates'] = $this->parseInstallmentRates($rawItems);

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function mapHistoryResponse(array $rawResponseData): array
    {
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);

        $mappedTransactions = [];
        $rawResult          = $rawResponseData['TP_Islem_IzlemeResponse']['TP_Islem_IzlemeResult'];
        $procReturnCode     = $this->getProcReturnCode($rawResult);
        $status             = self::TX_STATUS_DECLINED;
        if ($procReturnCode > 0) {
            $status = self::TX_STATUS_APPROVED;
            foreach ($rawResult['DT_Bilgi']['diffgr:diffgram']['NewDataSet']['Temp'] as $rawTx) {
                $mappedTransactions[] = $this->mapSingleHistoryTransaction($rawTx);
            }
        }

        $mappedTransactions = \array_reverse($mappedTransactions);

        $result = [
            'proc_return_code' => $procReturnCode,
            'error_code'       => null,
            'error_message'    => null,
            'status'           => $status,
            'trans_count'      => \count($mappedTransactions),
            'transactions'     => $mappedTransactions,
            'all'              => $rawResponseData,
        ];

        if (self::TX_STATUS_APPROVED !== $status) {
            $result['error_code']    = $procReturnCode;
            $result['error_message'] = $rawResult['Sonuc_Str'];
        }

        return $result;
    }

    /**
     * @param array<string, string|null> $rawBin
     *
     * @return array{bin: string|null, bank_code: string|null, bank_name: string|null, card_type: CreditCardInterface::CARD_TYPE_*|null, card_class: CreditCardInterface::CARD_CLASS_*|null, card_family: null}
     */
    private function mapSingleBin(array $rawBin): array
    {
        return [
            'bin'         => $rawBin['BIN'] ?? null,
            'bank_code'   => isset($rawBin['Banka_Kodu']) ? (string) $rawBin['Banka_Kodu'] : null,
            'bank_name'   => $rawBin['Kart_Banka'] ?? null,
            'card_type'   => $this->valueMapper->mapCardType($rawBin['Kart_Org'] ?? null),
            'card_class'  => $this->valueMapper->mapCardClass($rawBin['Kart_Tip'] ?? null),
            'card_family' => null,
        ];
    }

    /**
     * Converts per-card-program rows (MO_01…MO_12) into the unified installment_rates structure.
     * Negative rates indicate an unavailable installment count and are excluded.
     * Single payment (installment = 1) is always excluded.
     *
     * @param array<int, array<string, mixed>> $items
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
    private function parseInstallmentRates(array $items): array
    {
        $groups = [];

        foreach ($items as $item) {
            $rates = [];

            for ($i = 2; $i <= 12; $i++) {
                $key  = 'MO_'.\str_pad((string) $i, 2, '0', \STR_PAD_LEFT);
                $rate = isset($item[$key]) ? (float) $item[$key] : null;

                if (null === $rate || $rate < 0) {
                    continue;
                }

                $rates[] = ['installment' => $i, 'rate' => $rate, 'constant' => 0.0];
            }

            if ([] !== $rates) {
                $rawName  = ($item['Kredi_Karti_Banka'] ?? null) ?: null;
                $groups[] = [
                    'bank_code'   => null,
                    'bank_name'   => null,
                    'card_prefix' => null,
                    'card_type'   => null,
                    'card_class'  => null,
                    'card_family' => $this->valueMapper->mapCardFamilyName($rawName),
                    'rates'       => $rates,
                ];
            }
        }

        return $groups;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function getProcReturnCode(array $response): ?int
    {
        $code = $response['Sonuc'] ?? $response['TURKPOS_RETVAL_Sonuc'] ?? null;

        return null !== $code ? (int) $code : null;
    }

    /**
     * @param array<string, mixed> $rawTx
     *
     * @return array<string, mixed>
     */
    private function mapSingleHistoryTransaction(array $rawTx): array
    {
        $txType                          = PosQueryInterface::QUERY_TYPE_HISTORY;
        $rawTx                           = $this->emptyStringsToNull($rawTx);
        $transaction                     = $this->getDefaultHistoryTxResponse();
        $procReturnCode                  = $this->getProcReturnCode($rawTx);
        $transaction['proc_return_code'] = $procReturnCode;
        if ($procReturnCode > 0) {
            $transaction['status'] = self::TX_STATUS_APPROVED;
        }

        $dateTime                        = $this->valueFormatter->formatDateTime($rawTx['Tarih'], $txType);
        $transaction['transaction_type'] = $this->valueMapper->mapTxType($rawTx['Tip_Str']);
        if (self::TX_STATUS_APPROVED === $transaction['status']) {
            $transaction['currency'] = isset($rawTx['PB'])
                ? $this->valueMapper->mapCurrency($rawTx['PB'], $txType)
                : null;
            $amount                  = null === $rawTx['Tutar']
                ? null : $this->valueFormatter->formatAmount($rawTx['Tutar'], PosQueryInterface::QUERY_TYPE_HISTORY);
            if (PosInterface::TX_TYPE_PAY_AUTH === $transaction['transaction_type']) {
                $transaction['first_amount']   = $amount;
                $transaction['capture_amount'] = $amount;
                $transaction['capture']        = true;
                $transaction['capture_time']   = $dateTime;
            } elseif (PosInterface::TX_TYPE_CANCEL === $transaction['transaction_type'] && $rawTx['Tutar'] < 0) {
                $transaction['refund_amount'] = $transaction['first_amount'];
            }

            if ($rawTx['Toplam_Iade_Tutar'] > 0) {
                $transaction['refund_amount'] = $this->valueFormatter->formatAmount(
                    $rawTx['Toplam_Iade_Tutar'],
                    PosQueryInterface::QUERY_TYPE_HISTORY
                );
            }
        } else {
            $transaction['error_code']    = $procReturnCode;
            $transaction['error_message'] = $rawTx['Sonuc_Str'];
        }

        $transaction['order_id']         = $rawTx['ORJ_ORDER_ID'];
        $transaction['payment_model']    = $this->valueMapper->mapSecureType($rawTx['Islem_Guvenlik'], $txType);
        $transaction['transaction_time'] = $dateTime;

        return $transaction;
    }
}
