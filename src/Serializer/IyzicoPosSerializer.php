<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Serializer;

use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Gateways\IyzicoPos;

class IyzicoPosSerializer implements SerializerInterface
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass, ?string $apiName = null): bool
    {
        return IyzicoPos::class === $gatewayClass
            && (
                null === $apiName
                || HttpClientInterface::API_NAME_PAYMENT_API === $apiName
                || HttpClientInterface::API_NAME_GATEWAY_3D_API === $apiName
            );
    }

    /**
     * @inheritDoc
     */
    public function encode(array $data, ?string $txType = null): EncodedData
    {
        return new EncodedData(
            \json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            self::FORMAT_JSON
        );
    }

    /**
     * @inheritDoc
     */
    public function decode(string $data, ?string $txType = null): array
    {
        if ('' === $data) {
            return [];
        }

        return \json_decode($data, true, 512, JSON_THROW_ON_ERROR);
    }
}
