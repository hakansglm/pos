<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Serializer\Decoder;

use Symfony\Component\Serializer\Exception\NotEncodableValueException;

class InterPosDecoder implements DecoderInterface
{
    /**
     * @inheritDoc
     */
    public function decode(string $data): array
    {
        // genelde ;; delimiter kullanilmis, ama bazen arasinda ;;; boyle delimiter de var.
        $resultValues = \preg_split('/(;;;|;;)/', $data);
        if (false === $resultValues) {
            throw new NotEncodableValueException();
        }

        $result = [];
        foreach ($resultValues as $val) {
            $parts = \explode('=', $val, 2);
            if (2 !== \count($parts)) {
                throw new NotEncodableValueException();
            }

            [$key, $value] = $parts;
            $result[$key]  = $value;
        }

        return $result;
    }
}
