<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Serializer\Encoder;

use Mews\Pos\Serializer\EncodedData;
use Mews\Pos\Serializer\Encoder\FormEncoder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FormEncoder::class)]
class FormEncoderTest extends TestCase
{
    private FormEncoder $encoder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->encoder = new FormEncoder();
    }

    /**
     * @dataProvider encodeDataProvider
     */
    public function testEncode(array $data, string $expectedData): void
    {
        $result = $this->encoder->encode($data);

        $this->assertSame($expectedData, $result->getData());
        $this->assertSame(EncodedData::FORMAT_FORM, $result->getFormat());
    }

    public static function encodeDataProvider(): array
    {
        return [
            'simple_key_value' => [
                'input'         => ['abc' => '1', 'abc2' => 1.0, 'sa' => 'aa'],
                'expected_data' => 'abc=1&abc2=1&sa=aa',
            ],
            'interpos_style' => [
                'input'         => [
                    'UserCode' => 'QNB_API_KULLANICI_3DPAY',
                    'OrderId'  => 'order222',
                    'Amount'   => '100.25',
                ],
                'expected_data' => 'UserCode=QNB_API_KULLANICI_3DPAY&OrderId=order222&Amount=100.25',
            ],
            'cancel_request' => [
                'input'         => ['key' => 'value'],
                'expected_data' => 'key=value',
            ],
            'values_with_spaces_are_url_encoded' => [
                'input'         => ['key' => 'hello world'],
                'expected_data' => 'key=hello+world',
            ],
        ];
    }
}
