<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Gateway\PayForPos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\Decoder\PayForPosXmlDecoder;
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
class PayForPosHttpClient extends AbstractHttpClient
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
            new XmlEncoder('PayforRequest', 'UTF-8'),
            new PayForPosXmlDecoder(),
            $logger
        );
    }

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass, string $apiName): bool
    {
        return PayForPos::class === $gatewayClass && HttpClientInterface::API_NAME_PAYMENT_API === $apiName;
    }

    /**
     * @inheritDoc
     */
    public function supportsTx(string $txType, string $paymentModel, ?string $orderTxType = null): bool
    {
        return PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD !== $txType;
    }

    /**
     * @inheritDoc
     */
    protected function createRequest(string $url, EncodedData $content, string $txType, ?AbstractPosAccount $account = null): RequestInterface
    {
        $body = $this->streamFactory->createStream($content->getData());

        $request = $this->requestFactory->createRequest('POST', $url);

        return $request->withHeader('Content-Type', 'text/xml; charset=UTF-8')
            ->withBody($body);
    }
}
