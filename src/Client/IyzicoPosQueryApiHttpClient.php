<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\IyzicoPosAccount;
use Mews\Pos\Gateways\IyzicoPos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\EncodedData;
use Psr\Http\Message\RequestInterface;

class IyzicoPosQueryApiHttpClient extends AbstractIyzicoPosHttpClient
{
    /**
     * @inheritDoc
     */
    public function supportsTx(string $txType, string $paymentModel, ?string $orderTxType = null): bool
    {
        return $txType === PosInterface::TX_TYPE_ORDER_HISTORY || $txType === PosInterface::TX_TYPE_HISTORY;
    }

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass, string $apiName): bool
    {
        return IyzicoPos::class === $gatewayClass && HttpClientInterface::API_NAME_QUERY_API === $apiName;
    }

    /**
     * @inheritDoc
     */
    public function getApiURL(?string $txType = null, ?string $paymentModel = null, ?string $orderTxType = null): string
    {
        if (null === $txType) {
            throw new \InvalidArgumentException('Transaction type is required to generate API URL');
        }

        return $this->baseApiUrl.'/'.$this->requestValueMapper->mapTxType($txType);
    }

    /**
     * Sends a GET request with request data attached as URL query parameters.
     * The authorization hash is calculated with an empty body, as required by
     * the iyzico reporting API for GET endpoints.
     *
     * @inheritDoc
     */
    protected function createRequest(string $url, EncodedData $content, string $txType, ?AbstractPosAccount $account = null): RequestInterface
    {
        if (!$account instanceof IyzicoPosAccount) {
            throw new \InvalidArgumentException(\sprintf('Expected %s, got %s.', IyzicoPosAccount::class, null !== $account ? \get_class($account) : 'null'));
        }

        $authStr = $this->createAuthorizationHeader($url, '', $account);
        $url     .= '?'.$content->getData();

        return $this->requestFactory->createRequest('GET', $url)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', $authStr);
    }
}
