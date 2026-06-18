<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Serializer\Decoder;

use Mews\Pos\Serializer\Decoder\VakifKatilimPosXmlDecoder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(VakifKatilimPosXmlDecoder::class)]
class VakifKatilimPosXmlDecoderTest extends TestCase
{
    private VakifKatilimPosXmlDecoder $decoder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->decoder = new VakifKatilimPosXmlDecoder();
    }

    /**
     * @dataProvider decodeDataProvider
     */
    public function testDecode(string $data, array $expected): void
    {
        $actual = $this->decoder->decode($data);

        $this->assertSame($expected, $actual);
    }

    public static function decodeDataProvider(): \Generator
    {
        yield 'standard_utf8_response' => [
            'input'    => '<?xml version="1.0" encoding="UTF-8"?>
<VPosTransactionResponseContract xmlns:xsd="http://www.w3.org/2001/XMLSchema"
xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
 <VPosMessageContract>
<OkUrl>http://localhost/ThreeDModel/Approval</OkUrl>
<FailUrl>http://localhost/ThreeDModel/Fail</FailUrl>
<HashData>DvAUXMvYV4ex5m16mMezEl+kxrI=</HashData>
<MerchantId>1</MerchantId>
<SubMerchantId>0</SubMerchantId>
<CustomerId>936</CustomerId>
<UserName>APIUSER</UserName>
<HashPassword>kfkdsnskslkclswr9430ır</HashPassword>
<MerchantOrderId>1554891870</MerchantOrderId>
<InstallmentCount>0</InstallmentCount>
<Amount>111</Amount>
<FECAmount>0</FECAmount>
<AdditionalData>
 <AdditionalDataList>
 	<VPosAdditionalData>
 <Key>MD</Key>
 <Data>vygnTBD4smBxAOlDsgbaOQ==</Data>
 </VPosAdditionalData>
 </AdditionalDataList>
</AdditionalData>
<Products/>
<Addresses/>
<PaymentType>1</PaymentType>
<DebtId>0</DebtId>
<SurchargeAmount>0</SurchargeAmount>
<SGKDebtAmount>0</SGKDebtAmount>
<InstallmentMaturityCommisionFlag>0</InstallmentMaturityCommisionFlag>
<TransactionSecurity>3</TransactionSecurity>
 </VPosMessageContract>
 <IsEnrolled>true</IsEnrolled>
 <IsVirtual>false</IsVirtual>
 <RRN>922709016599</RRN>
 <Stan>016599</Stan>
 <ResponseCode>00</ResponseCode>
 <ResponseMessage>Provizyon Alindi.</ResponseMessage>
 <OrderId>15161</OrderId>
 <TransactionTime>00010101T00:00:00</TransactionTime>
 <MerchantOrderId>1554891870</MerchantOrderId>
 <HashData>bcCqBe4hbElPOVYtfvsw7M44usQ=</HashData>
</VPosTransactionResponseContract>',
            'expected' => [
                'VPosMessageContract' => [
                    'OkUrl'                            => 'http://localhost/ThreeDModel/Approval',
                    'FailUrl'                          => 'http://localhost/ThreeDModel/Fail',
                    'HashData'                         => 'DvAUXMvYV4ex5m16mMezEl+kxrI=',
                    'MerchantId'                       => '1',
                    'SubMerchantId'                    => '0',
                    'CustomerId'                       => '936',
                    'UserName'                         => 'APIUSER',
                    'HashPassword'                     => 'kfkdsnskslkclswr9430ır',
                    'MerchantOrderId'                  => '1554891870',
                    'InstallmentCount'                 => '0',
                    'Amount'                           => '111',
                    'FECAmount'                        => '0',
                    'AdditionalData'                   => [
                        'AdditionalDataList' => [
                            'VPosAdditionalData' => [
                                'Key'  => 'MD',
                                'Data' => 'vygnTBD4smBxAOlDsgbaOQ==',
                            ],
                        ],
                    ],
                    'Products'                         => '',
                    'Addresses'                        => '',
                    'PaymentType'                      => '1',
                    'DebtId'                           => '0',
                    'SurchargeAmount'                  => '0',
                    'SGKDebtAmount'                    => '0',
                    'InstallmentMaturityCommisionFlag' => '0',
                    'TransactionSecurity'              => '3',
                ],
                'IsEnrolled'          => 'true',
                'IsVirtual'           => 'false',
                'RRN'                 => '922709016599',
                'Stan'                => '016599',
                'ResponseCode'        => '00',
                'ResponseMessage'     => 'Provizyon Alindi.',
                'OrderId'             => '15161',
                'TransactionTime'     => '00010101T00:00:00',
                'MerchantOrderId'     => '1554891870',
                'HashData'            => 'bcCqBe4hbElPOVYtfvsw7M44usQ=',
                '@xmlns:xsi'          => 'http://www.w3.org/2001/XMLSchema-instance',
                '@xmlns:xsd'          => 'http://www.w3.org/2001/XMLSchema',
            ],
        ];

        yield 'response_with_utf16_encoding_declaration' => [
            'input'    => '<?xml version="1.0" encoding="utf-16"?>
<VPosTransactionResponseContract xmlns:xsd="http://www.w3.org/2001/XMLSchema"
xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
 <VPosOrderData>
 <OrderContract>
 <OrderId>12743</OrderId>
 <MerchantOrderId>1995434716</MerchantOrderId>
 <ResponseCode>00</ResponseCode>
 <ResponseMessage/>
 </OrderContract>
 </VPosOrderData>
 <ResponseCode>00</ResponseCode>
 <ResponseMessage/>
</VPosTransactionResponseContract>',
            'expected' => [
                'VPosOrderData'   => [
                    'OrderContract' => [
                        'OrderId'         => '12743',
                        'MerchantOrderId' => '1995434716',
                        'ResponseCode'    => '00',
                        'ResponseMessage' => '',
                    ],
                ],
                'ResponseCode'    => '00',
                'ResponseMessage' => '',
                '@xmlns:xsi'      => 'http://www.w3.org/2001/XMLSchema-instance',
                '@xmlns:xsd'      => 'http://www.w3.org/2001/XMLSchema',
            ],
        ];

        yield 'response_with_null_character' => [
            'input'    => '<?xml version="1.0" encoding="UTF-8"?><VPosTransactionResponseContract><ResponseCode>&#x0;00</ResponseCode></VPosTransactionResponseContract>',
            'expected' => ['ResponseCode' => '00'],
        ];
    }
}
