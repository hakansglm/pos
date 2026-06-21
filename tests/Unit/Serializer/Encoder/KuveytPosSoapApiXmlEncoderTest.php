<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Serializer\Encoder;

use Mews\Pos\Serializer\EncodedData;
use Mews\Pos\Serializer\Encoder\KuveytPosSoapApiXmlEncoder;
use Mews\Pos\Tests\Unit\DataMapper\Request\Mapper\KuveytPosRequestDataMapperTest;
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

    /**
     * @dataProvider encodeDataProvider
     */
    public function testEncode(array $data, string $expectedData): void
    {
        $result = $this->encoder->encode($data);

        $this->assertSame(str_replace(["\r"], '', $expectedData), str_replace(["\r"], '', $result->getData()));
        $this->assertSame(EncodedData::FORMAT_XML, $result->getFormat());
    }

    public static function encodeDataProvider(): \Generator
    {
        $refundTests = iterator_to_array(KuveytPosRequestDataMapperTest::createRefundRequestDataProvider());

        yield 'test_refund' => [
            'input'         => $refundTests[0]['expected'],
            'expected_data' => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ser="http://boa.net/BOA.Integration.VirtualPos/Service"><soapenv:Body><ser:DrawBack><ser:request><ser:IsFromExternalNetwork>1</ser:IsFromExternalNetwork><ser:BusinessKey>0</ser:BusinessKey><ser:ResourceId>0</ser:ResourceId><ser:ActionId>0</ser:ActionId><ser:LanguageId>0</ser:LanguageId><ser:CustomerId>400235</ser:CustomerId><ser:MailOrTelephoneOrder>1</ser:MailOrTelephoneOrder><ser:Amount>101</ser:Amount><ser:MerchantId>80</ser:MerchantId><ser:OrderId>114293600</ser:OrderId><ser:RRN>318923298433</ser:RRN><ser:Stan>298433</ser:Stan><ser:ProvisionNumber>241839</ser:ProvisionNumber><ser:VPosMessage><ser:APIVersion>TDV2.0.0</ser:APIVersion><ser:InstallmentMaturityCommisionFlag>0</ser:InstallmentMaturityCommisionFlag><ser:HashData>request-hash</ser:HashData><ser:MerchantId>80</ser:MerchantId><ser:SubMerchantId>0</ser:SubMerchantId><ser:CustomerId>400235</ser:CustomerId><ser:UserName>apiuser</ser:UserName><ser:CardType>Visa</ser:CardType><ser:BatchID>0</ser:BatchID><ser:TransactionType>DrawBack</ser:TransactionType><ser:InstallmentCount>0</ser:InstallmentCount><ser:Amount>101</ser:Amount><ser:DisplayAmount>0</ser:DisplayAmount><ser:CancelAmount>101</ser:CancelAmount><ser:MerchantOrderId>2023070849CD</ser:MerchantOrderId><ser:FECAmount>0</ser:FECAmount><ser:CurrencyCode>0949</ser:CurrencyCode><ser:QeryId>0</ser:QeryId><ser:DebtId>0</ser:DebtId><ser:SurchargeAmount>0</ser:SurchargeAmount><ser:SGKDebtAmount>0</ser:SGKDebtAmount><ser:TransactionSecurity>1</ser:TransactionSecurity></ser:VPosMessage></ser:request></ser:DrawBack></soapenv:Body></soapenv:Envelope>',
        ];

        yield 'test_partial_refund' => [
            'input'         => $refundTests[1]['expected'],
            'expected_data' => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ser="http://boa.net/BOA.Integration.VirtualPos/Service"><soapenv:Body><ser:PartialDrawback><ser:request><ser:IsFromExternalNetwork>1</ser:IsFromExternalNetwork><ser:BusinessKey>0</ser:BusinessKey><ser:ResourceId>0</ser:ResourceId><ser:ActionId>0</ser:ActionId><ser:LanguageId>0</ser:LanguageId><ser:CustomerId>400235</ser:CustomerId><ser:MailOrTelephoneOrder>1</ser:MailOrTelephoneOrder><ser:Amount>901</ser:Amount><ser:MerchantId>80</ser:MerchantId><ser:OrderId>114293600</ser:OrderId><ser:RRN>318923298433</ser:RRN><ser:Stan>298433</ser:Stan><ser:ProvisionNumber>241839</ser:ProvisionNumber><ser:VPosMessage><ser:APIVersion>TDV2.0.0</ser:APIVersion><ser:InstallmentMaturityCommisionFlag>0</ser:InstallmentMaturityCommisionFlag><ser:HashData>request-hash</ser:HashData><ser:MerchantId>80</ser:MerchantId><ser:SubMerchantId>0</ser:SubMerchantId><ser:CustomerId>400235</ser:CustomerId><ser:UserName>apiuser</ser:UserName><ser:CardType>Visa</ser:CardType><ser:BatchID>0</ser:BatchID><ser:TransactionType>PartialDrawback</ser:TransactionType><ser:InstallmentCount>0</ser:InstallmentCount><ser:Amount>901</ser:Amount><ser:DisplayAmount>0</ser:DisplayAmount><ser:CancelAmount>901</ser:CancelAmount><ser:MerchantOrderId>2023070849CD</ser:MerchantOrderId><ser:FECAmount>0</ser:FECAmount><ser:CurrencyCode>0949</ser:CurrencyCode><ser:QeryId>0</ser:QeryId><ser:DebtId>0</ser:DebtId><ser:SurchargeAmount>0</ser:SurchargeAmount><ser:SGKDebtAmount>0</ser:SGKDebtAmount><ser:TransactionSecurity>1</ser:TransactionSecurity></ser:VPosMessage></ser:request></ser:PartialDrawback></soapenv:Body></soapenv:Envelope>',
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
