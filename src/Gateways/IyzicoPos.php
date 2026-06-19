<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Gateways;

use Mews\Pos\DataMapper\RequestDataMapper\IyzicoPosRequestDataMapper;
use Mews\Pos\DataMapper\RequestDataMapper\RequestDataMapperInterface;
use Mews\Pos\DataMapper\ResponseDataMapper\IyzicoPosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\ResponseDataMapperInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\IyzicoPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exceptions\HashMismatchException;
use Mews\Pos\Exceptions\UnsupportedFormFormatException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;
use Psr\Http\Client\ClientExceptionInterface;

/**
 * @since 2.0.0
 * @link https://docs.iyzico.com/
 */
class IyzicoPos extends AbstractGateway
{
    /** @var string */
    public const NAME = 'IyzicoPos';

    /** @var IyzicoPosAccount */
    protected AbstractPosAccount $account;

    /** @var IyzicoPosRequestDataMapper */
    protected RequestDataMapperInterface $requestDataMapper;

    /** @var IyzicoPosResponseDataMapper */
    protected ResponseDataMapperInterface $responseDataMapper;

    /** @inheritdoc */
    protected static array $supportedTransactions = [
        PosInterface::TX_TYPE_PAY_AUTH       => [
            PosInterface::MODEL_3D_SECURE,
            PosInterface::MODEL_NON_SECURE,
            PosInterface::MODEL_3D_HOST,
        ],
        PosInterface::TX_TYPE_PAY_PRE_AUTH   => [
            PosInterface::MODEL_3D_SECURE,
            PosInterface::MODEL_NON_SECURE,
            PosInterface::MODEL_3D_HOST,
        ],
        PosInterface::TX_TYPE_PAY_POST_AUTH  => true,
        PosInterface::TX_TYPE_CANCEL         => true,
        PosInterface::TX_TYPE_REFUND         => true,
        PosInterface::TX_TYPE_REFUND_PARTIAL => true,
        /**
         * Not: status isteği /payment/detail ödemenin son durumunu dönmüyor.
         * Örneğin iptal/iade edildiği bilgisi yer almaz.
         */
        PosInterface::TX_TYPE_STATUS         => true,
        PosInterface::TX_TYPE_HISTORY        => true,
        PosInterface::TX_TYPE_ORDER_HISTORY  => true,
        PosInterface::TX_TYPE_CUSTOM_QUERY   => true,
    ];

    /** @return IyzicoPosAccount */
    public function getAccount(): AbstractPosAccount
    {
        return $this->account;
    }

