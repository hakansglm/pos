<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Gateways\PosNetPos;
use Mews\Pos\Serializer\Decoder\XmlDecoder;
use Mews\Pos\Serializer\EncodedData;
use Mews\Pos\Serializer\Encoder\XmlEncoder;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

class PosNetPosHttpClient extends AbstractHttpClient
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
            new XmlEncoder('posnetRequest', 'ISO-8859-9'),
            new XmlDecoder(),
            $logger
        );
    }

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass, string $apiName): bool
    {
        return PosNetPos::class === $gatewayClass && HttpClientInterface::API_NAME_PAYMENT_API === $apiName;
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
    public function request(
        string              $txType,
        string              $paymentModel,
        array               $requestData,
        array               $order,
        ?string             $url = null,
        ?AbstractPosAccount $account = null,
        ?string             $orderTxType = null
    ): array {
        $content = $this->encoder->encode($requestData);
        $content = new EncodedData(
            \sprintf('xmldata=%s', $content->getData()),
            EncodedData::FORMAT_FORM
        );

        return $this->doRequest(
            $txType,
            $paymentModel,
            $content,
            $order,
            $url,
            $account,
            true,
            $orderTxType
        );
    }

    /**
     * @inheritDoc
     */
    protected function createRequest(string $url, EncodedData $content, string $txType, ?AbstractPosAccount $account = null): RequestInterface
    {
        $body    = $this->streamFactory->createStream($content->getData());
        $request = $this->requestFactory->createRequest('POST', $url);

        return $request->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($body);
    }
}
