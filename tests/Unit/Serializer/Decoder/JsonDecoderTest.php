<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Serializer\Decoder;

use Mews\Pos\Serializer\Decoder\JsonDecoder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonDecoder::class)]
class JsonDecoderTest extends TestCase
{
    private JsonDecoder $decoder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->decoder = new JsonDecoder();
    }

    /**
     * @dataProvider decodeDataProvider
     */
    public function testDecode(string $data, array $expected): void
    {
        $actual = $this->decoder->decode($data);

        $this->assertSame($expected, $actual);
    }

    public static function decodeDataProvider(): array
    {
        return [
            'simple_json' => [
                'input'    => '{"abc": 1, "dfe": 1.0}',
                'expected' => ['abc' => 1, 'dfe' => 1.0],
            ],
            'nested_json' => [
                'input'    => '{"order":{"id":"order222","amount":"100.25"}}',
                'expected' => ['order' => ['id' => 'order222', 'amount' => '100.25']],
            ],
            'response_with_null_value' => [
                'input'    => '{"code":"00","message":null}',
                'expected' => ['code' => '00', 'message' => null],
            ],
            'with_empty_value' => [
                'input'    => '',
                'expected' => [],
            ],
        ];
    }
}