    /**
     * {@inheritDoc}
     */
    public function get3DFormData(
        array                $order,
        string               $paymentModel,
        string               $txType,
        ?CreditCardInterface $creditCard = null,
        bool                 $createWithoutCard = false,
        ?string              $formFormat = null
    ) {
        $this->check3DFormInputs($paymentModel, $txType, $creditCard, $createWithoutCard);

        if (PosInterface::MODEL_3D_SECURE === $paymentModel && PosInterface::FORM_FORMAT_ARRAY === $formFormat) {
            throw new UnsupportedFormFormatException();
        }

        $this->logger->debug('preparing iyzico 3D form data');


        $initResponse = $this->initialize3DForm($order, $paymentModel, $txType, $creditCard);

        if ($this->responseDataMapper::PROCEDURE_SUCCESS_CODE !== ($initResponse['status'] ?? null)) {
            $this->logger->error('3D form generation failed', [
                'response_body' => $initResponse,
                'url'           => $this->get3DGatewayURL(),
                'tx_type'       => $txType,
            ]);

            throw new \RuntimeException(
                (string) ($initResponse['errorMessage'] ?? 'iyzico 3D form verisi oluşturulamadı!'),
                (int) ($initResponse['errorCode'] ?? 0)
            );
        }

        if (PosInterface::MODEL_3D_SECURE === $paymentModel) {
            /** @var non-empty-string $html */
            $html = \base64_decode((string) $initResponse['threeDSHtmlContent'], true);

            return $html;
        }

        /**
         * For 3D Host (CheckoutForm) payment Iyzico returns both a URL for redirection
         * and the HTML script to be shown to the user.
         * Either of them can be used for the next steps.
         */
        if (null === $formFormat || PosInterface::FORM_FORMAT_HTML === $formFormat) {
            return $initResponse['checkoutFormContent'];
        }

        return [
            'gateway' => $initResponse['paymentPageUrl'],
            'method'  => 'GET',
            'inputs'  => [],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function make3DPayment(array $gatewayResponseData, array $order, string $txType, ?CreditCardInterface $creditCard = null): array
    {
        $paymentModel = PosInterface::MODEL_3D_SECURE;

        if (!$this->is3DAuthSuccess($gatewayResponseData)) {
            $this->response = $this->responseDataMapper->map3DPaymentData(
                $gatewayResponseData,
                null,
                $txType,
                $order
            );

            return $this->response;
        }

        if (
            !$this->is3DHashCheckDisabled()
            && !$this->requestDataMapper->getCrypt()->check3DHash($this->account, $gatewayResponseData)
        ) {
            throw new HashMismatchException();
        }

        $requestData = $this->requestDataMapper->create3DPaymentRequestData(
            $this->account,
            $order,
            $txType,
            $gatewayResponseData
        );

        $event = new RequestDataPreparedEvent(
            $requestData,
            $this->account->getBankName(),
            $txType,
            static::class,
            $order,
            $paymentModel
        );
        /** @var RequestDataPreparedEvent $event */
        $event = $this->eventDispatcher->dispatch($event);
        if ($requestData !== $event->getRequestData()) {
            $this->logger->debug('Request data is changed via listeners', [
                'txType'      => $event->getTxType(),
                'bankName'    => $event->getBankName(),
                'initialData' => $requestData,
                'updatedData' => $event->getRequestData(),
            ]);
            $requestData = $event->getRequestData();
        }

        /** @var array<string, mixed> $provisionResponse */
        $provisionResponse = $this->clientStrategy->getClient(
            $txType,
            $paymentModel
        )->request(
            $txType,
            $paymentModel,
            $requestData,
            $order,
            null,
            $this->account
        );

        $this->response = $this->responseDataMapper->map3DPaymentData(
            $gatewayResponseData,
            $provisionResponse,
            $txType,
            $order
        );

        $this->logger->debug('finished 3D payment', ['mapped_response' => $this->response]);

        return $this->response;
    }

    /**
     * {@inheritDoc}
     */
    public function make3DPayPayment(array $gatewayResponseData, array $order, string $txType): array
    {
        throw new UnsupportedTransactionTypeException();
    }

    /**
     * {@inheritDoc}
     */
    public function make3DHostPayment(array $gatewayResponseData, array $order, string $txType): array
    {
        $response = $this->get3DHostPaymentStatus($gatewayResponseData, $order);

        $this->response = $this->responseDataMapper->map3DHostResponseData(
            $response,
            $txType,
            $order
        );

        return $this->response;
    }

    /**
     * retrieve status of the 3D HOST Payment
     *
     * @param array<string, mixed> $gatewayResponseData
     * @param array<string, mixed> $order
     *
     * @return array<string, mixed>
     *
     * @throws ClientExceptionInterface
     */
    private function get3DHostPaymentStatus(array $gatewayResponseData, array $order): array
    {
        $apiRequestTxType = PosInterface::TX_TYPE_INTERNAL_3D_PAYMENT_STATUS;
        $paymentModel = PosInterface::MODEL_3D_HOST;

        /** @var array{token: string} $queryParams */
        $queryParams = $gatewayResponseData;

        // Burda odemenin basarili olup olmadigini sorguluyoruz.
        $requestData = $this->requestDataMapper->create3DHostPaymentStatusRequestData($queryParams, $order);

        $event = new RequestDataPreparedEvent(
            $requestData,
            $this->account->getBankName(),
            $apiRequestTxType,
            static::class,
            $order,
            $paymentModel
        );
        /** @var RequestDataPreparedEvent $event */
        $event = $this->eventDispatcher->dispatch($event);
        if ($requestData !== $event->getRequestData()) {
            $this->logger->debug('Request data is changed via listeners', [
                'txType'      => $event->getTxType(),
                'bankName'    => $event->getBankName(),
                'initialData' => $requestData,
                'updatedData' => $event->getRequestData(),
            ]);
            $requestData = $event->getRequestData();
        }

        /** @var array<string, mixed> $result */
        $result = $this->clientStrategy->getClient(
            $apiRequestTxType,
            $paymentModel,
        )->request(
            $apiRequestTxType,
            $paymentModel,
            $requestData,
            $order,
            null,
            $this->account
        );

        return $result;
    }

    /**
     * @param array<string, mixed>                                              $order
     * @param PosInterface::MODEL_3D_*                                          $paymentModel
     * @param PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $orderTxType
     * @param CreditCardInterface|null                                          $creditCard
     *
     * @return array<string, mixed>
     *
     * @throws ClientExceptionInterface
     * @throws UnsupportedTransactionTypeException
     */
    private function initialize3DForm(array $order, string $paymentModel, string $orderTxType, ?CreditCardInterface $creditCard = null): array
    {
        $requestData = $this->requestDataMapper->create3DFormInitializeRequestData(
            $this->account,
            $order,
            $paymentModel,
            $orderTxType,
            $creditCard
        );

        $apiRequestTxType = PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD;

        $event = new RequestDataPreparedEvent(
            $requestData,
            $this->account->getBankName(),
            $apiRequestTxType,
            static::class,
            $order,
            $paymentModel
        );
        /** @var RequestDataPreparedEvent $event */
        $event = $this->eventDispatcher->dispatch($event);
        if ($requestData !== $event->getRequestData()) {
            $this->logger->debug('Request data is changed via listeners', [
                'txType'      => $event->getTxType(),
                'bankName'    => $event->getBankName(),
                'initialData' => $requestData,
                'updatedData' => $event->getRequestData(),
            ]);
            $requestData = $event->getRequestData();
        }

        /** @var array<string, mixed> $initResponse */
        $initResponse = $this->clientStrategy->getClient(
            $apiRequestTxType,
            $paymentModel
        )->request(
            $apiRequestTxType,
            $paymentModel,
            $requestData,
            $order,
            null,
            $this->account,
            $orderTxType
        );

        return $initResponse;
    }
}
