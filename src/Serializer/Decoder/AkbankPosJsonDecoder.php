<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Serializer\Decoder;

use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\JsonEncoder as SymfonyJsonEncoder;
use Symfony\Component\Serializer\Serializer;

class AkbankPosJsonDecoder implements DecoderInterface
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

        $decodedData = $this->serializer->decode($data, SymfonyJsonEncoder::FORMAT);

        // API sends data for the history request compressed in data key.
        if (isset($decodedData['data'])) {
            $decompressedData    = $this->decompress($decodedData['data']);
            $decodedData['data'] = $this->serializer->decode($decompressedData, JsonEncoder::FORMAT);
        }

        return $decodedData;
    }

    /**
     * @param string $data
     *
     * @return string json string
     */
    private function decompress(string $data): string
    {
        $decodedData = \base64_decode($data);
        $gzipStream  = gzopen('data://application/octet-stream;base64,' . base64_encode($decodedData), 'rb');

        if (!$gzipStream) {
            return '';
        }

        $decompressedData = '';
        $i                = 0;
        while (!gzeof($gzipStream)) {
            ++$i;
            if ($i > 1000000) {
                throw new \RuntimeException('Invalid history data');
            }

            $decompressedData .= gzread($gzipStream, 1024);
        }

        gzclose($gzipStream);

        return $decompressedData;
    }
}
