<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Exception\UnsupportedTransactionTypeException;
use Mews\Pos\Gateway\VakifKatilimPos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\Decoder\VakifKatilimPosXmlDecoder;
use Mews\Pos\Serializer\EncodedData;
use Mews\Pos\Serializer\Encoder\XmlEncoder;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
class VakifKatilimPosHttpClient extends AbstractHttpClient
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
            new XmlEncoder('VPosMessageContract', 'ISO-8859-1'),
            new VakifKatilimPosXmlDecoder(),
            $logger
        );
    }

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass, string $apiName): bool
    {
        return VakifKatilimPos::class === $gatewayClass
            && (HttpClientInterface::API_NAME_PAYMENT_API === $apiName
                // API_NAME_GATEWAY_3D_API is needed for backward compatibility with v1 configs.
                || HttpClientInterface::API_NAME_GATEWAY_3D_API === $apiName);
    }

    /**
     * @inheritDoc
     */
    public function supportsTx(string $txType, string $paymentModel, ?string $orderTxType = null): bool
    {
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
     * @throws \InvalidArgumentException           when a transaction type or a payment model are not provided
     */
    public function getApiURL(?string $txType = null, ?string $paymentModel = null, ?string $orderTxType = null): string
    {
        if (null !== $txType && null !== $paymentModel) {
            return $this->baseApiUrl.'/'.$this->getRequestURIByTransactionType($txType, $paymentModel, $orderTxType);
        }

        throw new \InvalidArgumentException('Transaction type and payment model are required to generate API URL');
    }

    /**
     * @inheritDoc
     */
    public function request(
        string              $txType,
        string              $paymentModel,
        array               $requestData,
        array               $order,
        ?string             $url = null,
        ?AbstractPosAccount $account = null,
        ?string             $orderTxType = null
    ) {
        $content = $this->encoder->encode($requestData);

        return $this->doRequest(
            $txType,
            $paymentModel,
            $content,
            $order,
            $url,
            $account,
            PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD !== $txType,
            $orderTxType
        );
    }

    /**
     * @return RequestInterface
     */
    protected function createRequest(string $url, EncodedData $content, ?string $txType = null, ?AbstractPosAccount $account = null): RequestInterface
    {
        $body    = $this->streamFactory->createStream($content->getData());
        $request = $this->requestFactory->createRequest('POST', $url);

        return $request->withHeader('Content-Type', 'text/xml; charset=UTF-8')
            ->withBody($body);
    }

    /**
     * @phpstan-param PosInterface::TX_TYPE_*     $txType
     * @phpstan-param PosInterface::MODEL_*       $paymentModel
     * @phpstan-param PosInterface::TX_TYPE_PAY_* $orderTxType
     *
     * @return non-empty-string
     *
     * @throws UnsupportedTransactionTypeException
     */
    private function getRequestURIByTransactionType(string $txType, string $paymentModel, ?string $orderTxType = null): string
    {
        $orderTxType ??= PosInterface::TX_TYPE_PAY_AUTH;

        $arr = [
            PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD => 'ThreeDModelPayGate',
            PosInterface::TX_TYPE_PAY_AUTH       => [
                PosInterface::MODEL_NON_SECURE => 'Non3DPayGate',
                PosInterface::MODEL_3D_SECURE  => 'ThreeDModelProvisionGate',
            ],
            PosInterface::TX_TYPE_PAY_PRE_AUTH   => [
                PosInterface::MODEL_NON_SECURE => 'PreAuthorizaten',
            ],
            PosInterface::TX_TYPE_PAY_POST_AUTH  => 'PreAuthorizatenClose',
            PosInterface::TX_TYPE_CANCEL         => [
                PosInterface::MODEL_NON_SECURE => [
                    PosInterface::TX_TYPE_PAY_AUTH     => 'SaleReversal',
                    PosInterface::TX_TYPE_PAY_PRE_AUTH => 'PreAuthorizationReversal',
                ],
            ],
            PosInterface::TX_TYPE_REFUND         => [
                PosInterface::MODEL_NON_SECURE => [
                    PosInterface::TX_TYPE_PAY_AUTH     => 'DrawBack',
                    PosInterface::TX_TYPE_PAY_PRE_AUTH => 'PreAuthorizationDrawBack',
                ],
            ],
            PosInterface::TX_TYPE_REFUND_PARTIAL => [
                PosInterface::MODEL_NON_SECURE => [
                    PosInterface::TX_TYPE_PAY_AUTH => 'PartialDrawBack',
                ],
            ],
            PosInterface::TX_TYPE_STATUS         => 'SelectOrderByMerchantOrderId',
            PosInterface::TX_TYPE_ORDER_HISTORY  => 'SelectOrder',
            PosInterface::TX_TYPE_HISTORY        => 'SelectOrder',
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

        if (\is_string($arr[$txType][$paymentModel])) {
            return  $arr[$txType][$paymentModel];
        }

        if (!isset($arr[$txType][$paymentModel][$orderTxType])) {
            throw new UnsupportedTransactionTypeException();
        }

        return $arr[$txType][$paymentModel][$orderTxType];
    }
}
