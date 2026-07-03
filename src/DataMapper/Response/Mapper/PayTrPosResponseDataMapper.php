<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\Response\Mapper;

use Mews\Pos\Exception\NotImplementedException;
use Mews\Pos\Gateway\PayTrPos;
use Mews\Pos\PosInterface;

/**
 * Maps PayTR API responses to the unified response format.
 *
 * @link https://dev.paytr.com/
 *
 * @internal
 */
class PayTrPosResponseDataMapper extends AbstractResponseDataMapper
{
    /** @var string */
    public const PROCEDURE_SUCCESS_CODE = 'success';

    /** @inheritDoc */
    public static function supports(string $gatewayClass): bool
    {
        return PayTrPos::class === $gatewayClass;
    }

    /**
     * {@inheritDoc}
     *
     * Maps the synchronous Direct API response (NonSecure with sync_mode=1).
     */
    public function mapPaymentResponse(array $rawPaymentResponseData, string $txType, array $order): array
    {
        $this->logger->debug('mapping payment response', [$rawPaymentResponseData]);
        $defaultResponse = $this->getDefaultPaymentResponse($txType, PosInterface::MODEL_NON_SECURE);

        if ([] === $rawPaymentResponseData) {
            return $defaultResponse;
        }

        $procReturnCode = $this->getProcReturnCode($rawPaymentResponseData);
        $status = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        $currency = isset($rawPaymentResponseData['currency'])
            ? $this->valueMapper->mapCurrency($rawPaymentResponseData['currency'], $txType)
            : null;

        $mappedResponse = [
            'order_id'          => $rawPaymentResponseData['merchant_oid'] ?? $order['id'],
            'currency'          => $currency ?? $order['currency'] ?? null,
            'amount'            => self::TX_APPROVED === $status
                ? $this->valueFormatter->formatAmount($rawPaymentResponseData['total_amount'] ?? $order['amount'], $txType)
                : null,
            'installment_count' => isset($rawPaymentResponseData['installment_count'])
                ? $this->valueFormatter->formatInstallment($rawPaymentResponseData['installment_count'], $txType)
                : null,
            'transaction_time'  => self::TX_APPROVED === $status ? new \DateTimeImmutable() : null,
            'status'            => $status,
            'error_code'        => self::TX_APPROVED === $status ? null : ($rawPaymentResponseData['failed_reason_code'] ?? null),
            'error_message'     => self::TX_APPROVED === $status ? null : ($rawPaymentResponseData['failed_reason_msg'] ?? null),
            'all'               => $rawPaymentResponseData,
        ];

        $this->logger->debug('mapped payment response', $mappedResponse);

        return $this->mergeArraysPreferNonNullValues($defaultResponse, $mappedResponse);
    }

