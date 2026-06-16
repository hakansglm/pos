<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Serializer\Encoder;

use Mews\Pos\Serializer\EncodedData;

class FormEncoder implements EncoderInterface
{
    /**
     * @inheritDoc
     */
    public function encode(array $data): EncodedData
    {
        return new EncodedData(\http_build_query($data), EncodedData::FORMAT_FORM);
    }
}
