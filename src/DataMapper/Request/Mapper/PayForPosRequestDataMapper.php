<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\Request\Mapper;

use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Model\Account\PayForPosAccount;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\Event\Before3DFormHashCalculatedEvent;
use Mews\Pos\Gateway\PayForPos;
use Mews\Pos\PosInterface;

/**
 * Creates request data for PayForPos Gateway requests
 *
 * @internal
 */
class PayForPosRequestDataMapper extends AbstractRequestDataMapper
{
    /**
     * MOTO (Mail Order Telephone Order) 0 for false, 1 for true
     *
     * @var string
     */
    private const MOTO = '0';

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return PayForPos::class === $gatewayClass;
    }

    /**
     * {@inheritDoc}
     *
     * @param string $txType kullanilmiyor
     *
     * @return array{RequestGuid: mixed, UserCode: string, UserPass: string, OrderId: mixed, SecureType: string}
     */
    public function create3DPaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, array $responseData): array
    {
        $order = $this->preparePaymentOrder($order);

        return [
            'RequestGuid' => $responseData['RequestGuid'],
            'UserCode'    => $posAccount->getUsername(),
            'UserPass'    => $posAccount->getPassword(),
            'OrderId'     => $order['id'],
            'SecureType'  => '3DModelPayment',
        ];
    }

    /**
     * {@inheritDoc}
     *
     * @param PayForPosAccount $posAccount
     *
     * @return array{MbrId: string, MOTO: string, OrderId: string, SecureType: string, TxnType: string, PurchAmount: string, Currency: string, InstallmentCount: string, Lang: string, CardHolderName: string|null, Pan: string, Expiry: string, Cvv2: string, MerchantId: string, UserCode: string, UserPass: string}
     */
    public function createNonSecurePaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, CreditCardInterface $creditCard): array
    {
        $order = $this->preparePaymentOrder($order);

        return $this->getRequestAccountData($posAccount) + [
                'MOTO'             => self::MOTO,
                'OrderId'          => (string) $order['id'],
                'SecureType'       => $this->valueMapper->mapSecureType(PosInterface::MODEL_NON_SECURE),
                'TxnType'          => $this->valueMapper->mapTxType($txType),
                'PurchAmount'      => (string) $this->valueFormatter->formatAmount($order['amount']),
                'Currency'         => (string) $this->valueMapper->mapCurrency($order['currency']),
                'InstallmentCount' => (string) $this->valueFormatter->formatInstallment($order['installment']),
                'Lang'             => $this->getLang($order),
                'CardHolderName'   => $creditCard->getHolderName(),
                'Pan'              => $creditCard->getNumber(),
                'Expiry'           => $this->valueFormatter->formatCardExpDate($creditCard->getExpirationDate(), 'Expiry'),
                'Cvv2'             => $creditCard->getCvv(),
            ];
    }

    /**
     * {@inheritDoc}
     *
     * @param PayForPosAccount $posAccount
     *
     * @return array{MbrId: string, OrgOrderId: string, SecureType: string, TxnType: string, PurchAmount: string, Currency: string, Lang: string, MerchantId: string, UserCode: string, UserPass: string}
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->preparePostPaymentOrder($order);

        return $this->getRequestAccountData($posAccount) + [
                'OrgOrderId'  => (string) $order['id'],
                'SecureType'  => $this->valueMapper->mapSecureType(PosInterface::MODEL_NON_SECURE),
                'TxnType'     => $this->valueMapper->mapTxType(PosInterface::TX_TYPE_PAY_POST_AUTH),
                'PurchAmount' => (string) $this->valueFormatter->formatAmount($order['amount']),
                'Currency'    => (string) $this->valueMapper->mapCurrency($order['currency']),
                'Lang'        => $this->getLang($order),
            ];
    }

    /**
     * {@inheritDoc}
     *
     * @param PayForPosAccount $posAccount
     *
     * @return array{MbrId: string, OrgOrderId: string, SecureType: string, Lang: string, TxnType: string, MerchantId: string, UserCode: string, UserPass: string}
     */
    public function createStatusRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->prepareStatusOrder($order);

        return $this->getRequestAccountData($posAccount) + [
                'OrgOrderId' => (string) $order['id'],
                'SecureType' => 'Inquiry',
                'Lang'       => $this->getLang($order),
                'TxnType'    => $this->valueMapper->mapTxType(PosInterface::TX_TYPE_STATUS),
            ];
    }

    /**
     * {@inheritDoc}
     *
     * @param PayForPosAccount $posAccount
     *
     * @return array{MbrId: string, OrgOrderId: string, SecureType: string, TxnType: string, Currency: string, Lang: string, MerchantId: string, UserCode: string, UserPass: string}
     */
    public function createCancelRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->prepareCancelOrder($order);

        return $this->getRequestAccountData($posAccount) + [
                'OrgOrderId' => (string) $order['id'],
                'SecureType' => $this->valueMapper->mapSecureType(PosInterface::MODEL_NON_SECURE),
                'TxnType'    => $this->valueMapper->mapTxType(PosInterface::TX_TYPE_CANCEL),
                'Currency'   => (string) $this->valueMapper->mapCurrency($order['currency']),
                'Lang'       => $this->getLang($order),
            ];
    }

    /**
     * {@inheritDoc}
     *
     * @param PayForPosAccount $posAccount
     *
     * @return array{MbrId: string, SecureType: string, Lang: string, OrgOrderId: string, TxnType: string, PurchAmount: string, Currency: string, MerchantId: string, UserCode: string, UserPass: string}
     */
    public function createRefundRequestData(AbstractPosAccount $posAccount, array $order, string $refundTxType): array
    {
        $order = $this->prepareRefundOrder($order);

        return $this->getRequestAccountData($posAccount) + [
                'SecureType'  => $this->valueMapper->mapSecureType(PosInterface::MODEL_NON_SECURE),
                'Lang'        => $this->getLang($order),
                'OrgOrderId'  => (string) $order['id'],
                'TxnType'     => $this->valueMapper->mapTxType($refundTxType),
                'PurchAmount' => (string) $this->valueFormatter->formatAmount($order['amount']),
                'Currency'    => (string) $this->valueMapper->mapCurrency($order['currency']),
            ];
    }

    /**
     * {@inheritDoc}
     *
     * @param PayForPosAccount $posAccount
     */
    public function createOrderHistoryRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->prepareOrderHistoryOrder($order);

        $requestData = [
            'SecureType' => 'Report',
            'OrderId'    => $order['id'],
            'TxnType'    => $this->valueMapper->mapTxType(PosInterface::TX_TYPE_HISTORY),
            'Lang'       => $this->getLang($order),
        ];

        return $this->getRequestAccountData($posAccount) + $requestData;
    }

    /**
     * @param PayForPosAccount                            $posAccount
     * @param array{transaction_date: \DateTimeInterface} $data
     *
     * {@inheritDoc}
     */
    public function createHistoryRequestData(AbstractPosAccount $posAccount, array $data = []): array
    {
        $order = $this->prepareHistoryOrder($data);

        $requestData = [
            'SecureType' => 'Report',
            'ReqDate'    => $this->valueFormatter->formatDateTime($data['transaction_date'], 'ReqDate'),
            'TxnType'    => $this->valueMapper->mapTxType(PosInterface::TX_TYPE_HISTORY),
            'Lang'       => $this->getLang($order),
        ];

        return $this->getRequestAccountData($posAccount) + $requestData;
    }

    /**
     * @param PayForPosAccount $posAccount
     *
     * @inheritDoc
     */
    public function createCustomQueryRequestData(AbstractPosAccount $posAccount, array $requestData): array
    {
        return $requestData + $this->getRequestAccountData($posAccount);
    }

    /**
     * {@inheritDoc}
     *
     * @param PayForPosAccount $posAccount
     *
     * @return array{gateway: string, method: 'POST', inputs: array<string, string>}
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
        $order = $this->preparePaymentOrder($order);

        $inputs = $this->common3DFormDataGenerator($posAccount, $order, $paymentModel, $txType, $creditCard);

        return [
            'gateway' => $gatewayURL, //to be filled by the caller
            'method'  => 'POST',
            'inputs'  => $inputs,
        ];
    }

    /**
     * Returns the request body for the iyzico 3D initialize endpoint.
     *
     * @param PayForPosAccount                                                  $posAccount
     * @param array<string, mixed>                                              $order
     * @param PosInterface::MODEL_3D_*                                          $paymentModel
     * @param PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $txType
     *
     * @return array<string, mixed>
     */
    public function create3DFormInitializeRequestData(
        AbstractPosAccount   $posAccount,
        array                $order,
        string               $paymentModel,
        string               $txType,
        ?CreditCardInterface $creditCard = null
    ): array {
        return $this->common3DFormDataGenerator($posAccount, $order, $paymentModel, $txType, $creditCard);
    }

    /**
     * @inheritDoc
     */
    protected function preparePaymentOrder(array $order): array
    {
        return array_merge($order, [
            'installment' => $order['installment'] ?? 0,
            'currency'    => $order['currency'] ?? PosInterface::CURRENCY_TRY,
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function preparePostPaymentOrder(array $order): array
    {
        return [
            'id'       => $order['id'],
            'amount'   => $order['amount'],
            'currency' => $order['currency'] ?? PosInterface::CURRENCY_TRY,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareHistoryOrder(array $data): array
    {
        return [
            'transaction_date' => $data['transaction_date'],
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareOrderHistoryOrder(array $order): array
    {
        return [
            'id' => $order['id'],
        ];
    }

    /**
     *
     * @param PayForPosAccount                                                  $posAccount
     * @param array<string, mixed>                                              $order
     * @param PosInterface::MODEL_3D_*                                          $paymentModel
     * @param PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $txType
     *
     * @return array<string, string>
     */
    private function common3DFormDataGenerator(
        AbstractPosAccount $posAccount,
        array $order,
        string $paymentModel,
        string $txType,
        ?CreditCardInterface $creditCard
    ): array {
        $order = $this->preparePaymentOrder($order);

        $formData = [
            'MbrId'            => $posAccount->getMbrId(),
            'MerchantID'       => $posAccount->getMerchantId(),
            'UserCode'         => $posAccount->getUsername(),
            'OrderId'          => (string) $order['id'],
            'Lang'             => $this->getLang($order),
            'SecureType'       => $this->valueMapper->mapSecureType($paymentModel),
            'TxnType'          => $this->valueMapper->mapTxType($txType),
            'PurchAmount'      => (string) $this->valueFormatter->formatAmount($order['amount']),
            'InstallmentCount' => (string) $this->valueFormatter->formatInstallment($order['installment']),
            'Currency'         => (string) $this->valueMapper->mapCurrency($order['currency']),
            'OkUrl'            => (string) $order['success_url'],
            'FailUrl'          => (string) $order['fail_url'],
            'Rnd'              => $this->crypt->generateRandomString(),
        ];

        if ($creditCard instanceof CreditCardInterface) {
            $formData['CardHolderName'] = $creditCard->getHolderName() ?? '';
            $formData['Pan']            = $creditCard->getNumber();
            $formData['Expiry']         = $this->valueFormatter->formatCardExpDate($creditCard->getExpirationDate(), 'Expiry');
            $formData['Cvv2']           = $creditCard->getCvv();
        }

        $event = new Before3DFormHashCalculatedEvent(
            $formData,
            $posAccount->getBankName(),
            $txType,
            $paymentModel,
            PayForPos::class
        );
        $this->eventDispatcher->dispatch($event);
        $formData = $event->getFormInputs();

        $formData['Hash'] = $this->crypt->create3DHash($posAccount, $formData);

        return $formData;
    }

    /**
     * @param PayForPosAccount $posAccount
     *
     * @return array{MerchantId: string, UserCode: string, UserPass: string, MbrId: string}
     */
    private function getRequestAccountData(AbstractPosAccount $posAccount): array
    {
        return [
            'MerchantId' => $posAccount->getMerchantId(),
            'UserCode'   => $posAccount->getUsername(),
            'UserPass'   => $posAccount->getPassword(),
            'MbrId'      => $posAccount->getMbrId(),
        ];
    }
}
