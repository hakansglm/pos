<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Serializer;

use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

/**
 * @internal
 */
class EncodedData
{
    public const FORMAT_XML = XmlEncoder::FORMAT;

    public const FORMAT_JSON = JsonEncoder::FORMAT;

    public const FORMAT_FORM = 'form';

    /**
     * @param string         $data
     * @param self::FORMAT_* $format
     */
    public function __construct(private string $data, private string $format)
    {
    }

    /**
     * @return self::FORMAT_*
     */
    public function getFormat(): string
    {
        return $this->format;
    }

    public function getData(): string
    {
        return $this->data;
    }
}
