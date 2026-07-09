<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Serializer\Encoder;

use PHPUnit\Framework\Attributes\DataProvider;
use Mews\Pos\Serializer\EncodedData;
use Mews\Pos\Serializer\Encoder\JsonEncoder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonEncoder::class)]
class JsonEncoderTest extends TestCase
{
    private JsonEncoder $encoder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->encoder = new JsonEncoder();
    }

    #[DataProvider('encodeDataProvider')]
    public function testEncode(array $data, string $expectedData): void
    {
        $result = $this->encoder->encode($data);

        $this->assertSame($expectedData, $result->getData());
        $this->assertSame(EncodedData::FORMAT_JSON, $result->getFormat());
    }

    public static function encodeDataProvider(): array
    {
        return [
            'simple_flat_array' => [
                'input'         => ['abc' => 1, "abc2" => 2.0],
                'expected_data' => '{"abc":1,"abc2":2.0}',
            ],
            'nested_array' => [
                'input'         => ['order' => ['id' => 'order222', 'amount' => '100.25']],
                'expected_data' => '{"order":{"id":"order222","amount":"100.25"}}',
            ],
            'cancel_request' => [
                'input'         => ['key' => 'value'],
                'expected_data' => '{"key":"value"}',
            ],
            'string_values' => [
                'input'         => ['merchantId' => '000111', 'txType' => 'Sale'],
                'expected_data' => '{"merchantId":"000111","txType":"Sale"}',
            ],
        ];
    }
}
