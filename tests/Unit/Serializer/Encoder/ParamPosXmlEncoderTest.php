<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Serializer\Encoder;

use PHPUnit\Framework\Attributes\DataProvider;
use Generator;
use Mews\Pos\Serializer\EncodedData;
use Mews\Pos\Serializer\Encoder\ParamPosXmlEncoder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ParamPosXmlEncoder::class)]
class ParamPosXmlEncoderTest extends TestCase
{
    private ParamPosXmlEncoder $encoder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->encoder = new ParamPosXmlEncoder();
    }

    #[DataProvider('encodeDataProvider')]
    public function testEncode(array $data, string $expectedData): void
    {
        $result = $this->encoder->encode($data);

        $this->assertSame(str_replace(["\r"], '', $expectedData), str_replace(["\r"], '', $result->getData()));
        $this->assertSame(EncodedData::FORMAT_XML, $result->getFormat());
    }

    public static function encodeDataProvider(): Generator
    {
        yield 'test_3d_payment' => [
            'input'         => [
                'soap:Body' => [
                    'TP_WMD_UCD' => [
                        'Islem_ID'           => 'rand',
                        'Islem_Hash'         => 'jsLYSB3lJ81leFgDLw4D8PbXURs=',
                        'G'                  => [
                            'CLIENT_CODE'     => '10738',
                            'CLIENT_USERNAME' => 'Test1',
                            'CLIENT_PASSWORD' => 'Test2',
                        ],
                        'GUID'               => '0c13d406-873b-403b-9c09-a5766840d98c',
                        'Islem_Guvenlik_Tip' => '3D',
                    ],
                ],
            ],
            'expected_data' => '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><TP_WMD_UCD><Islem_ID>rand</Islem_ID><Islem_Hash>jsLYSB3lJ81leFgDLw4D8PbXURs=</Islem_Hash><G><CLIENT_CODE>10738</CLIENT_CODE><CLIENT_USERNAME>Test1</CLIENT_USERNAME><CLIENT_PASSWORD>Test2</CLIENT_PASSWORD></G><GUID>0c13d406-873b-403b-9c09-a5766840d98c</GUID><Islem_Guvenlik_Tip>3D</Islem_Guvenlik_Tip></TP_WMD_UCD></soap:Body></soap:Envelope>
',
        ];

        yield 'test_xmlns_attributes_are_added_to_root' => [
            'input'         => [
                'soap:Body' => [
                    'SomeMethod' => [
                        'param1' => 'value1',
                    ],
                ],
            ],
            'expected_data' => '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><SomeMethod><param1>value1</param1></SomeMethod></soap:Body></soap:Envelope>
',
        ];
    }
}
