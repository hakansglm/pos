<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Serializer\Encoder;

use PHPUnit\Framework\Attributes\DataProvider;
use Mews\Pos\Serializer\EncodedData;
use Mews\Pos\Serializer\Encoder\XmlEncoder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Encoder\XmlEncoder as SymfonyXmlEncoder;

#[CoversClass(XmlEncoder::class)]
class XmlEncoderTest extends TestCase
{
    #[DataProvider('encodeDataProvider')]
    public function testEncode(XmlEncoder $encoder, array $data, string $expectedData): void
    {
        $result = $encoder->encode($data);

        $this->assertSame(str_replace(["\r"], '', $expectedData), str_replace(["\r"], '', $result->getData()));
        $this->assertSame(EncodedData::FORMAT_XML, $result->getFormat());
    }

    public static function encodeDataProvider(): array
    {
        return [
            'garanti_utf8' => [
                'encoder'       => new XmlEncoder('GVPSRequest', 'UTF-8'),
                'input'         => ['Mode' => 'TEST', 'Version' => 'v0.01'],
                'expected_data' => '<?xml version="1.0" encoding="UTF-8"?>
<GVPSRequest><Mode>TEST</Mode><Version>v0.01</Version></GVPSRequest>
',
            ],
            'estpos_iso-8859-9' => [
                'encoder'       => new XmlEncoder('CC5Request', 'ISO-8859-9'),
                'input'         => ['Name' => 'test', 'Password' => 'secret'],
                'expected_data' => '<?xml version="1.0" encoding="ISO-8859-9"?>
<CC5Request><Name>test</Name><Password>secret</Password></CC5Request>
',
            ],
            'nested_data' => [
                'encoder'       => new XmlEncoder('posnetRequest', 'ISO-8859-9'),
                'input'         => ['terminal' => ['id' => '123', 'amount' => '100']],
                'expected_data' => '<?xml version="1.0" encoding="ISO-8859-9"?>
<posnetRequest><terminal><id>123</id><amount>100</amount></terminal></posnetRequest>
',
            ],
            'with_ignored_pi_node_option' => [
                'encoder'       => new XmlEncoder('VposRequest', 'UTF-8', [
                    SymfonyXmlEncoder::ENCODER_IGNORED_NODE_TYPES => [\XML_PI_NODE],
                ]),
                'input'         => ['MerchantId' => '000000000111111', 'Password' => 'secret'],
                'expected_data' => '<VposRequest><MerchantId>000000000111111</MerchantId><Password>secret</Password></VposRequest>',
            ],
            'cancel_request' => [
                'encoder'       => new XmlEncoder('GVPSRequest', 'UTF-8'),
                'input'         => ['key' => 'value'],
                'expected_data' => '<?xml version="1.0" encoding="UTF-8"?>
<GVPSRequest><key>value</key></GVPSRequest>
',
            ],
        ];
    }
}
