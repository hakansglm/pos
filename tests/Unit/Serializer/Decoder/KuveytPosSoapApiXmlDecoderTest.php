<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Serializer\Decoder;

use PHPUnit\Framework\Attributes\DataProvider;
use Generator;
use Mews\Pos\Serializer\Decoder\KuveytPosSoapApiXmlDecoder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(KuveytPosSoapApiXmlDecoder::class)]
class KuveytPosSoapApiXmlDecoderTest extends TestCase
{
    private KuveytPosSoapApiXmlDecoder $decoder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->decoder = new KuveytPosSoapApiXmlDecoder();
    }

    #[DataProvider('decodeDataProvider')]
    public function testDecode(string $data, array $expected): void
    {
        $actual = $this->decoder->decode($data);

        $this->assertSame($expected, $actual);
    }

    public static function decodeDataProvider(): Generator
    {
        yield 'test_cancel' => [
            'input'    => '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><SaleReversalResponse xmlns="http://boa.net/BOA.Integration.VirtualPos/Service"><SaleReversalResult><Results><Result><ErrorMessage>İptal işlemi satışla aynı gün yapılmalıdır. Geçmiş tarihli işlem için iade yapınız.</ErrorMessage><ErrorCode>InvalidRequestError</ErrorCode><IsFriendly>true</IsFriendly><Severity>BusinessError</Severity></Result></Results><Success>false</Success><Value><IsEnrolled>false</IsEnrolled><IsVirtual>false</IsVirtual><ResponseCode>DbLayerError</ResponseCode><OrderId>0</OrderId><TransactionTime>0001-01-01T00:00:00</TransactionTime><MerchantId xsi:nil="true"/><BusinessKey>0</BusinessKey></Value></SaleReversalResult></SaleReversalResponse></s:Body></s:Envelope>',
            'expected' => [
                'SaleReversalResponse' => [
                    'SaleReversalResult' => [
                        'Results' => [
                            'Result' => [
                                'ErrorMessage' => 'İptal işlemi satışla aynı gün yapılmalıdır. Geçmiş tarihli işlem için iade yapınız.',
                                'ErrorCode'    => 'InvalidRequestError',
                                'IsFriendly'   => 'true',
                                'Severity'     => 'BusinessError',
                            ],
                        ],
                        'Success' => 'false',
                        'Value'   => [
                            'IsEnrolled'      => 'false',
                            'IsVirtual'       => 'false',
                            'ResponseCode'    => 'DbLayerError',
                            'OrderId'         => '0',
                            'TransactionTime' => '0001-01-01T00:00:00',
                            'MerchantId'      => [
                                '@xsi:nil' => 'true',
                                '#'        => '',
                            ],
                            'BusinessKey'     => '0',
                        ],
                    ],
                ],
            ],
        ];

        yield 'test_refund' => [
            'input'    => '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><PartialDrawbackResponse xmlns="http://boa.net/BOA.Integration.VirtualPos/Service"><PartialDrawbackResult><Results><Result><ErrorMessage>IsoProxyFactoryServiceResponseWasNull</ErrorMessage><ErrorCode>ServiceUnavailable</ErrorCode><IsFriendly>true</IsFriendly><Severity>BusinessError</Severity></Result></Results><Success>false</Success><Value><IsEnrolled>false</IsEnrolled><IsVirtual>false</IsVirtual><ResponseCode>DbLayerError</ResponseCode><OrderId>0</OrderId><TransactionTime>0001-01-01T00:00:00</TransactionTime><MerchantId xsi:nil="true"/><BusinessKey>0</BusinessKey></Value></PartialDrawbackResult></PartialDrawbackResponse></s:Body></s:Envelope>',
            'expected' => [
                'PartialDrawbackResponse' => [
                    'PartialDrawbackResult' => [
                        'Results' => [
                            'Result' => [
                                'ErrorMessage' => 'IsoProxyFactoryServiceResponseWasNull',
                                'ErrorCode'    => 'ServiceUnavailable',
                                'IsFriendly'   => 'true',
                                'Severity'     => 'BusinessError',
                            ],
                        ],
                        'Success' => 'false',
                        'Value'   => [
                            'IsEnrolled'      => 'false',
                            'IsVirtual'       => 'false',
                            'ResponseCode'    => 'DbLayerError',
                            'OrderId'         => '0',
                            'TransactionTime' => '0001-01-01T00:00:00',
                            'MerchantId'      => [
                                '@xsi:nil' => 'true',
                                '#'        => '',
                            ],
                            'BusinessKey'     => '0',
                        ],
                    ],
                ],
            ],
        ];
    }
}
