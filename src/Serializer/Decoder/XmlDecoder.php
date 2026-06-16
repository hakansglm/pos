<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Serializer\Decoder;

use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\Serializer;

class XmlDecoder implements DecoderInterface
{
    private Serializer $serializer;

    public function __construct()
    {
        $this->serializer = new Serializer([], [new XmlEncoder()]);
    }

    /**
     * @inheritDoc
     */
    public function decode(string $data): array
    {
        try {
            return $this->serializer->decode($data, XmlEncoder::FORMAT);
        } catch (NotEncodableValueException $e) {
            if ($this->isHtml($data)) {
                throw new \RuntimeException($data, $e->getCode(), $e);
            }

            throw $e;
        }
    }

    private function isHtml(string $string): bool
    {
        if ('' === \trim($string)) {
            return false;
        }

        // Suppress errors for invalid HTML
        $previousLibxmlState = \libxml_use_internal_errors(true);

        // Create a new DOMDocument
        $dom = new \DOMDocument();

        // Attempt to load the string as HTML
        $isValidHTML = $dom->loadHTML($string, LIBXML_NOERROR | LIBXML_NOWARNING);

        // Clear any libxml errors
        \libxml_clear_errors();

        // Restore the previous libxml error handling state
        \libxml_use_internal_errors($previousLibxmlState);

        // Check if the string has recognizable HTML elements
        return $isValidHTML && $dom->getElementsByTagName('html')->length > 0;
    }
}
