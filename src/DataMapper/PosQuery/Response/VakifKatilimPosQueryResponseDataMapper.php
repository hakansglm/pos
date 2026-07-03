<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\PosQuery\Response;

use Mews\Pos\Gateway\VakifKatilimPos;
use Mews\Pos\PosInterface;
use Mews\Pos\PosQuery\PosQueryInterface;

/**
 * @internal
 */
class VakifKatilimPosQueryResponseDataMapper extends AbstractQueryResponseDataMapper
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return VakifKatilimPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    public function mapHistoryResponse(array $rawResponseData): array
    {
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);

        $mappedTransactions = [];
        $procReturnCode     = $this->getProcReturnCode($rawResponseData);
        $status             = self::TX_STATUS_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_STATUS_APPROVED;
        }

        if (isset($rawResponseData['VPosOrderData']['OrderContract'])) {
            foreach ($rawResponseData['VPosOrderData']['OrderContract'] as $tx) {
                $mappedTransactions[] = $this->mapSingleHistoryTransaction($tx);
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

        if (null !== $procReturnCode && self::PROCEDURE_SUCCESS_CODE !== $procReturnCode) {
            $result['error_code']    = $procReturnCode;
            $result['error_message'] = $rawResponseData['ResponseMessage'];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function getProcReturnCode(array $response): ?string
    {
        return $response['ResponseCode'] ?? null;
    }

    /**
     * @param array<string, string|null> $rawTx
     *
     * @return array<string, int|string|null|float|bool|\DateTimeImmutable>
     */
    private function mapSingleHistoryTransaction(array $rawTx): array
    {
        $txType         = PosQueryInterface::QUERY_TYPE_HISTORY;
        $procReturnCode = $this->getProcReturnCode($rawTx);
        $status         = self::TX_STATUS_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_STATUS_APPROVED;
        }

        $defaultResponse = $this->getDefaultHistoryTxResponse();

        $defaultResponse['proc_return_code'] = $procReturnCode;
        $defaultResponse['status']           = $status;
        $defaultResponse['error_code']       = self::TX_STATUS_APPROVED === $status ? null : $procReturnCode;
        $defaultResponse['error_message']    = self::TX_STATUS_APPROVED === $status ? null : $rawTx['ResponseExplain'];
        $defaultResponse['currency']         = null !== $rawTx['FEC'] ? $this->valueMapper->mapCurrency($rawTx['FEC'], $txType) : null;
        $defaultResponse['payment_model']    = null !== $rawTx['TransactionSecurity'] ? $this->valueMapper->mapSecureType($rawTx['TransactionSecurity'], $txType) : null;
        $defaultResponse['ref_ret_num']      = $rawTx['RRN'];
        $defaultResponse['transaction_id']   = $rawTx['Stan'];
        $defaultResponse['transaction_time'] = null !== $rawTx['OrderDate'] ? $this->valueFormatter->formatDateTime($rawTx['OrderDate'], $txType) : null;
        $defaultResponse['order_id']         = $rawTx['MerchantOrderId'];
        $defaultResponse['remote_order_id']  = $rawTx['OrderId'];

        if (self::TX_STATUS_APPROVED === $status) {
            $defaultResponse['auth_code']         = $rawTx['ProvNumber'] ?? null;
            $defaultResponse['installment_count'] = $this->valueFormatter->formatInstallment($rawTx['InstallmentCount'], $txType);
            $defaultResponse['masked_number']     = $rawTx['CardNumber'];
            $defaultResponse['first_amount']      = null === $rawTx['FirstAmount'] ? null : $this->valueFormatter->formatAmount($rawTx['FirstAmount'], $txType);
            $rawLastOrderStatus                   = $rawTx['LastOrderStatus'] ?? $rawTx['LastOrderStatusDescription'];
            $defaultResponse['order_status']      = null === $rawLastOrderStatus ? null : $this->valueMapper->mapOrderStatus($rawLastOrderStatus);
            $initialOrderStatus                   = null === $rawTx['OrderStatus'] ? null : $this->valueMapper->mapOrderStatus($rawTx['OrderStatus']);

            if (PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED === $initialOrderStatus) {
                $defaultResponse['capture_amount'] = isset($rawTx['TranAmount']) ? $this->valueFormatter->formatAmount($rawTx['TranAmount'], $txType) : 0;
                $defaultResponse['capture']        = $defaultResponse['first_amount'] === $defaultResponse['capture_amount'];
                if ($defaultResponse['capture']) {
                    $defaultResponse['capture_time'] = $defaultResponse['transaction_time'];
                }
            } elseif (PosInterface::PAYMENT_STATUS_CANCELED === $initialOrderStatus) {
                $defaultResponse['cancel_time'] = $defaultResponse['transaction_time'];
            }
        }

        return $defaultResponse;
    }
}
