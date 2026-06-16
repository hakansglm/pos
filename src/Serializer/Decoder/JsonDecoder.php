<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Serializer\Decoder;

use Symfony\Component\Serializer\Encoder\JsonEncoder as SymfonyJsonEncoder;
use Symfony\Component\Serializer\Serializer;

class JsonDecoder implements DecoderInterface
{
    private Serializer $serializer;

    public function __construct()
    {
        $this->serializer = new Serializer([], [new SymfonyJsonEncoder()]);
    }

    /**
     * @inheritDoc
     */
    public function decode(string $data): array
    {
        if ('' === $data) {
            return [];
        }

        return $this->serializer->decode($data, SymfonyJsonEncoder::FORMAT);
    }
}
