<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Serializer\Encoder;

use PHPUnit\Framework\Attributes\DataProvider;
use Generator;
use Mews\Pos\Serializer\EncodedData;
use Mews\Pos\Serializer\Encoder\KuveytPosSoapApiXmlEncoder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(KuveytPosSoapApiXmlEncoder::class)]
class KuveytPosSoapApiXmlEncoderTest extends TestCase
{
    private KuveytPosSoapApiXmlEncoder $encoder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->encoder = new KuveytPosSoapApiXmlEncoder();
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
        yield 'test_refund' => [
            'input'         => [
                'DrawBack' => [
                    'request' => [
                        'IsFromExternalNetwork' => true,
                        'BusinessKey' => 0,
                        'ProvisionNumber' => '241839',
                        'VPosMessage' => [
                            'APIVersion' => 'TDV2.0.0',
                            'InstallmentMaturityCommisionFlag' => 0,
                            'HashData' => 'request-hash',
                        ],
                    ],
                ],
            ],
            'expected_data' => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ser="http://boa.net/BOA.Integration.VirtualPos/Service"><soapenv:Body><ser:DrawBack><ser:request><ser:IsFromExternalNetwork>1</ser:IsFromExternalNetwork><ser:BusinessKey>0</ser:BusinessKey><ser:ProvisionNumber>241839</ser:ProvisionNumber><ser:VPosMessage><ser:APIVersion>TDV2.0.0</ser:APIVersion><ser:InstallmentMaturityCommisionFlag>0</ser:InstallmentMaturityCommisionFlag><ser:HashData>request-hash</ser:HashData></ser:VPosMessage></ser:request></ser:DrawBack></soapenv:Body></soapenv:Envelope>',
        ];

        yield 'test_partial_refund' => [
            'input'         => [
                'PartialDrawback' => [
                    'request' => [
                        'IsFromExternalNetwork' => true,
                        'BusinessKey' => 0,
                        'Stan' => '298433',
                        'ProvisionNumber' => '241839',
                        'VPosMessage' => [
                            'APIVersion' => 'TDV2.0.0',
                            'InstallmentMaturityCommisionFlag' => 0,
                        ],
                    ],
                ],
                ],
            'expected_data' => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ser="http://boa.net/BOA.Integration.VirtualPos/Service"><soapenv:Body><ser:PartialDrawback><ser:request><ser:IsFromExternalNetwork>1</ser:IsFromExternalNetwork><ser:BusinessKey>0</ser:BusinessKey><ser:Stan>298433</ser:Stan><ser:ProvisionNumber>241839</ser:ProvisionNumber><ser:VPosMessage><ser:APIVersion>TDV2.0.0</ser:APIVersion><ser:InstallmentMaturityCommisionFlag>0</ser:InstallmentMaturityCommisionFlag></ser:VPosMessage></ser:request></ser:PartialDrawback></soapenv:Body></soapenv:Envelope>',
        ];

        yield 'test_cancel' => [
            'input'         => ['abc' => 1, 'abc2' => ['abc3' => '3']],
            'expected_data' => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ser="http://boa.net/BOA.Integration.VirtualPos/Service"><soapenv:Body><ser:abc>1</ser:abc><ser:abc2><ser:abc3>3</ser:abc3></ser:abc2></soapenv:Body></soapenv:Envelope>',
        ];

        yield 'test_status' => [
            'input'         => ['abc' => 1, 'abc2' => ['abc3' => '3']],
            'expected_data' => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ser="http://boa.net/BOA.Integration.VirtualPos/Service"><soapenv:Body><ser:abc>1</ser:abc><ser:abc2><ser:abc3>3</ser:abc3></ser:abc2></soapenv:Body></soapenv:Envelope>',
        ];
    }
}
