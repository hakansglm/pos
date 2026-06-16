<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Serializer\Encoder;

use Mews\Pos\Serializer\EncodedData;
use Symfony\Component\Serializer\Encoder\JsonEncoder as SymfonyJsonEncoder;
use Symfony\Component\Serializer\Serializer;

class JsonEncoder implements EncoderInterface
{
    private Serializer $serializer;

    public function __construct()
    {
        $this->serializer = new Serializer([], [new SymfonyJsonEncoder()]);
    }

    /**
     * @inheritDoc
     */
    public function encode(array $data): EncodedData
    {
        return new EncodedData(
            $this->serializer->encode($data, SymfonyJsonEncoder::FORMAT),
            EncodedData::FORMAT_JSON
        );
    }
}
