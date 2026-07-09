<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\PosQuery\Response;

use Mews\Pos\DataMapper\Response\ValueMapper\GarantiPosResponseValueMapper;
use Mews\Pos\DataMapper\Response\ValueMapper\ResponseValueMapperInterface;
use Mews\Pos\Gateway\GarantiPos;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\PosInterface;
use Mews\Pos\PosQuery\PosQueryInterface;

/**
 * @internal
 */
class GarantiPosQueryResponseDataMapper extends AbstractQueryResponseDataMapper
{
    /** @var GarantiPosResponseValueMapper */
    protected ResponseValueMapperInterface $valueMapper;

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return GarantiPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    public function mapHistoryResponse(array $rawResponseData): array
    {
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $procReturnCode  = $this->getProcReturnCode($rawResponseData);
        $status          = self::TX_STATUS_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_STATUS_APPROVED;
        }

        $mappedTransactions = [];
        if (self::TX_STATUS_APPROVED === $status) {
            $rawTransactions = $rawResponseData['Order']['OrderListInqResult']['OrderTxnList']['OrderTxn'];
            if (\count($rawTransactions) !== \count($rawTransactions, COUNT_RECURSIVE)) {
                foreach ($rawTransactions as $transaction) {
                    $mappedTransactions[] = $this->mapSingleHistoryTransaction($transaction);
                }
            } else {
                $mappedTransactions[] = $this->mapSingleHistoryTransaction($rawTransactions);
            }
        }

        return [
            'proc_return_code' => $procReturnCode,
            'error_code'       => self::TX_STATUS_DECLINED === $status ? $procReturnCode : null,
            'error_message'    => self::TX_STATUS_DECLINED === $status ? ($rawResponseData['Transaction']['Response']['ErrorMsg'] ?? null) : null,
            'status'           => $status,
            'trans_count'      => \count($mappedTransactions),
            'transactions'     => $mappedTransactions,
            'all'              => $rawResponseData,
        ];
    }

    /**
     * @inheritDoc
     */
    public function mapBinListResponse(array $rawResponseData): array
    {
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $procReturnCode  = $this->getProcReturnCode($rawResponseData);
        $status          = self::TX_STATUS_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_STATUS_APPROVED;
        }

        $result                  = $this->getDefaultBinListResponse();
        $result['status']        = $status;
        $result['error_message'] = self::TX_STATUS_APPROVED === $status
            ? null
            : ($rawResponseData['Transaction']['Response']['ErrorMsg'] ?? null);
        $result['all']           = $rawResponseData;

        if (self::TX_STATUS_DECLINED === $status) {
            return $result;
        }

        $rawBins = $rawResponseData['BINInqResult']['BINList']['BIN'] ?? [];

        // When the bank returns a single BIN it comes as an associative array, not a list.
        if (\count($rawBins) !== \count($rawBins, COUNT_RECURSIVE)) {
            foreach ($rawBins as $rawBin) {
                $result['bin_list'][] = $this->mapSingleBin($rawBin);
            }
        } elseif ([] !== $rawBins) {
            $result['bin_list'][] = $this->mapSingleBin($rawBins);
        }

        return $result;
    }

    /**
     * @param array<string, string|null> $rawBin
     *
     * @return array{bin: string, bank_code: string|null, bank_name: string|null, card_type: CreditCardInterface::CARD_TYPE_*|null, card_class: CreditCardInterface::CARD_CLASS_*|null, card_family: CreditCardInterface::CARD_FAMILY_*|string|null}
     */
    private function mapSingleBin(array $rawBin): array
    {
        return [
            'bin'         => (string) ($rawBin['BINNum'] ?? ''),
            'bank_code'   => $rawBin['BankCode'] ?? null,
            'bank_name'   => $rawBin['BankName'] ?? null,
            'card_type'   => $this->valueMapper->mapCardType($rawBin['Organization'] ?? null),
            'card_class'  => $this->valueMapper->mapCardClass($rawBin['CardType'] ?? null),
            'card_family' => null,
        ];
    }

    /**
     * @param array<string, mixed> $response
     */
    private function getProcReturnCode(array $response): ?string
    {
        return $response['Transaction']['Response']['Code'] ?? null;
    }

    /**
     * @param array<string, string|null> $rawTx
     *
     * @return array<string, int|string|null|float|bool|\DateTimeImmutable>
     */
    private function mapSingleHistoryTransaction(array $rawTx): array
    {
        $txType         = PosQueryInterface::QUERY_TYPE_HISTORY;
        $procReturnCode = $rawTx['ResponseCode'];
        $status         = self::TX_STATUS_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_STATUS_APPROVED;
        }

        $defaultResponse                     = $this->getDefaultHistoryTxResponse();
        $defaultResponse['auth_code']        = $rawTx['AuthCode'] ?? null;
        $defaultResponse['ref_ret_num']      = $rawTx['RetrefNum'] ?? null;
        $defaultResponse['order_id']         = $rawTx['OrderID'];
        $defaultResponse['batch_num']        = $rawTx['BatchNum'];
        $defaultResponse['proc_return_code'] = $procReturnCode;
        $defaultResponse['transaction_type'] = null !== $rawTx['TrxType'] ? $this->valueMapper->mapTxType($rawTx['TrxType']) : null;
        $defaultResponse['order_status']     = null !== $rawTx['Status'] ? $this->valueMapper->mapOrderStatus($rawTx['Status'], $txType, $defaultResponse['transaction_type']) : null;
        $defaultResponse['status']           = $status;
        $defaultResponse['error_code']       = self::TX_STATUS_APPROVED === $status ? null : $procReturnCode;
        $defaultResponse['error_message']    = self::TX_STATUS_APPROVED === $status ? null : $rawTx['SysErrMsg'];
        $defaultResponse['payment_model']    = $this->valueMapper->mapSecureType($rawTx['SafeType'] ?? '', $txType);
        $defaultResponse['transaction_time'] = null !== $rawTx['LastTrxDate'] ? $this->valueFormatter->formatDateTime($rawTx['LastTrxDate'], $txType) : null;

        if (self::TX_STATUS_APPROVED === $status) {
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
}
