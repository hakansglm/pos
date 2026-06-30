<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\Request\Mapper;

use InvalidArgumentException;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\Crypt\PosNetPosCrypt;
use Mews\Pos\DataMapper\Request\ValueFormatter\PosNetPosRequestValueFormatter;
use Mews\Pos\DataMapper\Request\ValueFormatter\RequestValueFormatterInterface;
use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Model\Account\PosNetPosAccount;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\Exception\NotImplementedException;
use Mews\Pos\Exception\UnsupportedTransactionTypeException;
use Mews\Pos\Gateway\PosNetPos;
use Mews\Pos\PosInterface;

/**
 * Creates request data for PosNet Gateway requests
 *
 * @internal
 */
class PosNetPosRequestDataMapper extends AbstractRequestDataMapper
{
    /**
     * @var PosNetPosRequestValueFormatter
     */
    protected RequestValueFormatterInterface $valueFormatter;

    /** @var PosNetPosCrypt */
    protected CryptInterface $crypt;

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return PosNetPos::class === $gatewayClass;
    }

    /**
     * @param PosNetPosAccount                                                  $posAccount
     * @param PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $txType     kullanilmiyor
     *
     * {@inheritDoc}
     */
    public function create3DPaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, array $responseData): array
    {
        $order = $this->applyPaymentDefaults($order);

        $mappedOrder             = [];
        $mappedOrder['id']       = $this->valueFormatter->formatOrderId($order['id']);
        $mappedOrder['amount']   = $this->valueFormatter->formatAmount($order['amount']);
        $mappedOrder['currency'] = (string) $this->valueMapper->mapCurrency($order['currency']);

        $requestData = [
            'mid'         => $posAccount->getMerchantId(),
            'tid'         => $posAccount->getTerminalId(),
            'oosTranData' => [
                'bankData'     => $responseData['BankPacket'],
                'merchantData' => $responseData['MerchantPacket'],
                'sign'         => $responseData['Sign'],
                'wpAmount'     => 0,
            ],
        ];

        $requestData['oosTranData']['mac'] = $this->crypt->createHash($posAccount, $requestData, $mappedOrder);

        return $requestData;
    }

    /**
     * @param PosNetPosAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function createNonSecurePaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, CreditCardInterface $creditCard): array
    {
        $order = $this->applyPaymentDefaults($order);

        $txType = $this->valueMapper->mapTxType($txType);

        return [
            'mid'               => $posAccount->getMerchantId(),
            'tid'               => $posAccount->getTerminalId(),
            'tranDateRequired'  => '1',
            strtolower($txType) => [
                'orderID'      => $this->valueFormatter->formatOrderId($order['id']),
                'installment'  => $this->valueFormatter->formatInstallment($order['installment']),
                'amount'       => $this->valueFormatter->formatAmount($order['amount']),
                'currencyCode' => $this->valueMapper->mapCurrency($order['currency']),
                'ccno'         => $creditCard->getNumber(),
                'expDate'      => $this->valueFormatter->formatCardExpDate($creditCard->getExpirationDate(), 'expDate'),
                'cvc'          => $creditCard->getCvv(),
            ],
        ];
    }

    /**
     * @param PosNetPosAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        /** @var array<string, mixed> $order */
        $order = [
            'id'          => $order['id'],
            'amount'      => $order['amount'],
            'installment' => $order['installment'] ?? 0,
            'currency'    => $order['currency'] ?? PosInterface::CURRENCY_TRY,
            'ref_ret_num' => $order['ref_ret_num'],
        ];

        $txType = $this->valueMapper->mapTxType(PosInterface::TX_TYPE_PAY_POST_AUTH);

        return [
            'mid'                => $posAccount->getMerchantId(),
            'tid'                => $posAccount->getTerminalId(),
            'tranDateRequired'   => '1',
            \strtolower($txType) => [
                'hostLogKey'   => $order['ref_ret_num'],
                'amount'       => $this->valueFormatter->formatAmount($order['amount']),
                'currencyCode' => $this->valueMapper->mapCurrency($order['currency']),
                'installment'  => $this->valueFormatter->formatInstallment($order['installment']),
            ],
        ];
    }

    /**
     * @param PosNetPosAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function createStatusRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        /** @var array<string, mixed> $order */
        $order = [
            'id'            => $order['id'],
            'payment_model' => $order['payment_model'] ?? PosInterface::MODEL_3D_SECURE,
        ];

        $txType = $this->valueMapper->mapTxType(PosInterface::TX_TYPE_STATUS);

        return [
            'mid'   => $posAccount->getMerchantId(),
            'tid'   => $posAccount->getTerminalId(),
            $txType => [
                'orderID' => $this->valueFormatter->formatOrderId($order['id'], PosInterface::TX_TYPE_STATUS, $order['payment_model']),
            ],
        ];
    }

    /**
     * @param PosNetPosAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function createCancelRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $orderTemp = [
            'id'               => $order['id'] ?? null,
            'ref_ret_num'      => $order['ref_ret_num'] ?? null,
            'auth_code'        => $order['auth_code'] ?? null,
            'transaction_type' => $order['transaction_type'] ?? PosInterface::TX_TYPE_PAY_AUTH,
        ];

        if (null !== $orderTemp['id']) {
            $orderTemp['payment_model'] = $order['payment_model'] ?? PosInterface::MODEL_3D_SECURE;
        }

        /** @var array<string, mixed> $order */
        $order = $orderTemp;

        $txType     = $this->valueMapper->mapTxType(PosInterface::TX_TYPE_CANCEL);
        $txTypeData = [
            'transaction' => \strtolower($this->valueMapper->mapTxType($order['transaction_type'])),
        ];

        if (isset($order['auth_code'])) {
            $txTypeData['authCode'] = $order['auth_code'];
        }

        //either will work
        if (isset($order['ref_ret_num'])) {
            $txTypeData['hostLogKey'] = $order['ref_ret_num'];
        } else {
            $txTypeData['orderID'] = $this->valueFormatter->formatOrderId($order['id'], PosInterface::TX_TYPE_CANCEL, $order['payment_model']);
        }

        return [
            'mid'              => $posAccount->getMerchantId(),
            'tid'              => $posAccount->getTerminalId(),
            'tranDateRequired' => '1',
            $txType            => $txTypeData,
        ];
    }

    /**
     * @param PosNetPosAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function createRefundRequestData(AbstractPosAccount $posAccount, array $order, string $refundTxType): array
    {
        $orderTemp = [
            'id'          => $order['id'] ?? null,
            'ref_ret_num' => $order['ref_ret_num'] ?? null,
            'amount'      => $order['amount'],
            'currency'    => $order['currency'] ?? PosInterface::CURRENCY_TRY,
        ];

        if (null !== $orderTemp['id']) {
            $orderTemp['payment_model'] = $order['payment_model'] ?? PosInterface::MODEL_3D_SECURE;
        }

        /** @var array<string, mixed> $order */
        $order = $orderTemp;

        $txType     = $this->valueMapper->mapTxType($refundTxType);
        $txTypeData = [
            'amount'       => $this->valueFormatter->formatAmount($order['amount']),
            'currencyCode' => $this->valueMapper->mapCurrency($order['currency']),
        ];

        if (isset($order['ref_ret_num'])) {
            $txTypeData['hostLogKey'] = $order['ref_ret_num'];
        } else {
            $txTypeData['orderID'] = $this->valueFormatter->formatOrderId($order['id'], $refundTxType, $order['payment_model']);
        }

        return [
            'mid'              => $posAccount->getMerchantId(),
            'tid'              => $posAccount->getTerminalId(),
            'tranDateRequired' => '1',
            $txType            => $txTypeData,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function createHistoryRequestData(AbstractPosAccount $posAccount, array $data = []): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritDoc}
     */
    public function createOrderHistoryRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        throw new NotImplementedException();
    }


    /**
     * @param PosNetPosAccount                                  $posAccount
     * @param array{data1: string, data2: string, sign: string} $extraData
     *
     * @return array{gateway: string, method: 'POST', inputs: array<string, string>}
     *
     * {@inheritDoc}
     *
     * @throws InvalidArgumentException
     */
    public function create3DFormData(
        AbstractPosAccount   $posAccount,
        array                $order,
        string               $paymentModel,
        string               $txType,
        string               $gatewayURL,
        ?CreditCardInterface $creditCard = null,
        ?array               $extraData = null
    ): array {
        if (null === $extraData) {
            throw new InvalidArgumentException('$extraData can not be null');
        }

        $order = $this->applyPaymentDefaults($order);

        $inputs = [
            'mid'               => $posAccount->getMerchantId(),
            'posnetID'          => $posAccount->getPosNetId(),
            'posnetData'        => $extraData['data1'], //Ödeme bilgilerini içermektedir.
            'posnetData2'       => $extraData['data2'], //Kart bilgileri request içerisinde bulunuyorsa bu alan oluşturulmaktadır
            'digest'            => $extraData['sign'],  //Servis imzası.
            'merchantReturnURL' => $order['success_url'],
            /**
             * url - Yönlendirilen sayfanın adresi (URL – bilgi amaçlı)
             * YKB tarafından verilen Java Script fonksiyonu (posnet.js içerisindeki) tarafından
             * set edilir. Form içerisinde bulundurulması yeterlidir.
             */
            'url'               => '',
            'lang'              => $this->getLang($order),
        ];

        return [
            'gateway' => $gatewayURL,
            'method'  => 'POST',
            'inputs'  => $inputs,
        ];
    }

    /**
     * @phpstan-param PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $txType
     *
     * @param PosNetPosAccount                     $posAccount
     * @param array<string, int|string|float|null> $order
     * @param string                               $txType
     * @param CreditCardInterface                  $creditCard
     *
     * @return array<string, array<string, string|int|null>|string>
     *
     * @throws UnsupportedTransactionTypeException
     */
    public function create3DFormInitializeRequestData(AbstractPosAccount $posAccount, array $order, string $paymentModel, string $txType, ?CreditCardInterface $creditCard = null): array
    {
        if (!$creditCard instanceof CreditCardInterface) {
            throw new \InvalidArgumentException('Bu işlem için kredi kartı bilgileri gereklidir.');
        }

        $order = $this->applyPaymentDefaults($order);

        return [
            'mid'            => $posAccount->getMerchantId(),
            'tid'            => $posAccount->getTerminalId(),
            'oosRequestData' => [
                'posnetid'       => $posAccount->getPosNetId(),
                'ccno'           => $creditCard->getNumber(),
                'expDate'        => $this->valueFormatter->formatCardExpDate($creditCard->getExpirationDate(), 'expDate'),
                'cvc'            => $creditCard->getCvv(),
                'amount'         => $this->valueFormatter->formatAmount($order['amount']),
                'currencyCode'   => $this->valueMapper->mapCurrency($order['currency']),
                'installment'    => $this->valueFormatter->formatInstallment($order['installment']),
                'XID'            => $this->valueFormatter->formatOrderId($order['id']),
                'cardHolderName' => $creditCard->getHolderName(),
                'tranType'       => $this->valueMapper->mapTxType($txType),
            ],
        ];
    }

    /**
     * @param PosNetPosAccount                     $posAccount
     * @param array<string, int|string|float|null> $order
     * @param array<string, mixed>                 $responseData
     *
     * @return array<string, string|array<string, string>>
     */
    public function create3DResolveMerchantRequestData(AbstractPosAccount $posAccount, array $order, array $responseData): array
    {
        $order = $this->applyPaymentDefaults($order);

        $mappedOrder             = [];
        $mappedOrder['id']       = $this->valueFormatter->formatOrderId($order['id']);
        $mappedOrder['amount']   = $this->valueFormatter->formatAmount($order['amount']);
        $mappedOrder['currency'] = (string) $this->valueMapper->mapCurrency($order['currency']);

        $requestData = [
            'mid'                    => $posAccount->getMerchantId(),
            'tid'                    => $posAccount->getTerminalId(),
            'oosResolveMerchantData' => [
                'bankData'     => $responseData['BankPacket'],
                'merchantData' => $responseData['MerchantPacket'],
                'sign'         => $responseData['Sign'],
            ],
        ];

        $requestData['oosResolveMerchantData']['mac'] = $this->crypt->createHash($posAccount, $requestData, $mappedOrder);

        return $requestData;
    }

    /**
     * @param PosNetPosAccount $posAccount
     *
     * @inheritDoc
     */
    public function createCustomQueryRequestData(AbstractPosAccount $posAccount, array $requestData): array
    {
        return $requestData + [
                'mid' => $posAccount->getMerchantId(),
                'tid' => $posAccount->getTerminalId(),
            ];
    }

    /**
     * @param array<string, mixed> $order
     *
     * @return array<string, mixed>
     */
    private function applyPaymentDefaults(array $order): array
    {
        return array_merge($order, [
            'id'          => $order['id'],
            'installment' => $order['installment'] ?? 0,
            'amount'      => $order['amount'],
            'currency'    => $order['currency'] ?? PosInterface::CURRENCY_TRY,
        ]);
    }
}
