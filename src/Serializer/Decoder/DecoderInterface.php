<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Serializer\Decoder;

use Symfony\Component\Serializer\Exception\NotEncodableValueException;

interface DecoderInterface
{
    /**
     * @param string $data response received from the bank
     *
     * @return array<string, mixed>
     *
     * @throws \RuntimeException
     * @throws NotEncodableValueException
     */
    public function decode(string $data): array;
}
