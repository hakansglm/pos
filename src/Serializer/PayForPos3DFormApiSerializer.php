<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Serializer;

use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Gateways\PayForPos;

class PayForPos3DFormApiSerializer implements SerializerInterface
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass, ?string $apiName = null): bool
    {
        return PayForPos::class === $gatewayClass && HttpClientInterface::API_NAME_GATEWAY_3D_API === $apiName;
    }

    /**
     * @inheritDoc
     */
    public function encode(array $data, ?string $txType = null): EncodedData
    {
        $format = self::FORMAT_FORM;

        return new EncodedData(
            \http_build_query($data),
            $format
        );
    }

    /**
     * API returns html string, so we don't need to decode anything.
     *
     * @inheritDoc
     */
    public function decode(string $data, string $txType): array
    {
        throw new \RuntimeException('Not supported');
    }
}
