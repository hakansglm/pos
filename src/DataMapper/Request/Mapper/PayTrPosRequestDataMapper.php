<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\Request\Mapper;

use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\Exception\NotImplementedException;
use Mews\Pos\Gateway\PayTrPos;
use Mews\Pos\PosInterface;

/**
 * Creates request data for PayTR API requests.
 *
 * @link https://dev.paytr.com/
 *
 * @internal
 */
class PayTrPosRequestDataMapper extends AbstractRequestDataMapper
{
    /** @inheritDoc */
    public static function supports(string $gatewayClass): bool
    {
        return PayTrPos::class === $gatewayClass;
    }

    /**
     * {@inheritDoc}
     *
     * Builds the token request for the iFrame API (MODEL_3D_HOST).
     */
    public function create3DFormInitializeRequestData(AbstractPosAccount $posAccount, array $order, string $paymentModel, string $txType, ?CreditCardInterface $creditCard = null): array
    {
        $order       = $this->applyPaymentDefaults($order);
        $installment = $this->valueFormatter->formatInstallment(max(0, (int) $order['installment']));

        $requestData = [
            'merchant_id'       => $posAccount->getMerchantId(),
            'user_ip'           => (string) $order['ip'],
            'merchant_oid'      => (string) $order['id'],
            'email'             => (string) ($order['buyer']['email'] ?? ''),
            'payment_amount'    => $this->valueFormatter->formatAmount($order['amount'], PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD),
            'currency'          => $this->valueMapper->mapCurrency($order['currency']),
            'no_installment'    => 0 === (int) $installment ? 1 : 0,
            'max_installment'   => (int) $installment,
            'test_mode'         => $this->testMode ? 1 : 0,
            'lang'              => $this->valueMapper->mapLang($order['lang']),
            'user_basket'       => $this->buildBasket($order),
            'merchant_ok_url'   => (string) $order['success_url'],
            'merchant_fail_url' => (string) $order['fail_url'],
            'user_name'         => (string) ($order['buyer']['name']),
            'user_address'      => (string) ($order['billing_address']['address']),
            'user_phone'        => (string) ($order['buyer']['gsm_number']),
        ];

        $requestData['paytr_token'] = $this->crypt->createHash($posAccount, $requestData);

        return $requestData;
    }

