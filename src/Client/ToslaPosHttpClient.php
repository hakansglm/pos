<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\Exception\UnsupportedTransactionTypeException;
use Mews\Pos\Gateway\ToslaPos;
use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\PosInterface;
use Mews\Pos\PosQuery\PosQueryInterface;
use Mews\Pos\Serializer\Decoder\JsonDecoder;
use Mews\Pos\Serializer\EncodedData;
use Mews\Pos\Serializer\Encoder\JsonEncoder;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
class ToslaPosHttpClient extends AbstractHttpClient
{
    public function __construct(
        string                  $baseApiUrl,
        ClientInterface         $psrClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface  $streamFactory,
        LoggerInterface         $logger
    ) {
        parent::__construct(
            $baseApiUrl,
            $psrClient,
            $requestFactory,
            $streamFactory,
            new JsonEncoder(),
            new JsonDecoder(),
            $logger
        );
    }

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass, string $apiName): bool
    {
        return ToslaPos::class === $gatewayClass && HttpClientInterface::API_NAME_PAYMENT_API === $apiName;
    }

    /**
     * @inheritDoc
     */
    public function supportsTx(string $txType, string $paymentModel, ?string $orderTxType = null): bool
    {
        if (PosQueryInterface::QUERY_TYPE_CUSTOM_QUERY === $txType) {
            return true;
        }

        try {
            $this->getRequestURIByTransactionType($txType, $paymentModel);
        } catch (UnsupportedTransactionTypeException) {
            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     *
     * @throws UnsupportedTransactionTypeException
     * @throws \InvalidArgumentException           when a transaction type or payment model are not provided
     */
    public function getApiURL(?string $txType = null, ?string $paymentModel = null, ?string $orderTxType = null): string
    {
        if (null !== $txType && null !== $paymentModel) {
            return $this->baseApiUrl.'/'.$this->getRequestURIByTransactionType($txType, $paymentModel);
        }

        throw new \InvalidArgumentException('Transaction type and payment model are required to generate API URL');
    }

    /**
     * @inheritDoc
     */
    protected function createRequest(string $url, EncodedData $content, string $txType, ?AbstractPosAccount $account = null): RequestInterface
    {
        $body = $this->streamFactory->createStream($content->getData());

        $request = $this->requestFactory->createRequest('POST', $url);

        return $request->withHeader('Content-Type', 'application/json')
            ->withBody($body);
    }

    /**
     * @phpstan-param PosInterface::TX_TYPE_*|PosQueryInterface::QUERY_TYPE_* $txType
     * @phpstan-param PosInterface::MODEL_*                                $paymentModel
     *
     * @return string
     *
     * @throws UnsupportedTransactionTypeException
     */
    private function getRequestURIByTransactionType(string $txType, string $paymentModel): string
    {
        $arr = [
            PosInterface::TX_TYPE_PAY_AUTH               => [
                PosInterface::MODEL_NON_SECURE => 'Payment',
                PosInterface::MODEL_3D_PAY     => 'threeDPayment',
                PosInterface::MODEL_3D_HOST    => 'threeDPayment',
            ],
            PosInterface::TX_TYPE_PAY_PRE_AUTH           => [
                PosInterface::MODEL_3D_PAY  => 'threeDPreAuth',
                PosInterface::MODEL_3D_HOST => 'threeDPreAuth',
            ],
            PosInterface::TX_TYPE_PAY_POST_AUTH          => 'postAuth',
            PosInterface::TX_TYPE_CANCEL                 => 'void',
            PosInterface::TX_TYPE_REFUND                 => 'refund',
            PosInterface::TX_TYPE_REFUND_PARTIAL         => 'refund',
            PosInterface::TX_TYPE_STATUS                  => 'inquiry',
            PosInterface::TX_TYPE_ORDER_HISTORY           => 'history',
            PosQueryInterface::QUERY_TYPE_INSTALLMENT_RATES  => 'GetCommissionAndInstallmentInfo',
            PosQueryInterface::QUERY_TYPE_INSTALLMENT_PRICES => 'GetInstallmentOptions',
        ];

        if (!isset($arr[$txType])) {
            throw new UnsupportedTransactionTypeException();
        }

        if (\is_string($arr[$txType])) {
            return $arr[$txType];
        }

        if (!isset($arr[$txType][$paymentModel])) {
            throw new UnsupportedTransactionTypeException();
        }

        return $arr[$txType][$paymentModel];
    }
}
