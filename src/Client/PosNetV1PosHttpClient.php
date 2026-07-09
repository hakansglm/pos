<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\DataMapper\Request\ValueMapper\RequestValueMapperInterface;
use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Exception\UnsupportedTransactionTypeException;
use Mews\Pos\Gateway\PosNetV1Pos;
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
class PosNetV1PosHttpClient extends AbstractHttpClient
{
    /**
     * @param non-empty-string        $apiBaseUrl
     * @param ClientInterface         $psrClient
     * @param RequestFactoryInterface $requestFactory
     * @param StreamFactoryInterface  $streamFactory
     */
    public function __construct(
        string                              $apiBaseUrl,
        ClientInterface                     $psrClient,
        RequestFactoryInterface             $requestFactory,
        StreamFactoryInterface              $streamFactory,
        LoggerInterface                     $logger,
        private RequestValueMapperInterface $requestValueMapper
    ) {
        parent::__construct(
            $apiBaseUrl,
            $psrClient,
            $requestFactory,
            $streamFactory,
            new JsonEncoder(),
            new JsonDecoder(),
            $logger,
        );
    }

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass, string $apiName): bool
    {
        return PosNetV1Pos::class === $gatewayClass && HttpClientInterface::API_NAME_PAYMENT_API === $apiName;
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
            $this->getRequestURIByTransactionType($txType);
        } catch (UnsupportedTransactionTypeException) {
            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     *
     * @throws UnsupportedTransactionTypeException
     * @throws \InvalidArgumentException           when a transaction type is not provided
     */
    public function getApiURL(?string $txType = null, ?string $paymentModel = null, ?string $orderTxType = null): string
    {
        if (null !== $txType) {
            return $this->baseApiUrl.'/'.$this->getRequestURIByTransactionType($txType);
        }

        throw new \InvalidArgumentException('Transaction type is required to generate API URL');
    }


    /**
     * @inheritDoc
     */
    protected function createRequest(string $url, EncodedData $content, string $txType, ?AbstractPosAccount $account = null): RequestInterface
    {
        $body    = $this->streamFactory->createStream($content->getData());
        $request = $this->requestFactory->createRequest('POST', $url);

        return $request
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);
    }

    /**
     * @phpstan-param PosInterface::TX_TYPE_*|PosQueryInterface::QUERY_TYPE_* $txType
     *
     * @return string
     *
     * @throws UnsupportedTransactionTypeException
     */
    private function getRequestURIByTransactionType(string $txType): string
    {
        return $this->requestValueMapper->mapTxType($txType);
    }
}
