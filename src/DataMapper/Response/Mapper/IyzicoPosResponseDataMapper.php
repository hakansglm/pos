<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\Response\Mapper;

use Mews\Pos\DataMapper\Response\ValueMapper\IyzicoPosResponseValueMapper;
use Mews\Pos\DataMapper\Response\ValueMapper\ResponseValueMapperInterface;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Gateways\IyzicoPos;
use Mews\Pos\PosInterface;

/**
 * Maps iyzico API responses to the unified response format.
 */
class IyzicoPosResponseDataMapper extends AbstractResponseDataMapper
{
    /** @var string */
    public const PROCEDURE_SUCCESS_CODE = 'success';

    /**
     * @var IyzicoPosResponseValueMapper
     */
    protected ResponseValueMapperInterface $valueMapper;

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return IyzicoPos::class === $gatewayClass;
    }

    /**
     * {@inheritDoc}
     */
    public function mapPaymentResponse(array $rawPaymentResponseData, string $txType, array $order): array
    {
        $this->logger->debug('mapping payment response', [$rawPaymentResponseData]);

        $mappedResponse = $this->mapPaymentCommonPaymentResponse($rawPaymentResponseData, $txType, PosInterface::MODEL_NON_SECURE);
        $this->logger->debug('mapped payment response', $mappedResponse);

        return $mappedResponse;
    }

    /**
     * {@inheritDoc}
     */
    public function map3DPaymentData(array $raw3DAuthResponseData, ?array $rawPaymentResponseData, string $txType, array $order): array
    {
        $this->logger->debug('mapping 3D payment data', [$raw3DAuthResponseData, $rawPaymentResponseData]);

        $raw3DAuthResponseDataCleaned = $this->emptyStringsToNull($raw3DAuthResponseData);

        $mdStatus = $this->extractMdStatus($raw3DAuthResponseDataCleaned);
        if ($this->is3dAuthSuccess($mdStatus)) {
            $mappedResponse = $this->mapPaymentCommonPaymentResponse(
                $rawPaymentResponseData ?? [],
                $txType,
                PosInterface::MODEL_3D_SECURE
            );
        } else {
            $mappedResponse                     = $this->getDefaultPaymentResponse($txType, PosInterface::MODEL_3D_SECURE);
            $mappedResponse['order_id']         = $raw3DAuthResponseDataCleaned['conversationId'];
            $mappedResponse['proc_return_code'] = $raw3DAuthResponseDataCleaned['status'];
        }

        $mappedResponse['md_status']            = $mdStatus;
        $mappedResponse['md_error_message']     = null;
        $mappedResponse['transaction_security'] = null;
        $mappedResponse['3d_all']               = $raw3DAuthResponseData;

        $this->logger->debug('mapped 3D payment data', $mappedResponse);

        return $mappedResponse;
    }

    /**
     * {@inheritDoc}
     */
    public function map3DPayResponseData(array $raw3DAuthResponseData, string $txType, array $order): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritDoc}
     */
    public function map3DHostResponseData(array $raw3DAuthResponseData, string $txType, array $order): array
    {
        $this->logger->debug('mapping 3D payment data', [$raw3DAuthResponseData]);

        $mdStatus = $this->extractMdStatus($raw3DAuthResponseData);

        if ($this->is3dAuthSuccess($mdStatus)) {
            $mappedResponse = $this->mapPaymentCommonPaymentResponse(
                $raw3DAuthResponseData,
                $txType,
                PosInterface::MODEL_3D_HOST
            );
        } else {
            $mappedResponse                     = $this->getDefaultPaymentResponse($txType, PosInterface::MODEL_3D_HOST);
            $mappedResponse['order_id']         = $raw3DAuthResponseData['conversationId'];
            $mappedResponse['proc_return_code'] = $raw3DAuthResponseData['status'];
            $mappedResponse['all']              = $raw3DAuthResponseData;
        }

        $mappedResponse['md_status']            = $mdStatus;
        $mappedResponse['md_error_message']     = null;
        $mappedResponse['transaction_security'] = null;

        $this->logger->debug('mapped 3D payment data', $mappedResponse);

        return $mappedResponse;
    }

    /**
     * {@inheritDoc}
     */
    public function mapRefundResponse(array $rawResponseData): array
    {
        $status = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === ($rawResponseData['status'] ?? null)) {
            $status = self::TX_APPROVED;
        }

        return [
            'order_id'         => $rawResponseData['conversationId'] ?? null,
            'auth_code'        => null,
            'ref_ret_num'      => $rawResponseData['hostReference'] ?? null,
            'proc_return_code' => $rawResponseData['status'] ?? null,
            'transaction_id'   => $rawResponseData['paymentTransactionId'] ?? null,
            'error_code'       => self::TX_DECLINED === $status ? ($rawResponseData['errorCode'] ?? null) : null,
            'error_message'    => self::TX_DECLINED === $status ? ($rawResponseData['errorMessage'] ?? null) : null,
            'status'           => $status,
            'all'              => $rawResponseData,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function mapCancelResponse(array $rawResponseData): array
    {
        $status = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === ($rawResponseData['status'] ?? null)) {
            $status = self::TX_APPROVED;
        }

        return [
            'order_id'         => $rawResponseData['conversationId'] ?? null,
            'auth_code'        => null,
            'ref_ret_num'      => $rawResponseData['cancelHostReference'] ?? null,
            'proc_return_code' => $rawResponseData['status'] ?? null,
            'transaction_id'   => $rawResponseData['paymentId'] ?? null,
            'error_code'       => self::TX_DECLINED === $status ? ($rawResponseData['errorCode'] ?? null) : null,
            'error_message'    => self::TX_DECLINED === $status ? ($rawResponseData['errorMessage'] ?? null) : null,
            'status'           => $status,
            'all'              => $rawResponseData,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function mapStatusResponse(array $rawResponseData): array
    {
        $txType = PosInterface::TX_TYPE_STATUS;
        $status = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === ($rawResponseData['status'] ?? null)) {
            $status = self::TX_APPROVED;
        }

        $defaultResponse = $this->getDefaultStatusResponse($rawResponseData);

        $defaultResponse['proc_return_code'] = $rawResponseData['status'] ?? null;
        $defaultResponse['order_id']         = $rawResponseData['conversationId'] ?? null;
        $defaultResponse['transaction_id']   = $rawResponseData['paymentId'] ?? null;
        $defaultResponse['ref_ret_num']      = $rawResponseData['hostReference'] ?? null;
        $defaultResponse['order_status']     = isset($rawResponseData['paymentStatus'])
            ? $this->valueMapper->mapOrderStatus($rawResponseData['paymentStatus'], $txType)
            : null;
        $defaultResponse['status']           = $status;

        if (self::TX_APPROVED === $status) {
            $defaultResponse['auth_code']         = $rawResponseData['authCode'] ?? null;
            $defaultResponse['currency']          = isset($rawResponseData['currency'])
                ? $this->valueMapper->mapCurrency($rawResponseData['currency'], $txType)
                : null;
            $defaultResponse['first_amount']      = isset($rawResponseData['price'])
                ? $this->valueFormatter->formatAmount($rawResponseData['price'], $txType)
                : null;
            $defaultResponse['capture_amount']    = isset($rawResponseData['paidPrice'])
                ? $this->valueFormatter->formatAmount($rawResponseData['paidPrice'], $txType)
                : null;
            $defaultResponse['masked_number']     = $this->formatMaskedNumber($rawResponseData);
            $defaultResponse['installment_count'] = isset($rawResponseData['installment'])
                ? $this->valueFormatter->formatInstallment($rawResponseData['installment'], $txType)
                : null;
        } else {
            $defaultResponse['error_code']    = $rawResponseData['errorCode'] ?? null;
            $defaultResponse['error_message'] = $rawResponseData['errorMessage'] ?? null;
        }

        return $defaultResponse;
    }

    /**
     * @param array<string, mixed> $rawResponseData
     *
     * @return array<string, mixed>
     */
    public function mapOrderHistoryResponse(array $rawResponseData): array
    {
        $status = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === ($rawResponseData['status'] ?? null)) {
            $status = self::TX_APPROVED;
        }

        $transactions = [];
        if (self::TX_APPROVED === $status) {
            foreach ($rawResponseData['payments'] ?? [] as $rawPayment) {
                $transactions[] = $this->mapSingleOrderHistoryTransaction($rawPayment);
            }
        }

        return [
            'order_id'         => $rawResponseData['conversationId'] ?? null,
            'proc_return_code' => $rawResponseData['status'] ?? null,
            'error_code'       => self::TX_APPROVED === $status ? null : ($rawResponseData['errorCode'] ?? null),
            'error_message'    => self::TX_APPROVED === $status ? null : ($rawResponseData['errorMessage'] ?? null),
            'trans_count'      => \count($transactions),
            'transactions'     => $transactions,
            'status'           => $status,
            'all'              => $rawResponseData,
        ];
    }

    /**
     * @param array<string, mixed> $rawResponseData
     *
     * @return array<string, mixed>
     */
    public function mapHistoryResponse(array $rawResponseData): array
    {
        $status = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === ($rawResponseData['status'] ?? null)) {
            $status = self::TX_APPROVED;
        }

        $transactions = [];
        if (self::TX_APPROVED === $status) {
            foreach ($rawResponseData['transactions'] ?? [] as $rawTx) {
                $transactions[] = $this->mapSingleHistoryTransaction($rawTx);
            }
        }

        return [
            'proc_return_code' => $rawResponseData['status'] ?? null,
            'error_code'       => self::TX_APPROVED === $status ? null : ($rawResponseData['errorCode'] ?? null),
            'error_message'    => self::TX_APPROVED === $status ? null : ($rawResponseData['errorMessage'] ?? null),
            'trans_count'      => \count($transactions),
            'transactions'     => $transactions,
            'current_page'     => $rawResponseData['currentPage'] ?? null,
            'total_pages'      => $rawResponseData['totalPageCount'] ?? null,
            'status'           => $status,
            'all'              => $rawResponseData,
        ];
    }

    /**
     * IyziCo'da mdStatus 0 ve 4 ile de başarılı ödeme yapılabilir.
     * 0 değer aslında fail sayılması gerekiyor...
     *
     * @inheritDoc
     */
    public function is3dAuthSuccess(?string $mdStatus): bool
    {
        return '1' === $mdStatus;
    }

    /**
     * @inheritDoc
     */
    public function extractMdStatus(array $raw3DAuthResponseData): ?string
    {
        return isset($raw3DAuthResponseData['mdStatus']) ? (string) $raw3DAuthResponseData['mdStatus'] : null;
    }

    /**
     * @param string $mdStatus
     *
     * @return string
     */
    protected function mapResponseTransactionSecurity(string $mdStatus): string
    {
        // @codeCoverageIgnoreStart
        return '';
        // @codeCoverageIgnoreEnd
    }

    /**
     * Combines binNumber and lastFourDigits into a masked card number string.
     *
     * @param array<string, mixed> $responseData
     */
    private function formatMaskedNumber(array $responseData): ?string
    {
        $bin      = $responseData['binNumber'] ?? null;
        $lastFour = $responseData['lastFourDigits'] ?? null;
        if (null === $bin || null === $lastFour) {
            return null;
        }

        return $bin.'******'.$lastFour;
    }

    /**
     * @param array<string, mixed> $rawPaymentResponseData
     *
     * @phpstan-param PosInterface::TX_TYPE_PAY_* $txType
     * @phpstan-param PosInterface::MODEL_*       $paymentModel
     *
     * @return array<string, mixed>
     */
    private function mapPaymentCommonPaymentResponse(array $rawPaymentResponseData, string $txType, string $paymentModel): array
    {
        $defaultResponse = $this->getDefaultPaymentResponse($txType, $paymentModel);
        if ([] === $rawPaymentResponseData) {
            return $defaultResponse;
        }

        $status = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === ($rawPaymentResponseData['status'] ?? null)) {
            $status = self::TX_APPROVED;
        }

        $mappedResponse = [
            'order_id'         => $rawPaymentResponseData['conversationId'],
            'transaction_id'   => $rawPaymentResponseData['paymentId'] ?? null,
            'proc_return_code' => $rawPaymentResponseData['status'],
            'transaction_time' => $this->valueFormatter->formatDateTime((string) $rawPaymentResponseData['systemTime'], $txType),
            'status'           => $status,
            'error_code'       => self::TX_APPROVED === $status ? null : ($rawPaymentResponseData['errorCode'] ?? null),
            'error_message'    => self::TX_APPROVED === $status ? null : ($rawPaymentResponseData['errorMessage'] ?? null),
            'all'              => $rawPaymentResponseData,
        ];

        if (self::TX_APPROVED === $status) {
            $mappedResponse['transaction_type']  = $this->valueMapper->mapTxType($rawPaymentResponseData['phase']);
            $mappedResponse['ref_ret_num']       = $rawPaymentResponseData['hostReference'];
            $mappedResponse['auth_code']         = $rawPaymentResponseData['authCode'];
            $mappedResponse['installment_count'] = $this->valueFormatter->formatInstallment($rawPaymentResponseData['installment'], $txType);
            $mappedResponse['currency']          = $this->valueMapper->mapCurrency($rawPaymentResponseData['currency'], $txType);
            $mappedResponse['amount']            = $this->valueFormatter->formatAmount($rawPaymentResponseData['paidPrice'], $txType);
            $mappedResponse['masked_number']     = $this->formatMaskedNumber($rawPaymentResponseData);
        }

        return $this->mergeArraysPreferNonNullValues($defaultResponse, $mappedResponse);
    }

    /**
     * @param array<string, mixed> $rawPayment
     *
     * @return array<string, mixed>
     */
    private function mapSingleOrderHistoryTransaction(array $rawPayment): array
    {
        $txType        = PosInterface::TX_TYPE_ORDER_HISTORY;
        $paymentStatus = $rawPayment['paymentStatus'] ?? null;
        $txStatus      = 1 === $paymentStatus ? self::TX_APPROVED : self::TX_DECLINED;

        $transaction                     = $this->getDefaultOrderHistoryTxResponse();
        $transaction['transaction_id']   = $rawPayment['paymentId'] ?? null;
        $transaction['auth_code']        = $rawPayment['authCode'] ?? null;
        $transaction['ref_ret_num']      = $rawPayment['hostReference'] ?? null;
        $transaction['payment_model']    = $this->valueMapper->mapSecureType($rawPayment['threeDS'], $txType);
        $transaction['status']           = $txStatus;
        $transaction['transaction_type'] = $this->valueMapper->mapTxType($rawPayment['phase']);
        ;
        $transaction['first_amount']      = isset($rawPayment['price'])
            ? $this->valueFormatter->formatAmount($rawPayment['price'], $txType)
            : null;
        $transaction['capture_amount']    = isset($rawPayment['paidPrice'])
            ? $this->valueFormatter->formatAmount($rawPayment['paidPrice'], $txType)
            : null;
        $transaction['currency']          = isset($rawPayment['currency'])
            ? $this->valueMapper->mapCurrency($rawPayment['currency'], $txType)
            : null;
        $transaction['masked_number']     = $this->formatMaskedNumber($rawPayment);
        $transaction['installment_count'] = isset($rawPayment['installment'])
            ? $this->valueFormatter->formatInstallment((string) $rawPayment['installment'], $txType)
            : null;
        $transaction['transaction_time']  = isset($rawPayment['createdDate'])
            ? $this->valueFormatter->formatDateTime((string) $rawPayment['createdDate'], $txType)
            : null;
        $transaction['capture']           = 1 === $paymentStatus;

        $paymentRefundStatus = isset($rawPayment['paymentRefundStatus'])
            ? $this->valueMapper->mapOrderStatus($rawPayment['paymentRefundStatus'], $txType) : null;

        if (null === $paymentRefundStatus) {
            if (1 === $paymentStatus) {
                $transaction['order_status'] = PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED;
            } else {
                $transaction['order_status'] = PosInterface::PAYMENT_STATUS_ERROR;
            }
        } else {
            $transaction['order_status'] = $paymentRefundStatus;
        }

        if (isset($rawPayment['cancels']) && count($rawPayment['cancels']) > 0) {
            $transaction['cancel_time'] = $this->valueFormatter->formatDateTime((string) $rawPayment['cancels'][0]['createdDate'], $txType);
        }

        return $transaction;
    }

    /**
     * @param array<string, mixed> $rawTx
     *
     * @return array<string, mixed>
     */
    private function mapSingleHistoryTransaction(array $rawTx): array
    {
        $txType            = PosInterface::TX_TYPE_HISTORY;
        $transactionStatus = $rawTx['transactionStatus'] ?? null;

        $transactionType   = $rawTx['transactionType'] ?? null;

        $transaction                   = $this->getDefaultOrderHistoryTxResponse();
        $transaction['transaction_type']  = null !== $transactionType
            ? $this->valueMapper->mapTxType($transactionType)
            : null;

        if ($transaction['transaction_type'] === PosInterface::TX_TYPE_PAY_AUTH) {
            $txStatus          = \in_array($transactionStatus, [1, 2], true) ? self::TX_APPROVED : self::TX_DECLINED;
            $transaction['capture']           = self::TX_APPROVED === $txStatus;
            $transaction['capture_amount']    = isset($rawTx['paidPrice'])
                ? $this->valueFormatter->formatAmount($rawTx['paidPrice'], $txType)
                : null;
        } else {
            $txStatus = self::TX_APPROVED;
        }

        $transaction['transaction_id'] = $rawTx['transactionId'] ?? null;
        $transaction['auth_code']      = $rawTx['authCode'] ?? null;
        $transaction['ref_ret_num']    = $rawTx['hostReference'] ?? null;
        $transaction['payment_model']  = $this->valueMapper->mapSecureType($rawTx['threeDS'], $txType);

        $transaction['status']            = $txStatus;

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

        if (self::TX_DECLINED === $txStatus) {
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
