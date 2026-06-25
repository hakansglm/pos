<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Serializer\Decoder;

use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Generator;
use Mews\Pos\Serializer\Decoder\XmlDecoder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

#[CoversClass(XmlDecoder::class)]
class XmlDecoderTest extends TestCase
{
    private XmlDecoder $decoder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->decoder = new XmlDecoder();
    }

    #[DataProvider('decodeDataProvider')]
    public function testDecode(string $data, array $expected): void
    {
        $actual = $this->decoder->decode($data);

        $this->assertSame($expected, $actual);
    }

    #[DataProvider('decodeBadDataProvider')]
    public function testDecodeEmptyStringThrowsNotEncodableValueException(string $input, string $expectedExceptionClass): void
    {
        $this->expectException($expectedExceptionClass);
        $this->decoder->decode($input);
    }

    public static function decodeBadDataProvider(): array
    {
        return [
            'empty_string' => [
                // Empty string is explicitly excluded from HTML detection, so NotEncodableValueException propagates
                'input'                  => '',
                'expectedExceptionClass' => NotEncodableValueException::class,
            ],
            'html_response' => [
                'input'                  => <<<HTML
<!DOCTYPE html>
<html>
    <head><title>Server Error</title></head>
    <body>
        <h1>Server Error in Application.</h1>
    </body>
</html>
HTML,
                'expectedExceptionClass' => RuntimeException::class,
            ],
        ];
    }

    public static function decodeDataProvider(): Generator
    {
        yield 'garanti_style_response' => [
            'input'    => '<?xml version="1.0" encoding="UTF-8"?>
<GVPSRequest><Mode>TEST</Mode><Version>v0.01</Version><Terminal><ID>30691298</ID><MerchantID>7000679</MerchantID></Terminal></GVPSRequest>
',
            'expected' => [
                'Mode'     => 'TEST',
                'Version'  => 'v0.01',
                'Terminal' => [
                    'ID'         => '30691298',
                    'MerchantID' => '7000679',
                ],
            ],
        ];

        yield 'simple_xml_response' => [
            'input'    => '<CC5Response><OrderId>order222</OrderId><ProcReturnCode>00</ProcReturnCode><Response>Approved</Response></CC5Response>',
            'expected' => [
                'OrderId'        => 'order222',
                'ProcReturnCode' => '00',
                'Response'       => 'Approved',
            ],
        ];

        yield 'xml_with_empty_element' => [
            'input'    => '<Response><Code>00</Code><Message/></Response>',
            'expected' => [
                'Code'    => '00',
                'Message' => '',
            ],
        ];

        yield 'cancel_response' => [
            'input'    => '<Response><Status>OK</Status></Response>',
            'expected' => ['Status' => 'OK'],
        ];
    }
}
