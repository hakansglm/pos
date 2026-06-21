<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Gateway\PayFlexCPV4Pos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\Decoder\XmlDecoder;
use Mews\Pos\Serializer\EncodedData;
use Mews\Pos\Serializer\Encoder\FormEncoder;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

class PayFlexCPV4PosHttpClient extends AbstractHttpClient
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
            new FormEncoder(),
            new XmlDecoder(),
            $logger
        );
    }

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass, string $apiName): bool
    {
        return PayFlexCPV4Pos::class === $gatewayClass
            && (HttpClientInterface::API_NAME_PAYMENT_API === $apiName
                // API_NAME_GATEWAY_3D_API is needed for backward compatibility with v1 configs.
                || HttpClientInterface::API_NAME_GATEWAY_3D_API === $apiName);
    }

    /**
     * @inheritDoc
     */
    public function supportsTx(string $txType, string $paymentModel, ?string $orderTxType = null): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getApiURL(?string $txType = null, ?string $paymentModel = null, ?string $orderTxType = null): string
    {
        if (PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD === $txType) {
            return $this->baseApiUrl.'/RegisterTransaction';
        }

        return $this->baseApiUrl.'/VposTransaction';
    }

    /**
     * @inheritDoc
     */
    protected function createRequest(string $url, EncodedData $content, string $txType, ?AbstractPosAccount $account = null): RequestInterface
    {
        $request = $this->requestFactory->createRequest('POST', $url);
        $body    = $this->streamFactory->createStream($content->getData());

        return $request->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withHeader('Accept', 'text/xml')
            ->withBody($body);
    }
}