    /**
     * {@inheritDoc}
     *
     * Builds a form POST payload for the Direct API.
     */
    public function createNonSecurePaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, CreditCardInterface $creditCard): array
    {
        $order = $this->applyPaymentDefaults($order);

        $requestData                = $this->buildDirectPaymentData($posAccount, $order, PosInterface::MODEL_NON_SECURE, $creditCard);
        $requestData['paytr_token'] = $this->crypt->createHash($posAccount, $requestData);

        return $requestData;
    }

    /**
     * {@inheritDoc}
     */
    public function create3DPaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, array $responseData): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritDoc}
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritDoc}
     */
    public function createStatusRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $requestData = [
            'merchant_id'  => $posAccount->getMerchantId(),
            'merchant_oid' => (string) $order['id'],
        ];

        $requestData['paytr_token'] = $this->crypt->createHash($posAccount, $requestData);

        return $requestData;
    }

    /**
     * {@inheritDoc}
     */
    public function createCancelRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritDoc}
     */
    public function createRefundRequestData(AbstractPosAccount $posAccount, array $order, string $refundTxType): array
    {
        /** @var array<string, mixed> $order */
        $order = [
            'id'     => $order['id'],
            'amount' => $order['amount'],
        ];

        $requestData = [
            'merchant_id'   => $posAccount->getMerchantId(),
            'merchant_oid'  => (string) $order['id'],
            'return_amount' => $this->valueFormatter->formatAmount($order['amount']),
        ];

        $requestData['paytr_token'] = $this->crypt->createHash($posAccount, $requestData);

        return $requestData;
    }

    /**
     * {@inheritDoc}
     */
    public function createOrderHistoryRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritDoc}
     *
     * @param array{start_date?: \DateTimeInterface, end_date?: \DateTimeInterface} $data
     */
    public function createHistoryRequestData(AbstractPosAccount $posAccount, array $data = []): array
    {
        /** @var array<string, mixed> $data */
        $requestData = [
            'merchant_id' => $posAccount->getMerchantId(),
            'start_date'  => $this->valueFormatter->formatDateTime($data['start_date'], 'start_date'),
            'end_date'    => $this->valueFormatter->formatDateTime($data['end_date'], 'end_date'),
        ];

        if ($this->testMode) {
            $requestData['dummy'] = 1;
        }

        $requestData['paytr_token'] = $this->crypt->createHash($posAccount, $requestData);

        return $requestData;
    }

    /**
     * {@inheritDoc}
     */
    public function createCustomQueryRequestData(AbstractPosAccount $posAccount, array $requestData): array
    {
        $requestData['merchant_id'] ??= $posAccount->getMerchantId();

        if (!isset($requestData['paytr_token'])) {
            $requestData['paytr_token'] = $this->crypt->createHash($posAccount, $requestData);
        }

        return $requestData;
    }

    /**
     * {@inheritDoc}
     *
     * MODEL_3D_HOST: returns an iframe embed URL (GET, no inputs) after token is obtained.
     * MODEL_3D_PAY:  returns a POST form with all card and payment fields for Direct API.
     *
     * @return array{gateway: string, method: 'POST'|'GET', inputs: array<string, string>}
     */
    public function create3DFormData(AbstractPosAccount $posAccount, array $order, string $paymentModel, string $txType, string $gatewayURL, ?CreditCardInterface $creditCard = null, ?array $extraData = null): array
    {
        if (PosInterface::MODEL_3D_HOST === $paymentModel) {
            $token = (string) ($extraData['token'] ?? '');

            return [
                'gateway' => $gatewayURL.'/'.$token,
                'method'  => 'GET',
                'inputs'  => [],
            ];
        }

        // MODEL_3D_PAY — build a full POST form with card data
        $preparedOrder = $this->applyPaymentDefaults($order);

        $inputs                = $this->buildDirectPaymentData($posAccount, $preparedOrder, $paymentModel, $creditCard);
        $inputs['paytr_token'] = $this->crypt->create3DHash($posAccount, $inputs);

        return [
            'gateway' => $gatewayURL,
            'method'  => 'POST',
            'inputs'  => $inputs,
        ];
    }

    /**
     * @param array<string, mixed> $order
     *
     * @return array<string, mixed>
     */
    private function applyPaymentDefaults(array $order): array
    {
        return \array_merge($order, [
            'installment'     => $order['installment'] ?? 0,
            'currency'        => $order['currency'] ?? PosInterface::CURRENCY_TRY,
            'lang'            => $order['lang'] ?? $this->defaultLang,
            'ip'              => $order['ip'] ?? '',
            'buyer'           => $order['buyer'] ?? [],
            'billing_address' => $order['billing_address'] ?? [],
            'basket_items'    => $order['basket_items'] ?? [],
        ]);
    }

    /**
     * Builds the common Direct API payment fields.
     *
     * @param array<string, mixed> $order
     *
     * @return array<string, mixed>
     */
    private function buildDirectPaymentData(AbstractPosAccount $posAccount, array $order, string $paymentModel, ?CreditCardInterface $creditCard): array
    {
        if (!$creditCard instanceof CreditCardInterface) {
            throw new \InvalidArgumentException('Bu işlem için kredi kartı bilgileri gereklidir.');
        }

        $installment = (int) $this->valueFormatter->formatInstallment(max(0, (int) $order['installment']));
        $requestData = [
            'merchant_id'       => $posAccount->getMerchantId(),
            'user_ip'           => (string) $order['ip'],
            'merchant_oid'      => (string) $order['id'],
            'email'             => (string) ($order['buyer']['email'] ?? ''),
            'payment_amount'    => $this->valueFormatter->formatAmount($order['amount']),
            'installment_count' => $installment,
            'currency'          => $this->valueMapper->mapCurrency($order['currency']),
            'non_3d'            => PosInterface::MODEL_NON_SECURE === $paymentModel ? 1 : 0,
            'sync_mode'         => PosInterface::MODEL_NON_SECURE === $paymentModel ? 1 : 0,
            'user_name'         => (string) ($order['buyer']['name'] ?? ''),
            'user_address'      => (string) ($order['billing_address']['address'] ?? ''),
            'user_phone'        => (string) ($order['buyer']['gsm_number'] ?? ''),
            'test_mode'         => $this->testMode ? 1 : 0,
            'debug_on'          => $this->testMode ? 1 : 0,
            'client_lang'       => $this->valueMapper->mapLang($order['lang']),
            'user_basket'       => $this->buildBasket($order),

            // card data
            'payment_type'      => 'card',
            'cc_owner'          => (string) $creditCard->getHolderName(),
            'card_number'       => $creditCard->getNumber(),
            'expiry_month'      => $this->valueFormatter->formatCardExpDate($creditCard->getExpirationDate(), 'expiry_month'),
            'expiry_year'       => $this->valueFormatter->formatCardExpDate($creditCard->getExpirationDate(), 'expiry_year'),
            'cvv'               => $creditCard->getCvv(),
        ];

        if ($paymentModel !== PosInterface::MODEL_NON_SECURE) {
            $requestData['merchant_ok_url']   = (string) $order['success_url'];
            $requestData['merchant_fail_url'] = (string) $order['fail_url'];
        }

        return $requestData;
    }

    /**
     * Builds the basket required by the PayTR API.
     * Format: base64( json([["name", price, count], ...]) )
     *
     * Uses $order['basket_items'] when provided; falls back to a synthetic single-item entry.
     *
     * @param array<string, mixed> $order
     */
    private function buildBasket(array $order): string
    {
        $basketItems = $order['basket_items'];

        if ([] !== $basketItems) {
            $basket = \array_map(
                static fn (array $item): array => [(string) $item['name'], $item['price'], (int) $item['item_count']],
                $basketItems
            );
        } else {
            $basket = [
                [(string) $order['id'], $order['amount'], 1],
            ];
        }

        return \base64_encode(\json_encode($basket, \JSON_THROW_ON_ERROR));
    }
}
