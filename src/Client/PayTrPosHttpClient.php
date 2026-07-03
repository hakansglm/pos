<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\Gateway\PayTrPos;
use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\PosInterface;
use Mews\Pos\PosQuery\PosQueryInterface;
use Mews\Pos\Serializer\Decoder\JsonDecoder;
use Mews\Pos\Serializer\EncodedData;
use Mews\Pos\Serializer\Encoder\FormEncoder;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * PayTR HTTP client.
 *
 * @internal
 */
class PayTrPosHttpClient extends AbstractHttpClient
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
            new JsonDecoder(),
            $logger
        );
    }

    /** @inheritDoc */
    public static function supports(string $gatewayClass, string $apiName): bool
    {
        return PayTrPos::class === $gatewayClass && HttpClientInterface::API_NAME_PAYMENT_API === $apiName;
    }

    /** @inheritDoc */
    public function supportsTx(string $txType, string $paymentModel, ?string $orderTxType = null): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getApiURL(?string $txType = null, ?string $paymentModel = null, ?string $orderTxType = null): string
    {
        if (null === $txType) {
            throw new \InvalidArgumentException('Transaction type is required to generate PayTR API URL');
        }

        return match (true) {
            PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD === $txType
                => $this->baseApiUrl.'/odeme/api/get-token',

            \in_array($txType, [PosInterface::TX_TYPE_REFUND, PosInterface::TX_TYPE_REFUND_PARTIAL], true)
                => $this->baseApiUrl.'/odeme/iade',

            PosInterface::TX_TYPE_STATUS === $txType
            => $this->baseApiUrl.'/odeme/durum-sorgu',

            \in_array($txType, [PosInterface::TX_TYPE_PAY_AUTH, PosQueryInterface::QUERY_TYPE_CUSTOM_QUERY], true)
                => $this->baseApiUrl.'/odeme',

            PosQueryInterface::QUERY_TYPE_HISTORY === $txType
                => $this->baseApiUrl.'/rapor/islem-dokumu',

            PosQueryInterface::QUERY_TYPE_INSTALLMENT_RATES === $txType
                => $this->baseApiUrl.'/odeme/taksit-oranlari',

            PosQueryInterface::QUERY_TYPE_BIN_LIST === $txType
                => $this->baseApiUrl.'/odeme/api/bin-detail',

            default => throw new \Mews\Pos\Exception\UnsupportedTransactionTypeException(),
        };
    }

    /** @inheritDoc */
    protected function createRequest(string $url, EncodedData $content, string $txType, ?AbstractPosAccount $account = null): RequestInterface
    {
        $body    = $this->streamFactory->createStream($content->getData());
        $request = $this->requestFactory->createRequest('POST', $url);

        return $request
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($body);
    }
}
