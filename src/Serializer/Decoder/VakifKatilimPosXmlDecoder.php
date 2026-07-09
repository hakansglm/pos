<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Serializer\Decoder;

use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Serializer;

/**
 * @internal
 */
class VakifKatilimPosXmlDecoder implements DecoderInterface
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
        // workaround for Vakif Katilim POS XML responses, mentioned in their documentation
        $data = \str_replace("&#x0;", '', $data);
        $data = \str_replace(' encoding="utf-16"', '', $data);

        return $this->serializer->decode($data, XmlEncoder::FORMAT);
    }
}
