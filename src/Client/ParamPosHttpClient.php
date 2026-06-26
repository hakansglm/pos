<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Gateway\Param3DHostPos;
use Mews\Pos\Gateway\ParamPos;
use Mews\Pos\Serializer\Decoder\ParamPosXmlDecoder;
use Mews\Pos\Serializer\EncodedData;
use Mews\Pos\Serializer\Encoder\ParamPosXmlEncoder;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
class ParamPosHttpClient extends AbstractHttpClient
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
            new ParamPosXmlEncoder(),
            new ParamPosXmlDecoder(),
            $logger
        );
    }

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass, string $apiName): bool
    {
        return (ParamPos::class === $gatewayClass
            || Param3DHostPos::class === $gatewayClass)
            && HttpClientInterface::API_NAME_PAYMENT_API === $apiName;
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
    protected function createRequest(string $url, EncodedData $content, string $txType, ?AbstractPosAccount $account = null): RequestInterface
    {
        $body    = $this->streamFactory->createStream($content->getData());
        $request = $this->requestFactory->createRequest('POST', $url);

        return $request->withHeader('Content-Type', 'text/xml')
            ->withBody($body);
    }

    /**
     * @inheritDoc
     */
    protected function checkFailResponseData(string $txType, ResponseInterface $response, array $responseData, array $order): void
    {
        if (isset($responseData['soap:Fault'])) {
            $this->logger->error('soap error response', [
                'status_code' => $response->getStatusCode(),
                'response'    => $responseData,
                'order'       => $order,
                'tx_type'     => $txType,
            ]);

            throw new \RuntimeException($responseData['soap:Fault']['faultstring'] ?? 'Bankaya istek başarısız!');
        }
    }
}
