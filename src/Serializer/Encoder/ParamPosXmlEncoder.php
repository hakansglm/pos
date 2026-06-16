<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Serializer\Encoder;

use Mews\Pos\Serializer\EncodedData;
use Symfony\Component\Serializer\Encoder\XmlEncoder as SymfonyXmlEncoder;
use Symfony\Component\Serializer\Serializer;

class ParamPosXmlEncoder implements EncoderInterface
{
    private Serializer $serializer;

    public function __construct()
    {
        $this->serializer = new Serializer([], [new SymfonyXmlEncoder([
            SymfonyXmlEncoder::ROOT_NODE_NAME => 'soap:Envelope',
            SymfonyXmlEncoder::ENCODING       => 'utf-8',
        ])]);
    }

    /**
     * @inheritDoc
     */
    public function encode(array $data): EncodedData
    {
        $data['@xmlns:xsi']  = 'http://www.w3.org/2001/XMLSchema-instance';
        $data['@xmlns:xsd']  = 'http://www.w3.org/2001/XMLSchema';
        $data['@xmlns:soap'] = 'http://schemas.xmlsoap.org/soap/envelope/';

        return new EncodedData(
            $this->serializer->encode($data, SymfonyXmlEncoder::FORMAT),
            EncodedData::FORMAT_XML
        );
    }
}
