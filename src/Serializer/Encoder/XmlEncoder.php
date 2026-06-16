<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Serializer\Encoder;

use Mews\Pos\Serializer\EncodedData;
use Symfony\Component\Serializer\Encoder\XmlEncoder as SymfonyXmlEncoder;
use Symfony\Component\Serializer\Serializer;

class XmlEncoder implements EncoderInterface
{
    private Serializer $serializer;

    /**
     * @param string               $rootNodeName
     * @param string               $encoding
     * @param array<string, mixed> $options      additional XmlEncoder context options
     */
    public function __construct(string $rootNodeName, string $encoding, array $options = [])
    {
        $this->serializer = new Serializer([], [new SymfonyXmlEncoder(array_merge([
            SymfonyXmlEncoder::ROOT_NODE_NAME => $rootNodeName,
            SymfonyXmlEncoder::ENCODING       => $encoding,
        ], $options))]);
    }

    /**
     * @inheritDoc
     */
    public function encode(array $data): EncodedData
    {
        return new EncodedData(
            $this->serializer->encode($data, SymfonyXmlEncoder::FORMAT),
            EncodedData::FORMAT_XML
        );
    }
}