    /**
     * {@inheritDoc}
     *
     * Not used — PayTR 3DPay is callback-based; use map3DPayResponseData instead.
     */
    public function map3DPaymentData(array $raw3DAuthResponseData, ?array $rawPaymentResponseData, string $txType, array $order): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritDoc}
     *
     * Maps PayTR callback notification POST data for MODEL_3D_PAY.
     */
    public function map3DPayResponseData(array $raw3DAuthResponseData, string $txType, array $order): array
    {
        return $this->mapCallbackResponse($raw3DAuthResponseData, $txType, PosInterface::MODEL_3D_PAY);
    }

    /**
     * {@inheritDoc}
     *
     * Maps PayTR callback notification POST data for MODEL_3D_HOST (iFrame).
     * Same callback format as 3DPay.
     */
    public function map3DHostResponseData(array $raw3DAuthResponseData, string $txType, array $order): array
    {
        return $this->mapCallbackResponse($raw3DAuthResponseData, $txType, PosInterface::MODEL_3D_HOST);
    }

    /**
     * {@inheritDoc}
     *
     * Maps the PayTR refund API response.
     */
    public function mapRefundResponse(array $rawResponseData): array
    {
        $procReturnCode = $this->getProcReturnCode($rawResponseData);
        $status         = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        $refundAmount = isset($rawResponseData['return_amount']) ?
            $this->valueFormatter->formatAmount($rawResponseData['return_amount'], PosInterface::TX_TYPE_REFUND) : null;

        return [
            'order_id'      => $rawResponseData['merchant_oid'] ?? null,
            'status'        => $status,
            'refund_amount' => $refundAmount,
            'ref_ret_num'   => $rawResponseData['reference_no'] ?? null,
            'error_code'    => self::TX_APPROVED === $status ? null : $rawResponseData['err_no'],
            'error_message' => self::TX_APPROVED === $status ? null : $rawResponseData['err_msg'],
            'all'           => $rawResponseData,
        ];
    }

    /** {@inheritDoc} */
    public function mapCancelResponse(array $rawResponseData): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritDoc}
     */
    public function mapStatusResponse(array $rawResponseData): array
    {
        $txType = PosInterface::TX_TYPE_STATUS;
        $procReturnCode  = $this->getProcReturnCode($rawResponseData);
        $defaultResponse = $this->getDefaultStatusResponse($rawResponseData);
        $status          = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        if (self::TX_APPROVED !== $status) {
            $defaultResponse['error_code']    = $rawResponseData['err_no'];
            $defaultResponse['error_message'] = $rawResponseData['err_msg'];

            return $defaultResponse;
        }

        $paymentAmount = isset($rawResponseData['payment_amount'])
            ? $this->valueFormatter->formatAmount($rawResponseData['payment_amount'], $txType)
            : null;

        $paymentTotal = isset($rawResponseData['payment_total'])
            ? $this->valueFormatter->formatAmount($rawResponseData['payment_total'], $txType)
            : null;

        $currency = isset($rawResponseData['currency'])
            ? $this->valueMapper->mapCurrency($rawResponseData['currency'], $txType)
            : null;

        $txType          = PosInterface::TX_TYPE_PAY_AUTH;
        $transactionTime = isset($rawResponseData['payment_date'])
            ? $this->valueFormatter->formatDateTime($rawResponseData['payment_date'], $txType)
            : null;

        $installmentCount = isset($rawResponseData['taksit'])
            ? $this->valueFormatter->formatInstallment($rawResponseData['taksit'], $txType)
            : null;

        $returns      = $rawResponseData['returns'] ?? [];
        $refundAmount = null;
        $refundTime   = null;
        if ([] !== $returns) {
            $refundAmount = 0.0;
            foreach ($returns as $return) {
                $txRefundAmount = isset($return['refund_amount']) ?
                    $this->valueFormatter->formatAmount($return['refund_amount'], $txType) : 0.0;
                $refundAmount += $txRefundAmount;
                if (isset($return['return_date'])) {
                    $refundTime = $this->valueFormatter->formatDateTime($return['return_date'], $txType);
                }
            }
        }

        if ($refundAmount !== null && $refundAmount > 0 && $paymentTotal !== null) {
            $orderStatus = $refundAmount >= $paymentTotal
                ? PosInterface::PAYMENT_STATUS_FULLY_REFUNDED
                : PosInterface::PAYMENT_STATUS_PARTIALLY_REFUNDED;
        } else {
            $orderStatus = PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED;
        }

        $mappedResponse = [
            'proc_return_code'  => $procReturnCode,
            'order_status'      => $orderStatus,
            'status'            => $status,
            'transaction_time'  => $transactionTime,
            'capture_time'      => $transactionTime,
            'first_amount'      => $paymentAmount,
            'capture_amount'    => $paymentTotal,
            'capture'           => $paymentTotal !== null && $paymentTotal > 0,
            'currency'          => $currency,
            'masked_number'     => $rawResponseData['masked_pan'] ?? null,
            'installment_count' => $installmentCount,
            'refund_amount'     => $refundAmount,
            'refund_time'       => $refundTime,
            'all'               => $rawResponseData,
        ];

        return $this->mergeArraysPreferNonNullValues($defaultResponse, $mappedResponse);
    }

    /** {@inheritDoc} */
    public function mapOrderHistoryResponse(array $rawResponseData): array
    {
        throw new NotImplementedException();
    }

    /**
     * PayTR does not send an mdStatus field; 3D auth success is determined by status="success".
     *
     * @inheritDoc
     */
    public function is3dAuthSuccess(?string $mdStatus): bool
    {
        return 'success' === $mdStatus;
    }

    /** @inheritDoc */
    public function extractMdStatus(array $raw3DAuthResponseData): ?string
    {
        return $raw3DAuthResponseData['status'] ?? null;
    }


    /**
     * Get ProcReturnCode
     *
     * @param array<string, string> $response
     *
     * @return string|null success|failed|wait_callback
     */
    protected function getProcReturnCode(array $response): ?string
    {
        return $response['status'] ?? null;
    }

    /**
     * Shared mapping logic for both 3DPay and 3DHost callback responses.
     *
     * @phpstan-param PosInterface::MODEL_3D_PAY|PosInterface::MODEL_3D_HOST $paymentModel
     * @phpstan-param PosInterface::TX_TYPE_PAY_*                             $txType
     *
     * @param array<string, mixed> $callbackData
     *
     * @return array<string, mixed>
     */
    private function mapCallbackResponse(array $callbackData, string $txType, string $paymentModel): array
    {
        $this->logger->debug('mapping 3D callback response', [$callbackData]);
        $defaultResponse = $this->getDefaultPaymentResponse($txType, $paymentModel);

        $defaultResponse['md_status'] = null;
        $defaultResponse['md_error_message'] = null;
        $defaultResponse['transaction_security'] = null;
        if ([] === $callbackData) {
            return $defaultResponse;
        }

        if (1 === count($callbackData)) {
            $defaultResponse['md_error_message'] = $callbackData['fail_message'];

            return $defaultResponse;
        }

        /**
         * processing response from callback (Bildirim URL):
         */
        $procReturnCode = $this->getProcReturnCode($callbackData);
        $status         = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        $mappedResponse = [
            'order_id'             => $callbackData['merchant_oid'] ?? null,
            'payment_model'        => $paymentModel,
            'status'               => $status,
            'all'                  => $callbackData,
        ];
        if (self::TX_APPROVED === $status) {
            $mappedResponse['amount'] = $this->valueFormatter->formatAmount($callbackData['total_amount'] ?? 0, $txType);
            $mappedResponse['installment_count'] = $this->valueFormatter->formatInstallment($callbackData['installment_count'], $txType);
            $mappedResponse['currency'] = $this->valueMapper->mapCurrency($callbackData['currency'], $txType);
            $mappedResponse['transaction_time'] = new \DateTimeImmutable();
        } else {
            $mappedResponse['error_code'] = $callbackData['failed_reason_code'];
            $mappedResponse['error_message'] = $callbackData['failed_reason_msg'];
        }

        $this->logger->debug('mapped 3D callback response', $mappedResponse);

        return $this->mergeArraysPreferNonNullValues($defaultResponse, $mappedResponse);
    }
}
