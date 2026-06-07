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

class IyzicoPos3DFormHttpClient extends AbstractIyzicoPosHttpClient
{
    /**
     * @inheritDoc
     */
    public function supportsTx(string $txType, string $paymentModel, ?string $orderTxType = null): bool
    {
        return $txType === PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD;
    }

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass, string $apiName): bool
    {
        return IyzicoPos::class === $gatewayClass && HttpClientInterface::API_NAME_GATEWAY_3D_API === $apiName;
    }

    /**
     * @inheritDoc
     */
    public function getApiURL(?string $txType = null, ?string $paymentModel = null, ?string $orderTxType = null): string
    {
        if (null === $txType) {
            throw new \InvalidArgumentException('Transaction type is required to generate API URL');
        }

        if (PosInterface::MODEL_3D_HOST === $paymentModel) {
            if (PosInterface::TX_TYPE_STATUS === $txType) {
                return $this->baseApiUrl.'/payment/iyzipos/checkoutform/auth/ecom/detail';
            }

            return sprintf(
                '%s/payment/iyzipos/checkoutform/initialize/%s/ecom',
                $this->baseApiUrl,
                $this->requestValueMapper->mapTxType($txType)
            );
        }

        if (PosInterface::TX_TYPE_PAY_PRE_AUTH === $txType) {
            return $this->baseApiUrl.'/payment/3dsecure/initialize/'.$this->requestValueMapper->mapTxType($txType);
        }

        return $this->baseApiUrl.'/payment/3dsecure/initialize';
    }

    /**
     * @inheritDoc
     */
    protected function createRequest(string $url, EncodedData $content, string $txType, ?AbstractPosAccount $account = null): RequestInterface
    {
        if (!$account instanceof IyzicoPosAccount) {
            throw new \InvalidArgumentException(\sprintf('Expected %s, got %s.', IyzicoPosAccount::class, null !== $account ? \get_class($account) : 'null'));
        }

        $requestBody = $content->getData();

        $authStr = $this->createAuthorizationHeader($url, $requestBody, $account);

        $request = $this->requestFactory->createRequest('POST', $url);

        return $request
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', $authStr)
            ->withBody($this->streamFactory->createStream($requestBody));
    }
}
