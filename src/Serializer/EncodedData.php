<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Serializer;

use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

class EncodedData
{
    public const FORMAT_XML = XmlEncoder::FORMAT;

    public const FORMAT_JSON = JsonEncoder::FORMAT;

    public const FORMAT_FORM = 'form';

    /**
     * @var self::FORMAT_*
     */
    private string $format;

    /**
     * @var string encoded Data
     */
    private string $data;

    /**
     * @param string         $data
     * @param self::FORMAT_* $format
     */
    public function __construct(string $data, string $format)
    {
        $this->data   = $data;
        $this->format = $format;
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
