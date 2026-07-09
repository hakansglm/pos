<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Serializer\Encoder;

use Mews\Pos\Serializer\EncodedData;
use Mews\Pos\Serializer\XmlPrefixNormalizer;
use Symfony\Component\Serializer\Encoder\XmlEncoder as SymfonyXmlEncoder;
use Symfony\Component\Serializer\Serializer;

/**
 * @internal
 */
class KuveytPosSoapApiXmlEncoder implements EncoderInterface
{
    private Serializer $serializer;

    public function __construct()
    {
        $this->serializer = new Serializer([new XmlPrefixNormalizer()], [new SymfonyXmlEncoder([
            SymfonyXmlEncoder::ROOT_NODE_NAME             => 'soapenv:Envelope',
            SymfonyXmlEncoder::ENCODER_IGNORED_NODE_TYPES => [\XML_PI_NODE],
        ])]);
    }

    /**
     * @inheritDoc
     */
    public function encode(array $data): EncodedData
    {
        /** @var array<string, mixed> $data */
        $data = $this->serializer->normalize($data, SymfonyXmlEncoder::FORMAT, ['xml_prefix' => 'ser']);

        $serializeData                   = [];
        $serializeData['soapenv:Body']   = $data;
        $serializeData['@xmlns:soapenv'] = 'http://schemas.xmlsoap.org/soap/envelope/';
        $serializeData['@xmlns:ser']     = 'http://boa.net/BOA.Integration.VirtualPos/Service';

        return new EncodedData(
            $this->serializer->serialize($serializeData, SymfonyXmlEncoder::FORMAT),
            EncodedData::FORMAT_XML
        );
    }
}
