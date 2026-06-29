<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Gateway;

use Mews\Pos\DataMapper\Request\Mapper\PosNetV1PosRequestDataMapper;
use Mews\Pos\DataMapper\Request\Mapper\RequestDataMapperInterface;
use Mews\Pos\DataMapper\Response\Mapper\PosNetV1PosResponseDataMapper;
use Mews\Pos\DataMapper\Response\Mapper\ResponseDataMapperInterface;
use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Model\Account\PosNetPosAccount;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exception\HashMismatchException;
use Mews\Pos\Exception\UnsupportedFormFormatException;
use Mews\Pos\Exception\UnsupportedPaymentModelException;
use Mews\Pos\Exception\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;

class PosNetV1Pos extends AbstractGateway
{
    /** @var string */
    public const NAME = 'PosNetV1';

    /** @var PosNetPosAccount */
    protected AbstractPosAccount $account;

    /** @var PosNetV1PosRequestDataMapper */
    protected RequestDataMapperInterface $requestDataMapper;

    /** @var PosNetV1PosResponseDataMapper */
    protected ResponseDataMapperInterface $responseDataMapper;

    /** @inheritdoc */
    protected static array $supportedTransactions = [
        PosInterface::TX_TYPE_PAY_AUTH       => [
            PosInterface::MODEL_3D_SECURE,
            PosInterface::MODEL_NON_SECURE,
        ],
        PosInterface::TX_TYPE_PAY_PRE_AUTH   => [
            PosInterface::MODEL_3D_SECURE,
            PosInterface::MODEL_NON_SECURE,
        ],
        PosInterface::TX_TYPE_PAY_POST_AUTH  => true,
        PosInterface::TX_TYPE_STATUS         => true,
        PosInterface::TX_TYPE_CANCEL         => true,
        PosInterface::TX_TYPE_REFUND         => true,
        PosInterface::TX_TYPE_REFUND_PARTIAL => true,
        PosInterface::TX_TYPE_HISTORY        => false,
        PosInterface::TX_TYPE_ORDER_HISTORY  => false,
        PosInterface::TX_TYPE_CUSTOM_QUERY   => true,
    ];

    /** @return PosNetPosAccount */
    public function getAccount(): AbstractPosAccount
    {
        return $this->account;
    }

    /**
     * Kullanıcı doğrulama sonucunun sorgulanması ve verilerin doğruluğunun teyit edilmesi için kullanılır.
     *
     * @inheritDoc
     */
    public function make3DPayment(array $gatewayResponseData, array $order, string $txType, ?CreditCardInterface $creditCard = null): array
    {
        $paymentModel   = self::MODEL_3D_SECURE;

        if (!$this->is3dAuthSuccess($gatewayResponseData)) {
            $this->response = $this->responseDataMapper->map3DPaymentData($gatewayResponseData, null, $txType, $order);

            return $this->response;
        }

        if (
            !$this->is3DHashCheckDisabled()
            && !$this->crypt->check3DHash($this->account, $gatewayResponseData)
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
            $paymentModel,
        )->request(
            $txType,
            $paymentModel,
            $requestData,
            $order
        );
        $this->logger->debug('send $provisionResponse', ['$provisionResponse' => $provisionResponse]);

        $this->response = $this->responseDataMapper->map3DPaymentData($gatewayResponseData, $provisionResponse, $txType, $order);
        $this->logger->debug('finished 3D payment', ['mapped_response' => $this->response]);

        return $this->response;
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
     * @inheritDoc
     *
     * @return array{gateway: string, method: 'POST'|'GET', inputs: array<string, string>}
     */
    public function get3DFormData(array $order, string $paymentModel, string $txType, ?CreditCardInterface $creditCard = null, bool $createWithoutCard = false, ?string $formFormat = null): array
    {
        $this->check3DFormInputs($paymentModel, $txType, $creditCard, $createWithoutCard);

        if (PosInterface::FORM_FORMAT_HTML === $formFormat) {
            throw new UnsupportedFormFormatException();
        }

        $this->logger->debug('preparing 3D form data');

        return $this->requestDataMapper->create3DFormData(
            $this->account,
            $order,
            $paymentModel,
            $txType,
            $this->get3DGatewayURL($paymentModel),
            $creditCard
        );
    }

    /**
     * @inheritDoc
     */
    public function history(array $data): array
    {
        throw new UnsupportedTransactionTypeException();
    }

    /**
     * @inheritDoc
     */
    public function orderHistory(array $order): array
    {
        throw new UnsupportedTransactionTypeException();
    }
}
