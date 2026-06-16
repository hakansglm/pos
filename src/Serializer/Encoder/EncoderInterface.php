<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Serializer\Encoder;

use Mews\Pos\Serializer\EncodedData;

interface EncoderInterface
{
    /**
     * @param array<string, mixed> $data
     *
     * @return EncodedData
     */
    public function encode(array $data): EncodedData;
}
