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

class IyzicoPosHttpClient extends AbstractIyzicoPosHttpClient
{
    /**
     * @inheritDoc
     */
    public function supportsTx(string $txType, string $paymentModel, ?string $orderTxType = null): bool
    {
        return !\in_array($txType, [
            PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD,
            PosInterface::TX_TYPE_ORDER_HISTORY,
            PosInterface::TX_TYPE_HISTORY,
        ], true);
    }

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass, string $apiName): bool
    {
        return IyzicoPos::class === $gatewayClass && HttpClientInterface::API_NAME_PAYMENT_API === $apiName;
    }

    /**
     * @inheritDoc
     */
    public function getApiURL(?string $txType = null, ?string $paymentModel = null, ?string $orderTxType = null): string
    {
        return $this->baseApiUrl.$this->getPathByTxType($txType, $paymentModel);
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

    /**
     * @param PosInterface::TX_TYPE_*|null $txType
     * @param PosInterface::MODEL_*|null   $paymentModel
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    private function getPathByTxType(?string $txType, ?string $paymentModel): string
    {
        if (null === $txType) {
            throw new \InvalidArgumentException('Transaction type is required to generate API URL');
        }

        if (PosInterface::TX_TYPE_REFUND === $txType || PosInterface::TX_TYPE_REFUND_PARTIAL === $txType) {
            return '/v2/payment/'.$this->requestValueMapper->mapTxType($txType);
        }
        if (PosInterface::MODEL_3D_SECURE === $paymentModel) {
            return '/payment/v2/3dsecure/auth';
        }

        return '/payment/'.$this->requestValueMapper->mapTxType($txType);
    }
}
