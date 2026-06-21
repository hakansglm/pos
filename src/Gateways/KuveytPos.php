<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Gateways;

use LogicException;
use Mews\Pos\DataMapper\Request\Mapper\KuveytPosRequestDataMapper;
use Mews\Pos\DataMapper\Request\Mapper\RequestDataMapperInterface;
use Mews\Pos\DataMapper\Response\Mapper\KuveytPosResponseDataMapper;
use Mews\Pos\DataMapper\Response\Mapper\ResponseDataMapperInterface;
use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Model\Account\BoaPosAccount;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exceptions\UnsupportedFormFormatException;
use Mews\Pos\Serializer\Decoder\XmlDecoder;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;
use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;

/**
 * Kuveyt banki desteleyen Gateway
 */
class KuveytPos extends AbstractGateway
{
    /** @var string */
    public const NAME = 'KuveytPos';

    /** @var BoaPosAccount */
    protected AbstractPosAccount $account;

    /** @var KuveytPosRequestDataMapper */
    protected RequestDataMapperInterface $requestDataMapper;

    /** @var KuveytPosResponseDataMapper */
    protected ResponseDataMapperInterface $responseDataMapper;

    /** @inheritdoc */
    protected static array $supportedTransactions = [
        PosInterface::TX_TYPE_PAY_AUTH       => [
            PosInterface::MODEL_NON_SECURE,
            PosInterface::MODEL_3D_SECURE,
        ],
        PosInterface::TX_TYPE_PAY_PRE_AUTH   => false,
        PosInterface::TX_TYPE_PAY_POST_AUTH  => false,
        PosInterface::TX_TYPE_STATUS         => true,
        PosInterface::TX_TYPE_CANCEL         => true,
        PosInterface::TX_TYPE_REFUND         => true,
        PosInterface::TX_TYPE_REFUND_PARTIAL => true,
        PosInterface::TX_TYPE_HISTORY        => false,
        PosInterface::TX_TYPE_ORDER_HISTORY  => false,
        PosInterface::TX_TYPE_CUSTOM_QUERY   => false,
    ];

    /** @return BoaPosAccount */
    public function getAccount(): AbstractPosAccount
    {
        return $this->account;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayPayment(array $gatewayResponseData, array $order, string $txType): array
    {
        throw new UnsupportedPaymentModelException();
    }

    /**
     * @inheritDoc
     */
    public function make3DHostPayment(array $gatewayResponseData, array $order, string $txType): array
    {
        throw new UnsupportedPaymentModelException();
    }

    /**
     * Kuveyt bank dokumantasyonunda history sorgusu ile alakali hic bir bilgi yok
     *
     * @inheritDoc
     */
    public function history(array $data): array
    {
        throw new UnsupportedTransactionTypeException();
    }

    /**
     * Kuveyt bank dokumantasyonunda history sorgusu ile alakali hic bir bilgi yok
     *
     * @inheritDoc
     */
    public function orderHistory(array $order): array
    {
        throw new UnsupportedTransactionTypeException();
    }

    /**
     * @inheritDoc
     *
     * @return string
     */
    public function get3DFormData(array $order, string $paymentModel, string $txType, ?CreditCardInterface $creditCard = null, bool $createWithoutCard = false, ?string $formFormat = null): string
    {
        $this->check3DFormInputs($paymentModel, $txType, $creditCard, $createWithoutCard);

        if (PosInterface::FORM_FORMAT_ARRAY === $formFormat) {
            throw new UnsupportedFormFormatException();
        }

        $this->logger->debug('preparing 3D form data');

        return $this->getCommon3DFormData(
            $this->account,
            $order,
            $paymentModel,
            $txType,
            $creditCard
        );
    }

    /**
     * @inheritDoc
     */
    public function makeRegularPostPayment(array $order): array
    {
        throw new UnsupportedPaymentModelException();
    }

    /**
     * @inheritDoc
     */
    public function make3DPayment(array $gatewayResponseData, array $order, string $txType, ?CreditCardInterface $creditCard = null): array
    {
        $paymentModel    = PosInterface::MODEL_3D_SECURE;
        $gatewayResponse = $gatewayResponseData['AuthenticationResponse'] ?? null;
        if (!\is_string($gatewayResponse)) {
            throw new LogicException('AuthenticationResponse is missing');
        }

        $gatewayResponse = \urldecode($gatewayResponse);
        $gatewayResponse = (new XmlDecoder())->decode($gatewayResponse);

        if (!$this->is3DAuthSuccess($gatewayResponse)) {
            $this->response = $this->responseDataMapper->map3DPaymentData($gatewayResponse, null, $txType, $order);

            return $this->response;
        }

        $this->logger->debug('finishing payment');

        $requestData = $this->requestDataMapper->create3DPaymentRequestData($this->account, $order, $txType, $gatewayResponse);

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

        /** @var array<string, mixed> $bankResponse */
        $bankResponse = $this->clientStrategy->getClient(
            $txType,
            $paymentModel,
        )->request(
            $txType,
            $paymentModel,
            $requestData,
            $order
        );

        $this->response = $this->responseDataMapper->map3DPaymentData($gatewayResponse, $bankResponse, $txType, $order);
        $this->logger->debug('finished 3D payment', ['mapped_response' => $this->response]);

        return $this->response;
    }

    /**
     * @inheritDoc
     */
    public function customQuery(array $requestData, ?string $apiUrl = null): array
    {
        throw new UnsupportedTransactionTypeException();
    }

    /**
     * @phpstan-param PosInterface::MODEL_3D_*                                          $paymentModel
     * @phpstan-param PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $orderTxType
     *
     * @param BoaPosAccount                        $boaPosAccount
     * @param array<string, int|string|float|null> $order
     * @param string                               $paymentModel
     * @param string                               $orderTxType
     * @param CreditCardInterface|null             $creditCard
     *
     * @return string HTML form
     *
     * @throws RuntimeException
     * @throws UnsupportedTransactionTypeException
     * @throws ClientExceptionInterface
     */
    private function getCommon3DFormData(BoaPosAccount $boaPosAccount, array $order, string $paymentModel, string $orderTxType, ?CreditCardInterface $creditCard = null): string
    {
        $requestData = $this->requestDataMapper->create3DFormInitializeRequestData(
            $boaPosAccount,
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

        /** @var string $result */
        $result = $this->clientStrategy->getClient(
            $apiRequestTxType,
            $paymentModel,
        )->request(
            $apiRequestTxType,
            $paymentModel,
            $requestData,
            $order,
        );

        return $result;
    }
}
