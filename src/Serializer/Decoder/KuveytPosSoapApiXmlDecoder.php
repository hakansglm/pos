<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Serializer\Decoder;

use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Serializer;

class KuveytPosSoapApiXmlDecoder implements DecoderInterface
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
        $decodedData = $this->serializer->decode($data, XmlEncoder::FORMAT);

        return $decodedData['s:Body'];
    }
}
