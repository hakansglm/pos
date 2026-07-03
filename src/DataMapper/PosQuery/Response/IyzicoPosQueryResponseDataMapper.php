<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\PosQuery\Response;

use Mews\Pos\Gateway\IyzicoPos;
use Mews\Pos\PosInterface;
use Mews\Pos\PosQuery\PosQueryInterface;

/**
 * @internal
 */
class IyzicoPosQueryResponseDataMapper extends AbstractQueryResponseDataMapper
{
    public const PROCEDURE_SUCCESS_CODE = 'success';

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return IyzicoPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    public function mapHistoryResponse(array $rawResponseData): array
    {
        $status = self::TX_STATUS_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === ($rawResponseData['status'] ?? null)) {
            $status = self::TX_STATUS_APPROVED;
        }

        $transactions = [];
        if (self::TX_STATUS_APPROVED === $status) {
            foreach ($rawResponseData['transactions'] ?? [] as $rawTx) {
                $transactions[] = $this->mapSingleHistoryTransaction($rawTx);
            }
        }

        return [
            'proc_return_code' => $rawResponseData['status'] ?? null,
            'error_code'       => self::TX_STATUS_APPROVED === $status ? null : ($rawResponseData['errorCode'] ?? null),
            'error_message'    => self::TX_STATUS_APPROVED === $status ? null : ($rawResponseData['errorMessage'] ?? null),
            'trans_count'      => \count($transactions),
            'transactions'     => $transactions,
            'current_page'     => $rawResponseData['currentPage'] ?? null,
            'total_pages'      => $rawResponseData['totalPageCount'] ?? null,
            'status'           => $status,
            'all'              => $rawResponseData,
        ];
    }

    /**
     * @inheritDoc
     */
    public function mapBinListResponse(array $rawResponseData): array
    {
        $isSuccess = self::PROCEDURE_SUCCESS_CODE === ($rawResponseData['status'] ?? null);

        $result                  = $this->getDefaultBinListResponse();
        $result['status']        = $isSuccess ? self::TX_STATUS_APPROVED : self::TX_STATUS_DECLINED;
        $result['error_message'] = $isSuccess ? null : ($rawResponseData['errorMessage'] ?? null);
        $result['all']           = $rawResponseData;

        if (!$isSuccess) {
            return $result;
        }

        $result['bin_list'][] = [
            'bin'         => $rawResponseData['binNumber'] ?? null,
            'bank_code'   => isset($rawResponseData['bankCode']) ? (string) $rawResponseData['bankCode'] : null,
            'bank_name'   => $rawResponseData['bankName'] ?? null,
            'card_type'   => $this->valueMapper->mapCardType($rawResponseData['cardAssociation'] ?? null),
            'card_class'  => $this->valueMapper->mapCardClass($rawResponseData['cardType'] ?? null),
            'card_family' => $this->valueMapper->mapCardFamilyName($rawResponseData['cardFamily'] ?? null),
        ];

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function mapInstallmentPricesResponse(array $rawResponseData): array
    {
        $result    = $this->getDefaultInstallmentPricesResponse();
        $isSuccess = self::PROCEDURE_SUCCESS_CODE === ($rawResponseData['status'] ?? null);

        $result['status']        = $isSuccess ? self::TX_STATUS_APPROVED : self::TX_STATUS_DECLINED;
        $result['error_message'] = $isSuccess ? null : ($rawResponseData['errorMessage'] ?? null);
        $result['all']           = $rawResponseData;

        if (self::TX_STATUS_APPROVED !== $result['status']) {
            return $result;
        }

        foreach ($rawResponseData['installmentDetails'] ?? [] as $detail) {
            $result['installment_prices'][] = [
                'bank_code'   => isset($detail['bankCode']) ? (int) $detail['bankCode'] : null,
                'bank_name'   => $detail['bankName'] ?? null,
                'card_prefix' => isset($detail['binNumber']) ? (string) $detail['binNumber'] : null,
                'card_type'   => $this->valueMapper->mapCardType($detail['cardAssociation'] ?? null),
                'card_class'  => $this->valueMapper->mapCardClass($detail['cardType'] ?? null),
                'card_family' => $this->valueMapper->mapCardFamilyName($detail['cardFamilyName'] ?? null),
                'prices'      => $this->parseInstallmentPrices($detail['installmentPrices'] ?? []),
            ];
        }

        return $result;
    }

    /**
     * @param array<int, array<string, float|int>> $installmentPrices
     *
     * @return array<int, array{installment: int, installment_price: float, total_price: float|null}>
     */
    private function parseInstallmentPrices(array $installmentPrices): array
    {
        $result = [];

        foreach ($installmentPrices as $entry) {
            $result[] = [
                'installment'       => (int) $entry['installmentNumber'],
                'installment_price' => (float) $entry['installmentPrice'],
                'total_price'       => (float) $entry['totalPrice'],
            ];
        }

        \usort($result, static fn (array $a, array $b): int => $a['installment'] <=> $b['installment']);

        return $result;
    }

    /**
     * @param array<string, mixed> $rawTx
     *
     * @return array<string, mixed>
     */
    private function mapSingleHistoryTransaction(array $rawTx): array
    {
        $txType            = PosQueryInterface::QUERY_TYPE_HISTORY;
        $transactionStatus = $rawTx['transactionStatus'] ?? null;
        $transactionType   = $rawTx['transactionType'] ?? null;

        $transaction                     = $this->getDefaultHistoryTxResponse();
        $transaction['transaction_type'] = null !== $transactionType
            ? $this->valueMapper->mapTxType($transactionType)
            : null;

        if ($transaction['transaction_type'] === PosInterface::TX_TYPE_PAY_AUTH) {
            $txStatus                      = \in_array($transactionStatus, [1, 2], true) ? self::TX_STATUS_APPROVED : self::TX_STATUS_DECLINED;
            $transaction['capture']        = self::TX_STATUS_APPROVED === $txStatus;
            $transaction['capture_amount'] = isset($rawTx['paidPrice'])
                ? $this->valueFormatter->formatAmount($rawTx['paidPrice'], $txType)
                : null;
        } else {
            $txStatus = self::TX_STATUS_APPROVED;
        }

        $transaction['transaction_id'] = $rawTx['transactionId'] ?? null;
        $transaction['auth_code']      = $rawTx['authCode'] ?? null;
        $transaction['ref_ret_num']    = $rawTx['hostReference'] ?? null;
        $transaction['payment_model']  = $this->valueMapper->mapSecureType($rawTx['threeDS'], $txType);
        $transaction['status']         = $txStatus;

        $transaction['first_amount']      = isset($rawTx['price'])
            ? $this->valueFormatter->formatAmount($rawTx['price'], $txType)
            : null;
        $transaction['currency']          = isset($rawTx['transactionCurrency'])
            ? $this->valueMapper->mapCurrency($rawTx['transactionCurrency'], $txType)
            : null;
        $transaction['transaction_time']  = isset($rawTx['transactionDate'])
            ? $this->valueFormatter->formatDateTime((string) $rawTx['transactionDate'], $txType)
            : null;
        $transaction['installment_count'] = isset($rawTx['installment'])
            ? $this->valueFormatter->formatInstallment((string) $rawTx['installment'], $txType)
            : null;

        if (self::TX_STATUS_DECLINED === $txStatus) {
            $transaction['order_status'] = PosInterface::PAYMENT_STATUS_ERROR;
        } elseif ($transaction['transaction_type'] === PosInterface::TX_TYPE_PAY_AUTH) {
            $transaction['order_status'] = PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED;
        } elseif ($transaction['transaction_type'] === PosInterface::TX_TYPE_CANCEL) {
            $transaction['order_status'] = PosInterface::PAYMENT_STATUS_CANCELED;
        } elseif ($transaction['transaction_type'] === PosInterface::TX_TYPE_REFUND) {
            $transaction['order_status'] = PosInterface::PAYMENT_STATUS_FULLY_REFUNDED;
        }

        return $transaction;
    }
}
