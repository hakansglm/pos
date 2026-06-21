<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\Request\Mapper;

use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Gateways\IyzicoPos;
use Mews\Pos\PosInterface;

/**
 * Creates request data for iyzico API requests.
 */
class IyzicoPosRequestDataMapper extends AbstractRequestDataMapper
{
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
    public function create3DFormData(
        AbstractPosAccount   $posAccount,
        array                $order,
        string               $paymentModel,
        string               $txType,
        string               $gatewayURL,
        ?CreditCardInterface $creditCard = null,
        ?array               $extraData = null
    ) {
        throw new NotImplementedException();
    }

    /**
     * Returns the request body for the iyzico 3D initialize endpoint.
     *
     * @param array<string, mixed> $order
     *
     * @phpstan-param PosInterface::MODEL_* $paymentModel
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
        return $this->buildPaymentRequestData($posAccount, $order, $paymentModel, $creditCard);
    }

    /**
     * {@inheritDoc}
     */
    public function create3DPaymentRequestData(
        AbstractPosAccount $posAccount,
        array              $order,
        string             $txType,
        array              $responseData
    ): array {
        $order = $this->preparePaymentOrder($order);

        return [
            'locale'         => $this->getLang($order),
            'conversationId' => (string) $responseData['conversationId'],
            'paymentId'      => (string) $responseData['paymentId'],
            'paidPrice'      => $this->valueFormatter->formatAmount($order['amount']),
            'basketId'       => (string) $order['id'],
            'currency'       => $this->valueMapper->mapCurrency($order['currency']),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function createNonSecurePaymentRequestData(
        AbstractPosAccount  $posAccount,
        array               $order,
        string              $txType,
        CreditCardInterface $creditCard
    ): array {
        return $this->buildPaymentRequestData($posAccount, $order, PosInterface::MODEL_NON_SECURE, $creditCard);
    }

    /**
     * @param AbstractPosAccount   $posAccount
     * @param array<string, mixed> $order
     *
     * @return array<string, mixed>
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        return [
            'locale'         => $this->getLang($order),
            'conversationId' => (string) $order['id'],
            'paymentId'      => (string) $order['transaction_id'],
            'paidPrice'      => $this->valueFormatter->formatAmount((float) $order['amount']),
            'ip'             => (string) $order['ip'],
            'currency'       => $this->valueMapper->mapCurrency($order['currency']),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function createStatusRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->prepareStatusOrder($order);

        $data = [
            'locale'                => $this->getLang($order),
        ];

        if (isset($order['transaction_id'])) {
            $data['paymentId'] = (string) $order['transaction_id'];
        }

        if (isset($order['id'])) {
            $data['paymentConversationId'] = (string) $order['id'];
            $data['conversationId'] = (string) $order['id'];
        }

        return $data;
    }

    /**
     * {@inheritDoc}
     */
    public function createCancelRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        return [
            'locale'         => $this->getLang($order),
            'conversationId' => (string) $order['id'],
            'paymentId'      => (string) $order['transaction_id'],
            'ip'             => (string) $order['ip'],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function createRefundRequestData(AbstractPosAccount $posAccount, array $order, string $refundTxType): array
    {
        $order = $this->prepareRefundOrder($order);

        return [
            'locale'         => $this->getLang($order),
            'conversationId' => (string) $order['id'],
            'paymentId'      => (string) $order['transaction_id'],
            'price'          => $this->valueFormatter->formatAmount($order['amount']),
            'currency'       => $this->valueMapper->mapCurrency($order['currency']),
            'ip'             => (string) $order['ip'],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function createOrderHistoryRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $data = [
            'locale'                => $this->getLang($order),
        ];

        if (isset($order['transaction_id'])) {
            $data['paymentId'] = (string) $order['transaction_id'];
        }

        if (isset($order['id'])) {
            $data['paymentConversationId'] = (string) $order['id'];
            $data['conversationId'] = (string) $order['id'];
        }

        return $data;
    }

    /**
     * {@inheritDoc}
     */
    public function createHistoryRequestData(AbstractPosAccount $posAccount, array $data = []): array
    {
        $order = $this->prepareHistoryOrder($data);

        return [
            'locale'          => $this->getLang($order),
            'transactionDate' => $this->valueFormatter->formatDateTime($data['transaction_date'], 'transactionDate'),
            'page'            => $order['page'],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function createCustomQueryRequestData(AbstractPosAccount $posAccount, array $requestData): array
    {
        return $requestData;
    }

    /**
     * @param array{token: string}                 $responseData
     * @param array<string, string|int|float|null> $order
     *
     * @return array{locale: string, conversationId: string, token: string}
     */
    public function create3DHostPaymentStatusRequestData(array $responseData, array $order): array
    {
        return [
            'locale'         => $this->getLang($order),
            'conversationId' => (string) $order['id'],
            'token'          => (string) $responseData['token'],
        ];
    }


    /**
     * @inheritDoc
     */
    protected function preparePaymentOrder(array $order): array
    {
        return \array_merge($order, [
            'installment' => $order['installment'] ?? 1,
            'currency'    => $order['currency'] ?? PosInterface::CURRENCY_TRY,
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function prepareHistoryOrder(array $data): array
    {
        return array_merge($data, [
            'page' => $data['page'] ?? 1,
        ]);
    }

    /**
     * Builds the core payment request body shared by non-3D and 3D initialize calls.
     *
     * @param AbstractPosAccount       $posAccount
     * @param array<string, mixed>     $order
     * @param PosInterface::MODEL_*    $paymentModel
     * @param CreditCardInterface|null $creditCard
     *
     * @return array<string, mixed>
     */
    private function buildPaymentRequestData(
        AbstractPosAccount   $posAccount,
        array                $order,
        string               $paymentModel,
        ?CreditCardInterface $creditCard
    ): array {
        $order = $this->preparePaymentOrder($order);

        $request = [
            'locale'          => $this->getLang($order),
            'conversationId'  => (string) $order['id'],
            'basketId'        => (string) $order['id'],
            'price'           => $this->valueFormatter->formatAmount($order['amount']),
            'paidPrice'       => $this->valueFormatter->formatAmount($order['amount']),
            'currency'        => $this->valueMapper->mapCurrency($order['currency']),
            'paymentGroup'    => 'PRODUCT',
            'buyer'           => $this->formatBuyer($order['buyer']),
            'shippingAddress' => $this->formatAddress($order['shipping_address']),
            'billingAddress'  => $this->formatAddress($order['billing_address']),
            'basketItems'     => $this->formatBasketItems($order['basket_items']),
        ];

        if ($creditCard instanceof CreditCardInterface) {
            $request['paymentCard'] = [
                'cardHolderName' => (string) $creditCard->getHolderName(),
                'cardNumber'     => $creditCard->getNumber(),
                'expireMonth'    => $this->valueFormatter->formatCardExpDate($creditCard->getExpirationDate(), 'expireMonth'),
                'expireYear'     => $this->valueFormatter->formatCardExpDate($creditCard->getExpirationDate(), 'expireYear'),
                'cvc'            => $creditCard->getCvv(),
                'registerCard'   => 0,
            ];
        }

        if (PosInterface::MODEL_NON_SECURE !== $paymentModel) {
            $request['callbackUrl'] = (string) $order['success_url'];
        }

        if (PosInterface::MODEL_3D_HOST !== $paymentModel) {
            $request['paymentChannel'] = $order['payment_channel'] ?? 'WEB';
            $request['installment']    = $this->valueFormatter->formatInstallment($order['installment']);
        } else {
            $request['forceThreeDS']        = 1;
            $request['enabledInstallments'] = $order['enabled_installments'] ?? [];
        }

        if (null !== $posAccount->getSubMerchantId()) {
            $request['subMerchantKey'] = $posAccount->getSubMerchantId();
        }

        return $request;
    }

    /**
     * @param array<string, mixed> $buyer
     *
     * @return array<string, string>
     */
    private function formatBuyer(array $buyer): array
    {
        return [
            'id'                  => (string) $buyer['id'],
            'name'                => (string) $buyer['name'],
            'surname'             => (string) $buyer['surname'],
            'identityNumber'      => (string) $buyer['identity_number'],
            'email'               => (string) $buyer['email'],
            'gsmNumber'           => (string) $buyer['gsm_number'],
            'registrationAddress' => (string) $buyer['registration_address'],
            'city'                => (string) $buyer['city'],
            'country'             => (string) $buyer['country'],
            'zipCode'             => (string) $buyer['zip_code'],
            'ip'                  => (string) $buyer['ip'],
            'registrationDate'    => (string) ($buyer['registration_date'] ?? ''),
            'lastLoginDate'       => (string) ($buyer['last_login_date'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $address
     *
     * @return array<string, string>
     */
    private function formatAddress(array $address): array
    {
        return [
            'contactName' => (string) $address['contact_name'],
            'city'        => (string) $address['city'],
            'country'     => (string) $address['country'],
            'address'     => (string) $address['address'],
            'zipCode'     => (string) ($address['zip_code'] ?? ''),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     *
     * @return array<int, array<string, mixed>>
     */
    private function formatBasketItems(array $items): array
    {
        $formatted = [];
        foreach ($items as $item) {
            $formatted[] = [
                'id'        => (string) $item['id'],
                'name'      => (string) $item['name'],
                'category1' => (string) ($item['category1'] ?? ''),
                'category2' => (string) ($item['category2'] ?? ''),
                'itemType'  => (string) $item['item_type'],
                'price'     => $this->valueFormatter->formatAmount((float) $item['price']),
            ];
        }

        return $formatted;
    }
}
