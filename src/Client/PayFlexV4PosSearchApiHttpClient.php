<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\Gateway\PayFlexV4Pos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\Decoder\XmlDecoder;
use Mews\Pos\Serializer\Encoder\XmlEncoder;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
class PayFlexV4PosSearchApiHttpClient extends PayFlexV4PosHttpClient
{
    public function __construct(
        string                  $baseApiUrl,
        ClientInterface         $psrClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface  $streamFactory,
        LoggerInterface         $logger
    ) {
        AbstractHttpClient::__construct(
            $baseApiUrl,
            $psrClient,
            $requestFactory,
            $streamFactory,
            new XmlEncoder('SearchRequest', 'UTF-8'),
            new XmlDecoder(),
            $logger
        );
    }

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass, string $apiName): bool
    {
        return PayFlexV4Pos::class === $gatewayClass && HttpClientInterface::API_NAME_QUERY_API === $apiName;
    }

    /**
     * @inheritDoc
     */
    public function supportsTx(string $txType, string $paymentModel, ?string $orderTxType = null): bool
    {
        return PosInterface::TX_TYPE_STATUS === $txType;
    }
}
