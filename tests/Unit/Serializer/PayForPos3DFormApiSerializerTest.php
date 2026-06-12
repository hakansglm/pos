<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Serializer;

use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\Gateways\PayForPos;
use Mews\Pos\Serializer\PayForPos3DFormApiSerializer;
use Mews\Pos\Serializer\SerializerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\Serializer\PayForPos3DFormApiSerializer
 */
class PayForPos3DFormApiSerializerTest extends TestCase
{
    private PayForPos3DFormApiSerializer $serializer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serializer = new PayForPos3DFormApiSerializer();
    }

    public function testSupports(): void
    {
        $this->assertTrue(PayForPos3DFormApiSerializer::supports(PayForPos::class, HttpClientInterface::API_NAME_GATEWAY_3D_API));
        $this->assertFalse(PayForPos3DFormApiSerializer::supports(AkbankPos::class, HttpClientInterface::API_NAME_GATEWAY_3D_API));
        $this->assertFalse(PayForPos3DFormApiSerializer::supports(PayForPos::class, HttpClientInterface::API_NAME_PAYMENT_API));
        $this->assertFalse(PayForPos3DFormApiSerializer::supports(PayForPos::class));
    }

    public function testEncode(): void
    {
        $data = ['foo' => 'bar', 'baz' => 'hello world'];

        $result = $this->serializer->encode($data);

        $this->assertSame('foo=bar&baz=hello+world', $result->getData());
        $this->assertSame(SerializerInterface::FORMAT_FORM, $result->getFormat());
    }

    public function testDecode(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not supported');

        $this->serializer->decode('<html>some-form</html>', 'any_tx_type');
    }
}
