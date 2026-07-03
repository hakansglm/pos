<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\Gateway\IyzicoPos;
use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Model\Account\IyzicoPosAccount;
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
class IyzicoPosHttpClient extends AbstractIyzicoPosHttpClient
{
    public function __construct(
        string                  $baseApiUrl,
        ClientInterface         $psrClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface  $streamFactory,
        LoggerInterface         $logger,
        CryptInterface          $crypt
    ) {
        parent::__construct(
            $baseApiUrl,
            $psrClient,
            $requestFactory,
            $streamFactory,
            $logger,
            $crypt,
            new JsonEncoder(),
            new JsonDecoder()
        );
    }

    /**
     * @inheritDoc
     */
    public function supportsTx(string $txType, string $paymentModel, ?string $orderTxType = null): bool
    {
        return !\in_array($txType, [
            PosInterface::TX_TYPE_ORDER_HISTORY,
            PosQueryInterface::QUERY_TYPE_HISTORY,
        ], true);
    }

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass, string $apiName): bool
    {
        return IyzicoPos::class === $gatewayClass && HttpClientInterface::API_NAME_PAYMENT_API === $apiName;
    }

    /**
     * @inheritDoc
     */
    public function getApiURL(?string $txType = null, ?string $paymentModel = null, ?string $orderTxType = null): string
    {
        return $this->baseApiUrl.$this->getPathByTxType($txType, $paymentModel, $orderTxType);
    }

    /**
     * @inheritDoc
     */
    protected function createRequest(string $url, EncodedData $content, string $txType, ?AbstractPosAccount $account = null): RequestInterface
    {
        if (!$account instanceof IyzicoPosAccount) {
            throw new \InvalidArgumentException(\sprintf(
                'Expected %s, got %s.',
                IyzicoPosAccount::class,
                $account instanceof \Mews\Pos\Model\Account\AbstractPosAccount ? $account::class : 'null'
            ));
        }

        $requestBody = $content->getData();

        $authStr = $this->createAuthorizationHeader($url, $requestBody, $account);

        $request = $this->requestFactory->createRequest('POST', $url);

        return $request
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', $authStr)
            ->withBody($this->streamFactory->createStream($requestBody));
    }

    /**
     * @param PosInterface::TX_TYPE_*|PosQueryInterface::QUERY_TYPE_*|null $txType
     * @param PosInterface::MODEL_*|null                                   $paymentModel
     * @param PosInterface::TX_TYPE_PAY_*|null                             $orderTxType
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    private function getPathByTxType(?string $txType, ?string $paymentModel, ?string $orderTxType): string
    {
        if (null === $txType) {
            throw new \InvalidArgumentException('Transaction type is required to generate API URL');
        }

        if (PosInterface::MODEL_3D_HOST === $paymentModel) {
            if (PosInterface::TX_TYPE_INTERNAL_3D_PAYMENT_STATUS === $txType) {
                return '/payment/iyzipos/checkoutform/auth/ecom/detail';
            }

            if (PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD === $txType) {
                if (null === $orderTxType) {
                    throw new \InvalidArgumentException('$orderTxType is required to generate 3D HOST form URL');
                }

                if (PosInterface::TX_TYPE_PAY_AUTH === $orderTxType) {
                    return '/payment/iyzipos/checkoutform/initialize/auth/ecom';
                }

                if (PosInterface::TX_TYPE_PAY_PRE_AUTH === $orderTxType) {
                    return '/payment/iyzipos/checkoutform/initialize/preauth/ecom';
                }

                throw new \InvalidArgumentException(\sprintf('Unsupported orderTxType: %s', $orderTxType));
            }
        }

        if (PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD === $txType) {
            if (PosInterface::TX_TYPE_PAY_PRE_AUTH === $orderTxType) {
                return '/payment/3dsecure/initialize/preauth';
            }

            return '/payment/3dsecure/initialize';
        }

        if (PosInterface::MODEL_3D_SECURE === $paymentModel) {
            return '/payment/v2/3dsecure/auth';
        }

        $txTypePaths = [
            PosInterface::TX_TYPE_PAY_AUTH                   => '/payment/auth',
            PosInterface::TX_TYPE_PAY_PRE_AUTH               => '/payment/preauth',
            PosInterface::TX_TYPE_PAY_POST_AUTH              => '/payment/postauth',
            PosInterface::TX_TYPE_CANCEL                     => '/payment/cancel',
            PosInterface::TX_TYPE_STATUS                     => '/payment/detail',
            PosInterface::TX_TYPE_REFUND                     => '/v2/payment/refund',
            PosInterface::TX_TYPE_REFUND_PARTIAL             => '/v2/payment/refund',
            PosQueryInterface::QUERY_TYPE_INSTALLMENT_PRICES => '/payment/iyzipos/installment',
            PosQueryInterface::QUERY_TYPE_BIN_LIST           => '/payment/bin/check',
        ];

        if (!isset($txTypePaths[$txType])) {
            throw new \InvalidArgumentException(\sprintf('Unsupported transaction type: %s', $txType));
        }

        return $txTypePaths[$txType];
    }
}
