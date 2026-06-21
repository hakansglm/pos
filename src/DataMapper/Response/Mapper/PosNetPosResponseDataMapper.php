<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\Response\Mapper;

use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Gateways\PosNetPos;
use Mews\Pos\PosInterface;

class PosNetPosResponseDataMapper extends AbstractResponseDataMapper
{
    /** @var string */
    public const PROCEDURE_SUCCESS_CODE = '1';

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return PosNetPos::class === $gatewayClass;
    }

    /**
     * {@inheritDoc}
     */
    public function mapPaymentResponse(array $rawPaymentResponseData, string $txType, array $order): array
    {
        $status = self::TX_DECLINED;
        $this->logger->debug('mapping payment response', [$rawPaymentResponseData]);
        $defaultResponse = $this->getDefaultPaymentResponse($txType, PosInterface::MODEL_NON_SECURE);
        if ([] === $rawPaymentResponseData) {
            return $defaultResponse;
        }

        $rawPaymentResponseData = $this->emptyStringsToNull($rawPaymentResponseData);
        $errorCode              = $rawPaymentResponseData['respCode'] ?? null;
        $procReturnCode         = $this->getProcReturnCode($rawPaymentResponseData);
        if (
            self::PROCEDURE_SUCCESS_CODE === $procReturnCode
            && !$errorCode
        ) {
            $status = self::TX_APPROVED;
        }

        $defaultResponse['order_id']         = $order['id'];
        $defaultResponse['currency']         = $order['currency'];
        $defaultResponse['amount']           = $order['amount'];
        $defaultResponse['auth_code']        = $rawPaymentResponseData['authCode'] ?? null;
        $defaultResponse['ref_ret_num']      = $rawPaymentResponseData['hostlogkey'] ?? null;
        $defaultResponse['proc_return_code'] = $procReturnCode;
        $defaultResponse['status']           = $status;
        $defaultResponse['error_code']       = $errorCode;
        $defaultResponse['error_message']    = $rawPaymentResponseData['respText'] ?? null;
        $defaultResponse['all']              = $rawPaymentResponseData;

        if (self::TX_APPROVED === $status) {
            $defaultResponse['installment_count'] = $this->valueFormatter->formatInstallment($rawPaymentResponseData['instInfo']['inst1'], $txType);
            $defaultResponse['transaction_time']  = $this->valueFormatter->formatDateTime('now', $txType);
        }

        return $defaultResponse;
    }

    /**
     * {@inheritdoc}
     */
    public function map3DPaymentData(array $raw3DAuthResponseData, ?array $rawPaymentResponseData, string $txType, array $order): array
    {
        $this->logger->debug('mapping 3D payment data', [
            '3d_auth_response'   => $raw3DAuthResponseData,
            'provision_response' => $rawPaymentResponseData,
        ]);
        $raw3DAuthResponseData = $this->emptyStringsToNull($raw3DAuthResponseData);
        $status                = self::TX_DECLINED;
        $procReturnCode        = $this->getProcReturnCode($raw3DAuthResponseData);
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        $defaultResponse = $this->getDefaultPaymentResponse($txType, PosInterface::MODEL_3D_SECURE);

        if (!isset($raw3DAuthResponseData['oosResolveMerchantDataResponse'])) {
            $defaultResponse['proc_return_code'] = $procReturnCode;
            $defaultResponse['error_code']       = $raw3DAuthResponseData['respCode'];
            $defaultResponse['error_message']    = $raw3DAuthResponseData['respText'];
            $defaultResponse['3d_all']           = $raw3DAuthResponseData;

            return $defaultResponse;
        }

        /** @var array<string, string|null> $oosResolveMerchantDataResponse */
        $oosResolveMerchantDataResponse = $raw3DAuthResponseData['oosResolveMerchantDataResponse'];

        $mdStatus            = $this->extractMdStatus($raw3DAuthResponseData);
        $transactionSecurity = null;
        if (null === $mdStatus) {
            $this->logger->error('mdStatus boş döndü. Sağlanan banka API bilgileri eksik/yanlış olabilir.');
        } else {
            $transactionSecurity = $this->mapResponseTransactionSecurity($mdStatus);
        }

        $threeDResponse = [
            'order_id'             => $order['id'],
            'remote_order_id'      => $oosResolveMerchantDataResponse['xid'] ?? null,
            'transaction_security' => $transactionSecurity,
            'amount'               => $this->valueFormatter->formatAmount((string) $oosResolveMerchantDataResponse['amount'], $txType),
            'currency'             => $this->valueMapper->mapCurrency((string) $oosResolveMerchantDataResponse['currency'], $txType),
            'proc_return_code'     => $procReturnCode,
            'status'               => $status,
            'md_status'            => $mdStatus,
            'md_error_message'     => $oosResolveMerchantDataResponse['mdErrorMessage'] ?? null,
            '3d_all'               => $raw3DAuthResponseData,
        ];
        if (null === $rawPaymentResponseData) {
            $paymentResponseData = $defaultResponse;
        } else {
            $paymentResponseData = $this->map3dPaymentResponseCommon(
                $rawPaymentResponseData,
                $txType,
                PosInterface::MODEL_3D_SECURE
            );
        }

        return $this->mergeArraysPreferNonNullValues($threeDResponse, $paymentResponseData);
    }

    /**
     * {@inheritdoc}
     */
    public function map3DPayResponseData(array $raw3DAuthResponseData, string $txType, array $order): array
    {
        throw new NotImplementedException();
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
     * {@inheritdoc}
     */
    public function mapCancelResponse($rawResponseData): array
    {
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $status          = self::TX_DECLINED;
        $errorCode       = $rawResponseData['respCode'] ?? null;
        $procReturnCode  = $this->getProcReturnCode($rawResponseData);
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode && $rawResponseData && !$errorCode) {
            $status = self::TX_APPROVED;
        }

        $state           = $rawResponseData['state'] ?? null;
        $transactionType = null;
        if (null !== $state) {
            $transactionType = $this->valueMapper->mapTxType($state);
        }

        $results = [
            'auth_code'        => null,
            'transaction_id'   => null,
            'ref_ret_num'      => null,
            'group_id'         => null,
            'date'             => null,
            'transaction_type' => $transactionType,
            'proc_return_code' => $procReturnCode,
            'status'           => $status,
            'error_code'       => $errorCode,
            'error_message'    => $rawResponseData['respText'] ?? null,
            'all'              => $rawResponseData,
        ];

        /** @var array<string, string>|null $transactionDetails */
        $transactionDetails = $rawResponseData['transaction'] ?? null;
        $txResults          = [];
        if (null !== $transactionDetails) {
            $txResults = [
                'auth_code'      => $transactionDetails['authCode'] ?? null,
                'transaction_id' => null,
                'ref_ret_num'    => $transactionDetails['hostlogkey'] ?? null,
                'date'           => $transactionDetails['tranDate'] ?? null,
            ];
        }

        return array_merge($results, $txResults);
    }

    /**
     * {@inheritdoc}
     */
    public function mapStatusResponse(array $rawResponseData): array
    {
        $txType          = PosInterface::TX_TYPE_STATUS;
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $status          = self::TX_DECLINED;
        $errorCode       = $rawResponseData['respCode'] ?? null;
        $procReturnCode  = $this->getProcReturnCode($rawResponseData);

        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode && isset($rawResponseData['transactions']) && !$errorCode) {
            $status = self::TX_APPROVED;
        }

        $txResults = [];

        $defaultResponse = $this->getDefaultStatusResponse($rawResponseData);

        if (isset($rawResponseData['transactions']['transaction'])) {
            $transactionDetails = $rawResponseData['transactions']['transaction'];

            $txResults = [
                'currency'         => $this->valueMapper->mapCurrency($transactionDetails['currencyCode'], $txType),
                'first_amount'     => $this->valueFormatter->formatAmount($transactionDetails['amount'], $txType),
                'transaction_type' => null === $transactionDetails['state'] ? null : $this->valueMapper->mapTxType($transactionDetails['state']),
                'order_id'         => $transactionDetails['orderID'],
                'auth_code'        => $transactionDetails['authCode'] ?? null,
                'ref_ret_num'      => $transactionDetails['hostlogkey'] ?? null,
                // tranDate ex: 2019-10-10 11:21:14.281
                'transaction_time' => isset($transactionDetails['tranDate']) ? $this->valueFormatter->formatDateTime($transactionDetails['tranDate'], $txType) : null,
            ];
        }

        $defaultResponse['proc_return_code'] = $procReturnCode;
        $defaultResponse['status']           = $status;
        $defaultResponse['error_code']       = self::TX_APPROVED !== $status ? $errorCode : null;
        $defaultResponse['error_message']    = self::TX_APPROVED !== $status ? ($rawResponseData['respText'] ?? null) : null;

        return $this->mergeArraysPreferNonNullValues($defaultResponse, $txResults);
    }

    /**
     * {@inheritDoc}
     */
    public function mapOrderHistoryResponse(array $rawResponseData): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritDoc}
     */
    public function mapHistoryResponse(array $rawResponseData): array
    {
        throw new NotImplementedException();
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
        return $raw3DAuthResponseData['oosResolveMerchantDataResponse']['mdStatus'] ?? null;
    }

    /**
     * @param string $mdStatus
     *
     * @return string
     */
    protected function mapResponseTransactionSecurity(string $mdStatus): string
    {
        $transactionSecurity = 'MPI fallback';
        if ('1' === $mdStatus) {
            $transactionSecurity = 'Full 3D Secure';
        } elseif (\in_array($mdStatus, ['2', '3', '4'], true)) {
            $transactionSecurity = 'Half 3D Secure';
        }

        return $transactionSecurity;
    }


    /**
     * Get ProcReturnCode
     *
     * @param array<string, string> $response
     *
     * @return string|null
     */
    protected function getProcReturnCode(array $response): ?string
    {
        return $response['approved'] ?? null;
    }

    /**
     * @phpstan-param PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $txType
     * @phpstan-param PosInterface::MODEL_3D_*                                          $paymentModel
     *
     * @param array<string, mixed> $rawPaymentResponseData
     * @param string               $txType
     * @param string               $paymentModel
     *
     * @return array<string, mixed>
     */
    private function map3dPaymentResponseCommon(array $rawPaymentResponseData, string $txType, string $paymentModel): array
    {
        $status = self::TX_DECLINED;
        $this->logger->debug('mapping payment response', [$rawPaymentResponseData]);
        $defaultResponse = $this->getDefaultPaymentResponse($txType, $paymentModel);
        if ([] === $rawPaymentResponseData) {
            return $defaultResponse;
        }

        $rawPaymentResponseData = $this->emptyStringsToNull($rawPaymentResponseData);
        $errorCode              = $rawPaymentResponseData['respCode'] ?? null;
        $procReturnCode         = $this->getProcReturnCode($rawPaymentResponseData);
        if (
            self::PROCEDURE_SUCCESS_CODE === $procReturnCode
            && !$errorCode
        ) {
            $status = self::TX_APPROVED;
        }

        $defaultResponse['auth_code']        = $rawPaymentResponseData['authCode'] ?? null;
        $defaultResponse['ref_ret_num']      = $rawPaymentResponseData['hostlogkey'] ?? null;
        $defaultResponse['proc_return_code'] = $procReturnCode;
        $defaultResponse['status']           = $status;
        $defaultResponse['error_code']       = $errorCode;
        $defaultResponse['error_message']    = $rawPaymentResponseData['respText'] ?? null;
        $defaultResponse['all']              = $rawPaymentResponseData;
        if (self::TX_APPROVED === $status) {
            $defaultResponse['installment_count'] = $this->valueFormatter->formatInstallment($rawPaymentResponseData['instInfo']['inst1'], $txType);
            $defaultResponse['transaction_time']  = $this->valueFormatter->formatDateTime('now', $txType);
        }

        return $defaultResponse;
    }
}
