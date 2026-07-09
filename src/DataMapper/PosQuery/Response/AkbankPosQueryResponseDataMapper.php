<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\PosQuery\Response;

use Mews\Pos\DataMapper\Response\ValueMapper\AkbankPosResponseValueMapper;
use Mews\Pos\DataMapper\Response\ValueMapper\ResponseValueMapperInterface;
use Mews\Pos\Gateway\AkbankPos;
use Mews\Pos\PosInterface;
use Mews\Pos\PosQuery\PosQueryInterface;

/**
 * @internal
 */
class AkbankPosQueryResponseDataMapper extends AbstractQueryResponseDataMapper
{
    public const PROCEDURE_SUCCESS_CODE = 'VPS-0000';

    /** @var AkbankPosResponseValueMapper */
    protected ResponseValueMapperInterface $valueMapper;

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return AkbankPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    public function mapHistoryResponse(array $rawResponseData): array
    {
        $mappedTransactions = [];
        $procReturnCode     = $this->getProcReturnCode($rawResponseData);
        $status             = self::TX_STATUS_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_STATUS_APPROVED;
            foreach ($rawResponseData['data']['txnDetailList'] as $rawTx) {
                $mappedTransactions[] = $this->mapSingleHistoryTransaction($rawTx);
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
            $result['error_message'] = $rawResponseData['responseMessage'];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function getProcReturnCode(array $response): ?string
    {
        return $response['responseCode'] ?? null;
    }

    /**
     * @param array<string, mixed> $rawTx
     *
     * @return array<string, mixed>
     */
    private function mapSingleHistoryTransaction(array $rawTx): array
    {
        $txType      = PosQueryInterface::QUERY_TYPE_HISTORY;
        $transaction = $this->getDefaultHistoryTxResponse();

        $transaction['proc_return_code'] = $this->getProcReturnCode($rawTx);
        if (self::PROCEDURE_SUCCESS_CODE === $transaction['proc_return_code']) {
            $transaction['status'] = self::TX_STATUS_APPROVED;
        }

        $transaction['currency']          = $this->valueMapper->mapCurrency($rawTx['currencyCode'], $txType);
        $transaction['installment_count'] = $this->valueFormatter->formatInstallment($rawTx['installmentCount'], $txType);
        $transaction['transaction_type']  = $this->valueMapper->mapTxType($rawTx['txnCode']);
        $transaction['first_amount']      = null === $rawTx['amount'] ? null : $this->valueFormatter->formatAmount($rawTx['amount'], $txType);
        $transaction['transaction_time']  = $this->valueFormatter->formatDateTime($rawTx['txnDateTime'], $txType);

        if (self::TX_STATUS_APPROVED === $transaction['status']) {
            $transaction['order_id']      = $rawTx['orderId'];
            $transaction['masked_number'] = $rawTx['maskedCardNumber'];
            $transaction['ref_ret_num']   = $rawTx['rrn'];
            $transaction['batch_num']     = $rawTx['batchNumber'] ?? null;
            $transaction['order_status']  = $this->valueMapper->mapOrderStatus($rawTx['txnStatus'], $rawTx['preAuthStatus'] ?? null);
            $transaction['auth_code']     = $rawTx['authCode'];
            if (PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED === $transaction['order_status']) {
                if (\in_array(
                    $transaction['transaction_type'],
                    [PosInterface::TX_TYPE_PAY_AUTH, PosInterface::TX_TYPE_PAY_POST_AUTH],
                    true
                )) {
                    $transaction['capture_amount'] = null === $rawTx['amount'] ? null : $this->valueFormatter->formatAmount($rawTx['amount'], $txType);
                    $transaction['capture']        = $transaction['first_amount'] === $transaction['capture_amount'];
                    if ($transaction['capture']) {
                        $transaction['capture_time'] = $this->valueFormatter->formatDateTime($rawTx['txnDateTime'], $txType);
                    }
                } elseif (PosInterface::TX_TYPE_PAY_PRE_AUTH === $transaction['transaction_type']) {
                    $transaction['capture_amount'] = null === $rawTx['preAuthCloseAmount'] ? null : $this->valueFormatter->formatAmount($rawTx['preAuthCloseAmount'], $txType);
                    $transaction['capture']        = $transaction['first_amount'] === $transaction['capture_amount'];
                    if ($transaction['capture']) {
                        $transaction['capture_time'] = $this->valueFormatter->formatDateTime($rawTx['preAuthCloseDate'], $txType);
                    }
                }
            }
        } else {
            $transaction['error_code'] = $transaction['proc_return_code'];
        }

        return $transaction;
    }
}
