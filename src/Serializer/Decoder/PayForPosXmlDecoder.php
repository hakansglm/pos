<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Serializer\Decoder;

use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\Serializer;

class PayForPosXmlDecoder implements DecoderInterface
{
    private Serializer $serializer;

    public function __construct()
    {
        $this->serializer = new Serializer([], [new XmlEncoder()]);
    }

    /**
     * @inheritDoc
     */
    public function decode(string $data): array
    {
        /**
         * Finansbank XML responses sometimes contain redundant whitespace:
         * <MbrId>5</MbrId>\r\n
         * <MD>\r\n
         * </MD>\r\n
         * which causes non-empty value for response properties.
         */
        $response = \preg_replace('/\r\n\s*/', '', $data);
        if (null === $response) {
            throw new NotEncodableValueException();
        }

        return $this->serializer->decode($response, XmlEncoder::FORMAT);
    }
}
