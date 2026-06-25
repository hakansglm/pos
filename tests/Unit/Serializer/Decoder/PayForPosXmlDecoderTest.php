<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Serializer\Decoder;

use PHPUnit\Framework\Attributes\DataProvider;
use Generator;
use Mews\Pos\Serializer\Decoder\PayForPosXmlDecoder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PayForPosXmlDecoder::class)]
class PayForPosXmlDecoderTest extends TestCase
{
    private PayForPosXmlDecoder $decoder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->decoder = new PayForPosXmlDecoder();
    }

    #[DataProvider('decodeDataProvider')]
    public function testDecode(string $data, array $expected): void
    {
        $actual = $this->decoder->decode($data);

        $this->assertSame($expected, $actual);
    }

    public static function decodeDataProvider(): Generator
    {
        yield 'standard_response' => [
            'input'    => '<?xml version="1.0" encoding="utf-8"?>
<PayforResponse>
<AuthCode>S31432</AuthCode>
<HostRefNum>326011208369</HostRefNum>
<ProcReturnCode>00</ProcReturnCode>
<TransId>20230917EF0E</TransId>
<ErrMsg>Onaylandı</ErrMsg>
<CardHolderName>John Doe</CardHolderName>
<ArtiTaksit>0</ArtiTaksit>
<BankInternalResponseMessage></BankInternalResponseMessage>
<PAYFORFROMXMLREQUEST>1</PAYFORFROMXMLREQUEST>
<SESSION_SYSTEM_USER>0</SESSION_SYSTEM_USER>
</PayforResponse>',
            'expected' => [
                'AuthCode'                    => 'S31432',
                'HostRefNum'                  => '326011208369',
                'ProcReturnCode'              => '00',
                'TransId'                     => '20230917EF0E',
                'ErrMsg'                      => 'Onaylandı',
                'CardHolderName'              => 'John Doe',
                'ArtiTaksit'                  => '0',
                'BankInternalResponseMessage' => '',
                'PAYFORFROMXMLREQUEST'        => '1',
                'SESSION_SYSTEM_USER'         => '0',
            ],
        ];

        yield 'response_with_redundant_crlf_whitespace' => [
            'input'    => "<?xml version=\"1.0\" encoding=\"utf-8\"?>\r\n<PayforResponse>\r\n  <AuthCode>S31432</AuthCode>\r\n</PayforResponse>",
            'expected' => ['AuthCode' => 'S31432'],
        ];

        yield 'response_with_whitespace_in_empty_elements' => [
            'input'    => "<?xml version=\"1.0\" encoding=\"utf-8\"?>\r\n<PayforResponse>\r\n<MbrId>5</MbrId>\r\n<MD>\r\n</MD>\r\n</PayforResponse>",
            'expected' => [
                'MbrId' => '5',
                'MD'    => '',
            ],
        ];

        yield 'cancel_response' => [
            'input'    => '<PayforResponse><Status>OK</Status></PayforResponse>',
            'expected' => ['Status' => 'OK'],
        ];
    }
}
