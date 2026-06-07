<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Serializer;

use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\Gateways\IyzicoPos;
use Mews\Pos\Serializer\IyzicoPosQueryApiSerializer;
use Mews\Pos\Serializer\SerializerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\Serializer\IyzicoPosQueryApiSerializer
 */
class IyzicoPosQueryApiSerializerTest extends TestCase
{
    private IyzicoPosQueryApiSerializer $serializer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serializer = new IyzicoPosQueryApiSerializer();
    }

    public function testSupports(): void
    {
        $this->assertTrue($this->serializer::supports(IyzicoPos::class, HttpClientInterface::API_NAME_QUERY_API));
        $this->assertFalse($this->serializer::supports(IyzicoPos::class, HttpClientInterface::API_NAME_PAYMENT_API));
        $this->assertFalse($this->serializer::supports(IyzicoPos::class));
        $this->assertFalse($this->serializer::supports(AkbankPos::class, HttpClientInterface::API_NAME_QUERY_API));
    }

    /**
     * @dataProvider encodeDataProvider
     */
    public function testEncode(array $data, string $expectedFormData): void
    {
        $result = $this->serializer->encode($data);

        $this->assertSame($expectedFormData, $result->getData());
        $this->assertSame(SerializerInterface::FORMAT_FORM, $result->getFormat());
    }

    /**
     * @dataProvider decodeDataProvider
     */
    public function testDecode(string $input, array $expected): void
    {
        $actual = $this->serializer->decode($input);

        $this->assertSame($expected, $actual);
    }

    public function testDecodeEmptyStringReturnsEmptyArray(): void
    {
        $this->assertSame([], $this->serializer->decode(''));
    }

    public static function encodeDataProvider(): array
    {
        return [
            'query_params' => [
                'data'             => ['locale' => 'tr', 'page' => 1],
                'expectedFormData' => 'locale=tr&page=1',
            ],
            'date_param' => [
                'data'             => ['transactionDate' => '2024-01-15', 'page' => 2],
                'expectedFormData' => 'transactionDate=2024-01-15&page=2',
            ],
        ];
    }

    public static function decodeDataProvider(): array
    {
        return [
            'json_response' => [
                'input'    => '{"status":"success","transactions":[{"id":"t1"}]}',
                'expected' => ['status' => 'success', 'transactions' => [['id' => 't1']]],
            ],
        ];
    }
}
