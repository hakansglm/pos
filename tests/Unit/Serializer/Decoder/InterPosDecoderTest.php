<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Serializer\Decoder;

use Mews\Pos\Serializer\Decoder\InterPosDecoder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

/**
 * @covers \Mews\Pos\Serializer\Decoder\InterPosDecoder
 */
class InterPosDecoderTest extends TestCase
{
    private InterPosDecoder $decoder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->decoder = new InterPosDecoder();
    }

    /**
     * @dataProvider decodeDataProvider
     */
    public function testDecode(string $data, array $expected): void
    {
        $actual = $this->decoder->decode($data);

        $this->assertSame($expected, $actual);
    }

    public function testDecodeWithMissingEqualsSignThrowsException(): void
    {
        $this->expectException(NotEncodableValueException::class);
        $this->decoder->decode('key1Value;;key2Value');
    }

    public static function decodeDataProvider(): array
    {
        return [
            'double_semicolon_delimiter' => [
                'input'    => 'OrderId=33554969;;ProcReturnCode=00;;TxnResult=Success;;ErrorMessage=',
                'expected' => [
                    'OrderId'        => '33554969',
                    'ProcReturnCode' => '00',
                    'TxnResult'      => 'Success',
                    'ErrorMessage'   => '',
                ],
            ],
            'triple_semicolon_delimiter' => [
                'input'    => 'OrderId=12345;;;ProcReturnCode=00;;;TxnResult=Failed',
                'expected' => [
                    'OrderId'        => '12345',
                    'ProcReturnCode' => '00',
                    'TxnResult'      => 'Failed',
                ],
            ],
            'value_containing_equals_sign' => [
                'input'    => 'Key=val=ue;;Other=data',
                'expected' => [
                    'Key'   => 'val=ue',
                    'Other' => 'data',
                ],
            ],
            'full_success_payment_response' => [
                'input'    => 'OrderId=33554969;;ProcReturnCode=00;;HostRefNum=hostid;;AuthCode=gizlendi;;TxnResult=Success;;ErrorMessage=;;CampanyId=;;CampanyInstallCount=0;;CampanyShiftDateCount=0;;CampanyTxnId=;;CampanyType=;;CampanyInstallment=0;;CampanyDate=0;;CampanyAmnt=0;;TRXDATE=09.08.2024 10:40:34;;TransId=gizlendi;;ErrorCode=;;EarnedBonus=0,00;;UsedBonus=0,00;;AvailableBonus=0,00;;BonusToBonus=0;;CampaignBonus=0,00;;FoldedBonus=0;;SurchargeAmount=0;;Amount=1,00;;CardHolderName=gizlendi;;QrReferenceNumber=;;QrCardToken=;;QrData=;;QrPayIsSucess=False;;QrIssuerPaymentMethod=;;QrFastMessageReferenceNo=;;QrFastParticipantReceiverCode=;;QrFastParticipantReceiverName=;;QrFastParticipantSenderCode=;;QrFastSenderIban=;;QrFastParticipantSenderName=;;QrFastPaymentResultDesc=',
                'expected' => [
                    'OrderId'                       => '33554969',
                    'ProcReturnCode'                => '00',
                    'HostRefNum'                    => 'hostid',
                    'AuthCode'                      => 'gizlendi',
                    'TxnResult'                     => 'Success',
                    'ErrorMessage'                  => '',
                    'CampanyId'                     => '',
                    'CampanyInstallCount'           => '0',
                    'CampanyShiftDateCount'         => '0',
                    'CampanyTxnId'                  => '',
                    'CampanyType'                   => '',
                    'CampanyInstallment'            => '0',
                    'CampanyDate'                   => '0',
                    'CampanyAmnt'                   => '0',
                    'TRXDATE'                       => '09.08.2024 10:40:34',
                    'TransId'                       => 'gizlendi',
                    'ErrorCode'                     => '',
                    'EarnedBonus'                   => '0,00',
                    'UsedBonus'                     => '0,00',
                    'AvailableBonus'                => '0,00',
                    'BonusToBonus'                  => '0',
                    'CampaignBonus'                 => '0,00',
                    'FoldedBonus'                   => '0',
                    'SurchargeAmount'               => '0',
                    'Amount'                        => '1,00',
                    'CardHolderName'                => 'gizlendi',
                    'QrReferenceNumber'             => '',
                    'QrCardToken'                   => '',
                    'QrData'                        => '',
                    'QrPayIsSucess'                 => 'False',
                    'QrIssuerPaymentMethod'         => '',
                    'QrFastMessageReferenceNo'      => '',
                    'QrFastParticipantReceiverCode' => '',
                    'QrFastParticipantReceiverName' => '',
                    'QrFastParticipantSenderCode'   => '',
                    'QrFastSenderIban'              => '',
                    'QrFastParticipantSenderName'   => '',
                    'QrFastPaymentResultDesc'       => '',
                ],
            ],
        ];
    }
}
