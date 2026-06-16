<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Serializer\Decoder;

use Mews\Pos\Serializer\Decoder\AkbankPosJsonDecoder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

/**
 * @covers \Mews\Pos\Serializer\Decoder\AkbankPosJsonDecoder
 */
class AkbankPosJsonDecoderTest extends TestCase
{
    private AkbankPosJsonDecoder $decoder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->decoder = new AkbankPosJsonDecoder();
    }

    /**
     * @dataProvider decodeDataProvider
     */
    public function testDecode(string $data, array $expected): void
    {
        $actual = $this->decoder->decode($data);

        $this->assertSame($expected, $actual);
    }

    public function testDecodeWithInvalidGzipHistoryDataThrowsException(): void
    {
        $input = '{"data": "INVALID_BASE64_!!!!"}';

        $this->expectException(NotEncodableValueException::class);
        $this->decoder->decode($input);
    }

    public function testDecodeHistory(): void
    {
        $input = file_get_contents(__DIR__ . '/../../test_data/akbankpos/history/daily_history_raw.json');

        $actual = $this->decoder->decode($input);

        $this->assertSame(6, count($actual));
        $this->assertArrayHasKey('data', $actual);
        $this->assertIsArray($actual['data']);
        $this->assertCount(3, $actual['data']);
        $this->assertCount(525, $actual['data']['txnDetailList']);
    }

    public function testDecodeHistoryWithDecompressedDataThatIsNotValidJson(): void
    {
        $input = \json_encode([
            'data'            => 'H4sIAAAAAAAAAIWSTW7bMBCFr2JwWZG/ylq5zobo24S2G7RIMiCkka1UP0EFIWmMHyV5gy5Q3yvUrKdOICBciW8mW/43lBb5J/qK+thXVSAYsQIE5hwzNiaypiIWJAJExqNkQdXFbUtUbxFFbh0Y2u/sjnMswPGKCER1YxQphWJtKKCK0WUFkqxM/6N4f9jduPBHHhblIui9Si+3/bKrMl6q4EVYa6D9rGpWziq329XmIRzVvkKbWt/9sXP0/3f6XK+mIfqpmn98iM7UOf6Oxkmjm6up3ejLzdX30Y/hoPGF5fHMONrKmIWxUxNNO3DNy4Dd0jNSZRGGcVSGI0FUzk2BnJMc0NtzkmS5/22bec3p5yMadZrztUovIcUTFLFhZRjVNn2F2Qz67LrrkrAhXYpdcTMp+FQImUAE+vTzalBGj1GrbdhFifhC7wvoYLaD/ZMkqZScoojQyUWJElwkuscB2dCgsoM5/qQe+Wt79p+qXb/bN3rS/n60huvmq4OT0XJhITxaecc1OmfQxYjzLu0sAmEvwmtl3eBK+rgqSx7I7PjhA/iqfsW9s9FHYA0TZbwOySfHm8Mtz1e0J4u9TmY9gsumxbe5N3D7h/PW4rgDgMAAA==',
            'requestId'       => 'VPS00020599122225999999999920240425095907000052',
            'terminal'        => [
                'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                'merchantSafeId' => '2023090417500272654BD9A49CF07574',
            ],
            'responseMessage' => 'SUCCESSFUL',
            'txnDateTime'     => '2024-04-25T09:59:06.582',
            'responseCode'    => 'VPS-0000',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->decoder->decode($input);
    }

    public static function decodeDataProvider(): array
    {
        return [
            'standard_payment_response' => [
                'input'    => '{"abc": 1}',
                'expected' => ['abc' => 1],
            ],
            'history_without_data_key' => [
                'input'    => '{"responseCode": "VPS-0001", "responseMessage": "Error"}',
                'expected' => ['responseCode' => 'VPS-0001', 'responseMessage' => 'Error'],
            ],
            'empty_response' => [
                'input'    => '',
                'expected' => [],
            ],
        ];
    }
}
