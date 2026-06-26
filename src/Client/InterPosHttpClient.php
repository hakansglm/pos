<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Gateway\InterPos;
use Mews\Pos\Serializer\Decoder\InterPosDecoder;
use Mews\Pos\Serializer\EncodedData;
use Mews\Pos\Serializer\Encoder\FormEncoder;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
class InterPosHttpClient extends AbstractHttpClient
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
            new InterPosDecoder(),
            $logger
        );
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
    public static function supports(string $gatewayClass, string $apiName): bool
    {
        return InterPos::class === $gatewayClass && HttpClientInterface::API_NAME_PAYMENT_API === $apiName;
    }

    /**
     * @inheritDoc
     */
    protected function createRequest(string $url, EncodedData $content, string $txType, ?AbstractPosAccount $account = null): RequestInterface
    {
        $body = $this->streamFactory->createStream($content->getData());

        $request = $this->requestFactory->createRequest('POST', $url);

        return $request->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($body);
    }
}
