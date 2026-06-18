<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseDataMapper;

use Mews\Pos\DataMapper\ResponseValueMapper\GarantiPosResponseValueMapper;
use Mews\Pos\DataMapper\ResponseValueMapper\ResponseValueMapperInterface;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Gateways\GarantiPos;
use Mews\Pos\PosInterface;

/**
 * @phpstan-type PaymentStatusModel array{Order: array<string, string|array<string, string|null>>, Response: array<string, string>, Transaction: array<string, string>|array{Response: array<string, string>}}
 */
class GarantiPosResponseDataMapper extends AbstractResponseDataMapper
{
    /**
     * @var GarantiPosResponseValueMapper
     */
    protected ResponseValueMapperInterface $valueMapper;

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return GarantiPos::class === $gatewayClass;
    }

    /**
     * @param PaymentStatusModel $rawPaymentResponseData
     *
     * {@inheritDoc}
     */
    public function mapPaymentResponse(array $rawPaymentResponseData, string $txType, array $order): array
    {
        $this->logger->debug('mapping payment response', [$rawPaymentResponseData]);
        $mappedResponse = $this->mapPaymentResponseCommon($txType, PosInterface::MODEL_NON_SECURE, $rawPaymentResponseData);
        $mappedResponse['currency'] = $order['currency'];
        $mappedResponse['amount']   = $order['amount'];

        return $mappedResponse;
    }

    /**
     * @param PaymentStatusModel|null $rawPaymentResponseData
     *
     * {@inheritdoc}
     */
    public function map3DPaymentData(array $raw3DAuthResponseData, ?array $rawPaymentResponseData, string $txType, array $order): array
    {
        $this->logger->debug('mapping 3D payment data', [
            '3d_auth_response'   => $raw3DAuthResponseData,
            'provision_response' => $rawPaymentResponseData,
        ]);
        $raw3DAuthResponseData = $this->emptyStringsToNull($raw3DAuthResponseData);

        $paymentModel = PosInterface::MODEL_3D_SECURE;
        $mapped3DAuthData = $this->map3DCommonResponseData(
            $raw3DAuthResponseData,
            $txType
        );
        $mapped3DAuthData['3d_all'] = $raw3DAuthResponseData;

        $defaultPaymentResponse = $this->getDefaultPaymentResponse($txType, $paymentModel);
        $mappedPaymentResponse  = [];
        if (self::TX_APPROVED === $mapped3DAuthData['status'] && null !== $rawPaymentResponseData) {
            $mappedPaymentResponse = $this->mapPaymentResponseCommon($txType, $paymentModel, $rawPaymentResponseData);
        }

        if ([] === $mappedPaymentResponse) {
            return $this->mergeArraysPreferNonNullValues($defaultPaymentResponse, $mapped3DAuthData);
        }

        return $this->mergeArraysPreferNonNullValues($mapped3DAuthData, $mappedPaymentResponse);
    }

    /**
     * {@inheritdoc}
     */
    public function map3DPayResponseData(array $raw3DAuthResponseData, string $txType, array $order): array
    {
        $raw3DAuthResponseData = $this->emptyStringsToNull($raw3DAuthResponseData);

        $paymentModel = PosInterface::MODEL_3D_PAY;
        $threeDAuthResult = $this->map3DCommonResponseData(
            $raw3DAuthResponseData,
            $txType
        );
        $threeDAuthStatus = $threeDAuthResult['status'];
        $paymentStatus    = self::TX_DECLINED;
        $procReturnCode   = $raw3DAuthResponseData['procreturncode'];
        if (self::TX_APPROVED === $threeDAuthStatus && self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $paymentStatus = self::TX_APPROVED;
        }

        $defaultPaymentResponse           = $this->getDefaultPaymentResponse(
            $txType,
            $paymentModel
        );
        $defaultPaymentResponse['status'] = $paymentStatus;
        $defaultPaymentResponse['all']    = $raw3DAuthResponseData;

        if (self::TX_APPROVED !== $paymentStatus) {
            $defaultPaymentResponse['error_message'] = $raw3DAuthResponseData['errmsg'];
            $defaultPaymentResponse['error_code']    = $procReturnCode;
        } else {
            $defaultPaymentResponse['transaction_time'] = $this->valueFormatter->formatDateTime('now', $txType);
        }

        return $this->mergeArraysPreferNonNullValues($threeDAuthResult, $defaultPaymentResponse);
    }

    /**
     * {@inheritdoc}
     */
    public function map3DHostResponseData(array $raw3DAuthResponseData, string $txType, array $order): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritdoc}
     */
    public function mapRefundResponse(array $rawResponseData): array
    {
        return $this->mapCancelResponse($rawResponseData);
    }

    /**
     * @param PaymentStatusModel|array<string, string> $rawResponseData
     *
     * {@inheritdoc}
     */
    public function mapCancelResponse(array $rawResponseData): array
    {
        /** @var PaymentStatusModel $rawResponseData */
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $procReturnCode  = $this->getProcReturnCode($rawResponseData);
        $status          = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        $transaction = $rawResponseData['Transaction'];


        return [
            'order_id'         => $rawResponseData['Order']['OrderID'] ?? null,
            'group_id'         => $rawResponseData['Order']['GroupID'] ?? null,
            'transaction_id'   => null,
            'auth_code'        => $transaction['AuthCode'] ?? null,
            'ref_ret_num'      => $transaction['RetrefNum'] ?? null,
            'proc_return_code' => $procReturnCode,
            'error_code'       => $transaction['Response']['Code'] ?? null,
            'error_message'    => $transaction['Response']['ErrorMsg'] ?? null,
            'status'           => $status,
            'all'              => $rawResponseData,
        ];
    }

    /**
     * @param PaymentStatusModel|array<string, string> $rawResponseData
     *
     * {@inheritdoc}
     */
    public function mapStatusResponse(array $rawResponseData): array
    {
        /** @var PaymentStatusModel $rawResponseData */
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $procReturnCode  = $this->getProcReturnCode($rawResponseData);
        $status          = self::TX_DECLINED;
        $txType          = PosInterface::TX_TYPE_STATUS;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        /** @var array{Response: array<string, string|null>} $transaction */
        $transaction = $rawResponseData['Transaction'];
        /** @var array<string, string|null> $orderInqResult */
        $orderInqResult  = $rawResponseData['Order']['OrderInqResult'];
        $defaultResponse = $this->getDefaultStatusResponse($rawResponseData);

        $result = [
            'order_id'          => $rawResponseData['Order']['OrderID'] ?? null,
            'auth_code'         => $orderInqResult['AuthCode'] ?? null,
            'ref_ret_num'       => $orderInqResult['RetrefNum'] ?? null,
            'installment_count' => $this->valueFormatter->formatInstallment($orderInqResult['InstallmentCnt'], $txType),
            'proc_return_code'  => $procReturnCode,
            'order_status'      => null !== $orderInqResult['Status'] ? $this->valueMapper->mapOrderStatus($orderInqResult['Status'], $txType) : null,
            'status'            => $status,
            'error_code'        => self::TX_APPROVED === $status ? null : $transaction['Response']['Code'],
            'error_message'     => self::TX_APPROVED === $status ? null : $transaction['Response']['ErrorMsg'],
        ];
        if (self::TX_APPROVED === $status) {
            $transTime                  = $orderInqResult['ProvDate'] ?? $orderInqResult['PreAuthDate'];
            $result['transaction_time'] = $transTime === null ? null : $this->valueFormatter->formatDateTime($transTime, $txType);
            $result['capture_time']     = null !== $orderInqResult['AuthDate'] ? $this->valueFormatter->formatDateTime($orderInqResult['AuthDate'], $txType) : null;
            $result['masked_number']    = $orderInqResult['CardNumberMasked'];
            $amount                     = $orderInqResult['AuthAmount'];
            $result['capture_amount']   = null !== $amount ? $this->valueFormatter->formatAmount($amount, $txType) : null;
            $firstAmount                = $amount > 0 ? $amount : $orderInqResult['PreAuthAmount'];
            $result['first_amount']     = null !== $firstAmount ? $this->valueFormatter->formatAmount($firstAmount, $txType) : null;
            $result['capture']          = $result['first_amount'] > 0 ? $result['capture_amount'] === $result['first_amount'] : null;
        }

        return \array_merge($defaultResponse, $result);
    }

    /**
     * {@inheritDoc}
     */
    public function mapOrderHistoryResponse(array $rawResponseData): array
    {
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $procReturnCode  = $this->getProcReturnCode($rawResponseData);
        $status          = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        $mappedTransactions = [];
        if (self::TX_APPROVED === $status) {
            $rawTransactions = $rawResponseData['Order']['OrderHistInqResult']['OrderTxnList']['OrderTxn'];
            if (\count($rawTransactions) !== \count($rawTransactions, COUNT_RECURSIVE)) {
                foreach ($rawTransactions as $transaction) {
                    $mappedTransaction    = $this->mapSingleOrderHistoryTransaction($transaction);
                    $mappedTransactions[] = $mappedTransaction;
                }
            } else {
                $mappedTransactions[] = $this->mapSingleOrderHistoryTransaction($rawTransactions);
            }
        }

        return [
            'order_id'         => $rawResponseData['Order']['OrderID'],
            'proc_return_code' => $procReturnCode,
            'error_code'       => self::TX_DECLINED === $status ? $procReturnCode : null,
            'error_message'    => self::TX_DECLINED === $status ? $rawResponseData['Transaction']['Response']['ErrorMsg'] : null,
            'status'           => $status,
            'trans_count'      => \count($mappedTransactions),
            'transactions'     => $mappedTransactions,
            'all'              => $rawResponseData,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function mapHistoryResponse(array $rawResponseData): array
    {
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $procReturnCode  = $this->getProcReturnCode($rawResponseData);
        $status          = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        $mappedTransactions = [];
        if (self::TX_APPROVED === $status) {
            $rawTransactions = $rawResponseData['Order']['OrderListInqResult']['OrderTxnList']['OrderTxn'];
            if (\count($rawTransactions) !== \count($rawTransactions, COUNT_RECURSIVE)) {
                foreach ($rawTransactions as $transaction) {
                    $mappedTransaction    = $this->mapSingleHistoryTransaction($transaction);
                    $mappedTransactions[] = $mappedTransaction;
                }
            } else {
                $mappedTransactions[] = $this->mapSingleHistoryTransaction($rawTransactions);
            }
        }

        return [
            'proc_return_code' => $procReturnCode,
            'error_code'       => self::TX_DECLINED === $status ? $procReturnCode : null,
            'error_message'    => self::TX_DECLINED === $status ? $rawResponseData['Transaction']['Response']['ErrorMsg'] : null,
            'status'           => $status,
            'trans_count'      => \count($mappedTransactions),
            'transactions'     => $mappedTransactions,
            'all'              => $rawResponseData,
        ];
    }

    /**
     * @inheritDoc
     */
    public function is3dAuthSuccess(?string $mdStatus): bool
    {
        return \in_array($mdStatus, ['1', '2', '3', '4'], true);
    }

    /**
     * @inheritDoc
     */
    public function extractMdStatus(array $raw3DAuthResponseData): ?string
    {
        return $raw3DAuthResponseData['mdstatus'] ?? null;
    }

    /**
     * returns mapped data of the common response data among all 3d models.
     *
     * @param array<string, string>       $raw3DAuthResponseData
     * @param PosInterface::TX_TYPE_PAY_* $txType
     *
     * @return array<string, mixed>
     */
    protected function map3DCommonResponseData(array $raw3DAuthResponseData, string $txType): array
    {
        $procReturnCode = $raw3DAuthResponseData['procreturncode'];
        $mdStatus       = $this->extractMdStatus($raw3DAuthResponseData);

        $status = self::TX_DECLINED;

        if ($this->is3dAuthSuccess($mdStatus) && 'Error' !== $raw3DAuthResponseData['response']) {
            $status = self::TX_APPROVED;
        }

        $result = [
            'order_id'             => $raw3DAuthResponseData['oid'],
            'transaction_id'       => null,
            'auth_code'            => null,
            'ref_ret_num'          => null,
            'transaction_security' => null === $mdStatus ? null : $this->mapResponseTransactionSecurity($mdStatus),
            'transaction_type'     => $this->valueMapper->mapTxType($raw3DAuthResponseData['txntype']),
            'proc_return_code'     => $procReturnCode,
            'md_status'            => $mdStatus,
            'status'               => $status,
            'masked_number'        => null,
            'amount'               => $this->valueFormatter->formatAmount($raw3DAuthResponseData['txnamount'], $txType),
            'currency'             => $this->valueMapper->mapCurrency($raw3DAuthResponseData['txncurrencycode'], $txType),
            'installment_count'    => $this->valueFormatter->formatInstallment($raw3DAuthResponseData['txninstallmentcount'], $txType),
            'tx_status'            => null,
            'eci'                  => null,
            'cavv'                 => null,
            'error_code'           => 'Error' === $raw3DAuthResponseData['response'] ? $procReturnCode : null,
            'error_message'        => self::TX_APPROVED === $status ? null : $raw3DAuthResponseData['errmsg'],
            'md_error_message'     => self::TX_APPROVED === $status ? null : $raw3DAuthResponseData['mderrormessage'],
            'payment_model'        => $this->valueMapper->mapSecureType($raw3DAuthResponseData['secure3dsecuritylevel'], $txType),
        ];

        if (self::TX_APPROVED === $status) {
            $result['auth_code']      = $raw3DAuthResponseData['authcode'];
            $result['transaction_id'] = $raw3DAuthResponseData['transid'];
            $result['ref_ret_num']    = $raw3DAuthResponseData['hostrefnum'];
            $result['masked_number']  = $raw3DAuthResponseData['MaskedPan'];
            $result['tx_status']      = $raw3DAuthResponseData['txnstatus'];
            $result['eci']            = $raw3DAuthResponseData['eci'];
            $result['cavv']           = $raw3DAuthResponseData['cavv'];
        }

        return $result;
    }

    /**
     * @param string $mdStatus
     *
     * @return string
     */
    protected function mapResponseTransactionSecurity(string $mdStatus): string
    {
        if (!$this->is3dAuthSuccess($mdStatus)) {
            return 'MPI fallback';
        }

        if ('1' === $mdStatus) {
            return 'Full 3D Secure';
        }

        // ['2', '3', '4']
        return 'Half 3D Secure';
    }


    /**
     * Get ProcReturnCode
     *
     * @phpstan-param PaymentStatusModel                                    $response
     *
     * @param array{Transaction: array{Response: array{Code: string|null}}} $response
     *
     * @return string|null
     */
    protected function getProcReturnCode(array $response): ?string
    {
        return $response['Transaction']['Response']['Code'] ?? null;
    }

    /**
     * @param array<string, string|null> $rawTx
     *
     * @return array<string, int|string|null|float|bool|\DateTime>
     */
    private function mapSingleOrderHistoryTransaction(array $rawTx): array
    {
        $txType         = PosInterface::TX_TYPE_ORDER_HISTORY;
        $procReturnCode = $rawTx['Status'];
        $status         = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        $defaultResponse                     = $this->getDefaultOrderHistoryTxResponse();
        $defaultResponse['auth_code']        = $rawTx['AuthCode'] ?? null;
        $defaultResponse['ref_ret_num']      = $rawTx['RetrefNum'] ?? null;
        $defaultResponse['proc_return_code'] = $procReturnCode;
        $defaultResponse['status']           = $status;
        $defaultResponse['error_code']       = self::TX_APPROVED === $status ? null : $procReturnCode;
        $defaultResponse['transaction_type'] = $rawTx['Type'] === null ? null : $this->valueMapper->mapTxType($rawTx['Type']);

        if (self::TX_APPROVED === $status) {
            $transTime = $rawTx['ProvDate'] ?? $rawTx['PreAuthDate'] ?? $rawTx['AuthDate'];
            if (null !== $transTime) {
                $defaultResponse['transaction_time'] = $this->valueFormatter->formatDateTime($transTime, $txType);
            }

            $defaultResponse['capture_time']     = null !== $rawTx['AuthDate'] ? $this->valueFormatter->formatDateTime($rawTx['AuthDate'], $txType) : null;
            $amount                              = $rawTx['AuthAmount'];
            $defaultResponse['capture_amount']   = null !== $amount ? $this->valueFormatter->formatAmount($amount, $txType) : null;
            $firstAmount                         = $amount > 0 ? $amount : $rawTx['PreAuthAmount'];
            $defaultResponse['first_amount']     = null !== $firstAmount ? $this->valueFormatter->formatAmount($firstAmount, $txType) : null;
            $defaultResponse['capture']          = $defaultResponse['first_amount'] > 0 ? $defaultResponse['capture_amount'] === $defaultResponse['first_amount'] : null;
            $defaultResponse['currency']         = '0' !== $rawTx['CurrencyCode'] && null !== $rawTx['CurrencyCode'] ? $this->valueMapper->mapCurrency($rawTx['CurrencyCode'], $txType) : null;
        }

        return $defaultResponse;
    }

    /**
     * @param array<string, string|null> $rawTx
     *
     * @return array<string, mixed>
     */
    private function mapSingleHistoryTransaction(array $rawTx): array
    {
        $txType         = PosInterface::TX_TYPE_HISTORY;
        $procReturnCode = $rawTx['ResponseCode'];
        $status         = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        $defaultResponse                     = $this->getDefaultOrderHistoryTxResponse();
        $defaultResponse['auth_code']        = $rawTx['AuthCode'] ?? null;
        $defaultResponse['ref_ret_num']      = $rawTx['RetrefNum'] ?? null;
        $defaultResponse['order_id']         = $rawTx['OrderID'];
        $defaultResponse['batch_num']        = $rawTx['BatchNum'];
        $defaultResponse['proc_return_code'] = $procReturnCode;
        $defaultResponse['transaction_type'] = null !== $rawTx['TrxType'] ? $this->valueMapper->mapTxType($rawTx['TrxType']) : null;
        $defaultResponse['order_status']     = null !== $rawTx['Status'] ? $this->valueMapper->mapOrderStatus($rawTx['Status'], $txType, $defaultResponse['transaction_type']) : null;
        $defaultResponse['status']           = $status;
        $defaultResponse['error_code']       = self::TX_APPROVED === $status ? null : $procReturnCode;
        $defaultResponse['error_message']    = self::TX_APPROVED === $status ? null : $rawTx['SysErrMsg'];

        $defaultResponse['payment_model']    = $this->valueMapper->mapSecureType($rawTx['SafeType'] ?? '', $txType);
        $defaultResponse['transaction_time'] = null !== $rawTx['LastTrxDate'] ? $this->valueFormatter->formatDateTime($rawTx['LastTrxDate'], $txType) : null;
        if (self::TX_APPROVED === $status) {
            $defaultResponse['masked_number']     = $rawTx['CardNumberMasked'];
            $defaultResponse['installment_count'] = $this->valueFormatter->formatInstallment($rawTx['InstallmentCnt'], $txType);
            $defaultResponse['currency']          = null !== $rawTx['CurrencyCode'] ? $this->valueMapper->mapCurrency($rawTx['CurrencyCode'], $txType) : null;
            $defaultResponse['first_amount']      = null !== $rawTx['AuthAmount'] ? $this->valueFormatter->formatAmount($rawTx['AuthAmount'], $txType) : null;
            if ($defaultResponse['order_status'] === PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED) {
                $defaultResponse['capture_amount'] = $defaultResponse['first_amount'];
                $defaultResponse['capture']        = $defaultResponse['first_amount'] > 0 ? $defaultResponse['capture_amount'] === $defaultResponse['first_amount'] : null;
                $defaultResponse['capture_time']   = $defaultResponse['transaction_time'];
            }
        }

        return $defaultResponse;
    }

    /**
     * @param PosInterface::TX_TYPE_PAY_* $txType
     * @param PosInterface::MODEL_*       $paymentModel
     * @param PaymentStatusModel          $rawPaymentResponseData
     *
     * @return array<string, mixed>
     */
    private function mapPaymentResponseCommon(string $txType, string $paymentModel, array $rawPaymentResponseData): array
    {
        /** @var PaymentStatusModel $rawPaymentResponseData */
        $rawPaymentResponseData = $this->emptyStringsToNull($rawPaymentResponseData);
        $procReturnCode         = $this->getProcReturnCode($rawPaymentResponseData);
        $status = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        $defaultResponse = $this->getDefaultPaymentResponse($txType, $paymentModel);
        $transaction     = $rawPaymentResponseData['Transaction'];

        /** @var string $provDate */
        $provDate = $transaction['ProvDate'] ?? 'now';

        $mappedResponse = [
            'order_id'         => $rawPaymentResponseData['Order']['OrderID'],
            'group_id'         => $rawPaymentResponseData['Order']['GroupID'],
            'auth_code'        => self::TX_APPROVED === $status ? $transaction['AuthCode'] : null,
            'ref_ret_num'      => self::TX_APPROVED === $status ? $transaction['RetrefNum'] : null,
            'batch_num'        => self::TX_APPROVED === $status ? $transaction['BatchNum'] : null,
            'transaction_time' => self::TX_APPROVED === $status ? $this->valueFormatter->formatDateTime($provDate, $txType) : null,
            'proc_return_code' => $procReturnCode,
            'status'           => $status,
            'error_code'       => self::TX_APPROVED !== $status ? $transaction['Response']['ReasonCode'] : null,
            'error_message'    => self::TX_APPROVED !== $status ? $transaction['Response']['ErrorMsg'] : null,
            'all'              => $rawPaymentResponseData,
        ];

        $this->logger->debug('mapped payment response', $mappedResponse);

        return $this->mergeArraysPreferNonNullValues($defaultResponse, $mappedResponse);
    }
}
