<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Serializer;

use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\Gateways\IyzicoPos;
use Mews\Pos\Serializer\IyzicoPosSerializer;
use Mews\Pos\Serializer\SerializerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\Serializer\IyzicoPosSerializer
 */
class IyzicoPosSerializerTest extends TestCase
{
    private IyzicoPosSerializer $serializer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serializer = new IyzicoPosSerializer();
    }

    public function testSupports(): void
    {
        $this->assertTrue($this->serializer::supports(IyzicoPos::class));
        $this->assertTrue($this->serializer::supports(IyzicoPos::class, HttpClientInterface::API_NAME_PAYMENT_API));
        $this->assertTrue($this->serializer::supports(IyzicoPos::class, HttpClientInterface::API_NAME_GATEWAY_3D_API));
        $this->assertFalse($this->serializer::supports(IyzicoPos::class, HttpClientInterface::API_NAME_QUERY_API));
        $this->assertFalse($this->serializer::supports(AkbankPos::class));
    }

    /**
     * @dataProvider encodeDataProvider
     */
    public function testEncode(array $data, string $expectedJson): void
    {
        $result = $this->serializer->encode($data);

        $this->assertSame($expectedJson, $result->getData());
        $this->assertSame(SerializerInterface::FORMAT_JSON, $result->getFormat());
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
            'simple_data' => [
                'data'         => ['locale' => 'tr', 'conversationId' => 'order-1'],
                'expectedJson' => '{"locale":"tr","conversationId":"order-1"}',
            ],
            'unicode_preserved' => [
                'data'         => ['name' => 'Türkçe'],
                'expectedJson' => '{"name":"Türkçe"}',
            ],
        ];
    }

    public static function decodeDataProvider(): array
    {
        return [
            'payment_response' => [
                'input'    => '{"status":"success","paymentId":"pay-001","conversationId":"order-1"}',
                'expected' => [
                    'status'         => 'success',
                    'paymentId'      => 'pay-001',
                    'conversationId' => 'order-1',
                ],
            ],
        ];
    }
}
