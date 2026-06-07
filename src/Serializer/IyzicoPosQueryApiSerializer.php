<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Serializer;

use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Gateways\IyzicoPos;

class IyzicoPosQueryApiSerializer implements SerializerInterface
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass, ?string $apiName = null): bool
    {
        return IyzicoPos::class === $gatewayClass && HttpClientInterface::API_NAME_QUERY_API === $apiName;
    }

    /**
     * @inheritDoc
     */
    public function encode(array $data, ?string $txType = null): EncodedData
    {
        return new EncodedData(
            \http_build_query($data),
            self::FORMAT_FORM
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
