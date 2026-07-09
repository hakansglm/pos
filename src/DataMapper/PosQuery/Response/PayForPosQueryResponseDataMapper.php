<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\PosQuery\Response;

use Mews\Pos\Gateway\PayForPos;
use Mews\Pos\PosInterface;

/**
 * @internal
 */
class PayForPosQueryResponseDataMapper extends AbstractQueryResponseDataMapper
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return PayForPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    public function mapHistoryResponse(array $rawResponseData): array
    {
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);

        $mappedTransactions = [];
        $procReturnCode     = null;
        $status             = null;
        $paymentRequest     = [];
        if (isset($rawResponseData['PaymentRequestExtended']['PaymentRequest'])) {
            $paymentRequest = $rawResponseData['PaymentRequestExtended']['PaymentRequest'];
            $procReturnCode = $this->getProcReturnCode($paymentRequest);
            $status         = self::TX_STATUS_DECLINED;
            if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
                $status               = self::TX_STATUS_APPROVED;
                $mappedTransactions[] = $this->mapSingleHistoryTransaction($paymentRequest);
            }
        } else {
            foreach ($rawResponseData['PaymentRequestExtended'] as $tx) {
                $mappedTransactions[] = $this->mapSingleHistoryTransaction($tx['PaymentRequest']);
            }
        }

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
            $result['error_message'] = $paymentRequest['ErrMsg'];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function getProcReturnCode(array $response): ?string
    {
        return $response['ProcReturnCode'] ?? null;
    }

    /**
     * @param array<string, string|null> $rawTx
     *
     * @return array<string, int|string|null|float|bool|\DateTimeImmutable>
     */
    private function mapSingleHistoryTransaction(array $rawTx): array
    {
        $txType         = PosInterface::TX_TYPE_ORDER_HISTORY;
        $procReturnCode = $this->getProcReturnCode($rawTx);
        $status         = self::TX_STATUS_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_STATUS_APPROVED;
        }

        $defaultResponse = $this->getDefaultHistoryTxResponse();

        $defaultResponse['proc_return_code'] = $procReturnCode;
        $defaultResponse['order_id']         = $rawTx['OrderId'];
        $defaultResponse['status']           = $status;
        $defaultResponse['error_code']       = self::TX_STATUS_APPROVED === $status ? null : $procReturnCode;
        $defaultResponse['transaction_type'] = $this->valueMapper->mapTxType((string) $rawTx['TxnType']);
        $defaultResponse['currency']         = null !== $rawTx['Currency'] ? $this->valueMapper->mapCurrency($rawTx['Currency'], $txType) : null;

        if (self::TX_STATUS_APPROVED === $status) {
            $orderStatus                         = null;
            $defaultResponse['auth_code']        = $rawTx['AuthCode'] ?? null;
            $defaultResponse['ref_ret_num']      = $rawTx['HostRefNum'] ?? null;
            $defaultResponse['masked_number']    = $rawTx['CardMask'];
            $defaultResponse['first_amount']     = null !== $rawTx['PurchAmount'] ? $this->valueFormatter->formatAmount($rawTx['PurchAmount'], $txType) : null;
            $defaultResponse['transaction_time'] = null !== $rawTx['InsertDatetime'] ? $this->valueFormatter->formatDateTime($rawTx['InsertDatetime'], $txType) : null;
            if (\in_array(
                $defaultResponse['transaction_type'],
                [PosInterface::TX_TYPE_PAY_AUTH, PosInterface::TX_TYPE_PAY_POST_AUTH],
                true
            )) {
                $defaultResponse['capture']        = true;
                $defaultResponse['capture_amount'] = $defaultResponse['first_amount'];
                $defaultResponse['capture_time']   = $defaultResponse['transaction_time'];
                $orderStatus                       = PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED;
            } elseif (PosInterface::TX_TYPE_PAY_PRE_AUTH === $defaultResponse['transaction_type']) {
                $defaultResponse['capture'] = false;
                $orderStatus                = PosInterface::PAYMENT_STATUS_PRE_AUTH_COMPLETED;
            }

            $defaultResponse['order_status'] = $orderStatus;
        }

        return $defaultResponse;
    }
}
