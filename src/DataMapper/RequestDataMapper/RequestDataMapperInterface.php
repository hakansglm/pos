<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestDataMapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;

interface RequestDataMapperInterface
{
    /**
     * @param class-string<PosInterface> $gatewayClass
     *
     * @return bool
     */
    public static function supports(string $gatewayClass): bool;

    /**
     * @return bool
     */
    public function isTestMode(): bool;

    /**
     * @param bool $testMode
     */
    public function setTestMode(bool $testMode): void;

    /**
     * @return CryptInterface
     */
    public function getCrypt(): CryptInterface;

    /**
     * @phpstan-param PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $txType
     * @phpstan-param PosInterface::MODEL_3D_*                                          $paymentModel
     *
     * @param AbstractPosAccount                   $posAccount
     * @param array<string, string|int|float|null> $order
     * @param string                               $paymentModel
     * @param string                               $txType
     * @param string                               $gatewayURL
     * @param CreditCardInterface|null             $creditCard
     * @param array<string, mixed>                 $extraData    additional data that can be used when creating a 3D form data.
     *                                                           It is usually a Bank API response data
     *
     * @return array{gateway: string, method: 'POST'|'GET', inputs: array<string, string>}|non-empty-string
     *
     * @throws UnsupportedTransactionTypeException
     */
    public function create3DFormData(
        AbstractPosAccount   $posAccount,
        array                $order,
        string               $paymentModel,
        string               $txType,
        string               $gatewayURL,
        ?CreditCardInterface $creditCard = null,
        ?array               $extraData = null
    );

    /**
     * @phpstan-param PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $txType
     *
     * @param AbstractPosAccount                   $posAccount
     * @param array<string, string|int|float|null> $order
     * @param string                               $txType
     * @param array<string, mixed>                 $responseData gateway'den gelen cevap
     *
     * @return array<string, mixed>
     *
     * @throws UnsupportedTransactionTypeException
     */
    public function create3DPaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, array $responseData): array;

    /**
     * @phpstan-param PosInterface::TX_TYPE_PAY_* $txType
     *
     * @param AbstractPosAccount                   $posAccount
     * @param array<string, string|int|float|null> $order
     * @param string                               $txType
     * @param CreditCardInterface                  $creditCard
     *
     * @return array<string, mixed>
     *
     * @throws UnsupportedTransactionTypeException
     */
    public function createNonSecurePaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, CreditCardInterface $creditCard): array;

    /**
     * @param AbstractPosAccount                   $posAccount
     * @param array<string, string|int|float|null> $order
     *
     * @return array<string, mixed>
     *
     * @throws UnsupportedTransactionTypeException
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $posAccount, array $order): array;

    /**
     * @param AbstractPosAccount                   $posAccount
     * @param array<string, string|int|float|null> $order
     *
     * @return array<string, mixed>
     *
     * @throws UnsupportedTransactionTypeException
     */
    public function createStatusRequestData(AbstractPosAccount $posAccount, array $order): array;

    /**
     * @param AbstractPosAccount                   $posAccount
     * @param array<string, string|int|float|null> $order
     *
     * @return array<string, mixed>
     *
     * @throws UnsupportedTransactionTypeException
     */
    public function createCancelRequestData(AbstractPosAccount $posAccount, array $order): array;

    /**
     * @phpstan-param PosInterface::TX_TYPE_REFUND* $refundTxType
     *
     * @param AbstractPosAccount                   $posAccount
     * @param array<string, string|int|float|null> $order
     * @param string                               $refundTxType
     *
     * @return array<string, mixed>
     *
     * @throws UnsupportedTransactionTypeException
     */
    public function createRefundRequestData(AbstractPosAccount $posAccount, array $order, string $refundTxType): array;

    /**
     * @param AbstractPosAccount                   $posAccount
     * @param array<string, string|int|float|null> $order
     *
     * @return array<string, mixed>
     *
     * @throws UnsupportedTransactionTypeException
     */
    public function createOrderHistoryRequestData(AbstractPosAccount $posAccount, array $order): array;

    /**
     * @param AbstractPosAccount   $posAccount
     * @param array<string, mixed> $data       bankaya gore degisen ozel degerler
     *
     * @return array<string, mixed>
     *
     * @throws UnsupportedTransactionTypeException
     */
    public function createHistoryRequestData(AbstractPosAccount $posAccount, array $data = []): array;


    /**
     * Adds account information, constant values, calculated hash into $requestData if it is not already set.
     *
     * @param AbstractPosAccount   $posAccount
     * @param array<string, mixed> $requestData user generated request data
     *
     * @return array<string, mixed>
     */
    public function createCustomQueryRequestData(AbstractPosAccount $posAccount, array $requestData): array;

    /**
     * Builds the request data sent to the bank to initiate or verify a 3D Secure payment step.
     *
     * Depending on the gateway this method serves one of three roles:
     *
     * 1. **Enrollment check** (PayFlexV4, PayFlexCPV4, VakifKatilim, KuveytPos)
     *    Verifies whether the card is enrolled in 3D Secure.
     *    The bank responds with ECI/CAVV values and a redirect URL.
     *    The customer is then redirected to the issuing bank's ACS page.
     *
     * 2. **Session initialization** (iyzico, Tosla, PayFor)
     *    Registers a payment session with the bank's 3D system.
     *    The bank responds with a token or HTML form for the customer to complete authentication.
     *
     * 3. **Hosted payment form preparation** (Param, Param3DHost)
     *    Builds a SOAP envelope that the bank uses to render a hosted 3D payment form.
     *    The response contains encrypted form data that must be passed to the hosted page.
     *
     * Gateways that do not use this step (e.g. EstPos, Garanti, Akbank) throw \BadMethodCallException.
     *
     * @param AbstractPosAccount       $posAccount
     * @param array<string, mixed>     $order        Normalized order array
     * @param string                   $paymentModel PosInterface::MODEL_3D_*
     * @param string                   $txType       PosInterface::TX_TYPE_PAY_*
     * @param CreditCardInterface|null $creditCard   Required for non-hosted models
     *
     * @return array<string, mixed>
     */
    public function create3DFormInitializeRequestData(
        AbstractPosAccount   $posAccount,
        array                $order,
        string               $paymentModel,
        string               $txType,
        ?CreditCardInterface $creditCard = null
    ): array;
}
