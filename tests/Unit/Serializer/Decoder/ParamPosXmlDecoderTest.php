<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Serializer\Decoder;

use PHPUnit\Framework\Attributes\DataProvider;
use Generator;
use Mews\Pos\Serializer\Decoder\ParamPosXmlDecoder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ParamPosXmlDecoder::class)]
class ParamPosXmlDecoderTest extends TestCase
{
    private ParamPosXmlDecoder $decoder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->decoder = new ParamPosXmlDecoder();
    }

    #[DataProvider('decodeDataProvider')]
    public function testDecode(string $data, array $expected): void
    {
        $result = $this->decoder->decode($data);

        // UCD_HTML contains a large HTML document; just verify the key exists and ignore its value
        if (isset($result['TP_WMD_UCDResponse']['TP_WMD_UCDResult']['UCD_HTML'])) {
            $result['TP_WMD_UCDResponse']['TP_WMD_UCDResult']['UCD_HTML'] = $expected['TP_WMD_UCDResponse']['TP_WMD_UCDResult']['UCD_HTML'];
        }

        $this->assertSame($expected, $result);
    }

    public static function decodeDataProvider(): Generator
    {
        yield '3d_form_success' => [
            'input'    => \file_get_contents(__DIR__ . '/../../test_data/parampos/3d_form_response_success.xml'),
            'expected' => [
                'TP_WMD_UCDResponse' => [
                    'TP_WMD_UCDResult' => [
                        'Islem_ID'        => '6021840768',
                        'Islem_GUID'      => 'd68ac15c-17ca-4b7d-a046-10700291b249',
                        'UCD_HTML'        => 'html-document',
                        'UCD_MD'          => 'MosNOirpqxod2A0BdoPpFNf7E/hJX2pKvt8hunrQF2RSrggeWpNj9p+XDEgRdWfGdtGMHF5A7X/uVbJTb3cCN5LGcG2JsGd69bXc7yYBGGw/VMFTcHDObj+cVR6fP2k1s531ozcBEFN1hv+fwBH80YGHP2a6xbRujYzME2iPuPgCdr7wkoSWcZvwB5M73bFow3Jx3vqkwceaPUO6dat7m5Uv1dKmbp+py3yOR0nVaFGnKTmIB4JIAIuP24hCU2MJi+hvKDf7+IJIEl5cjotiUx/J0AINoeuIGrklDAZ8JRA7pxYXpZLwc3ZX60VpWvfS7sSOdayadMBOvltQSdRrPPhJztVNmkztgUe7s3rbpdVr4Fc/KzGtPa5PZLnpkXszhOO4g+pw0A3KuFsqTdFuuu25CqBTX/aG4yZ4VO7UKfG27cTgRaObKsU+YiwOhH/VgGODvd5qrR02gOY8f9Xqtw==',
                        'Sonuc'           => '1',
                        'Sonuc_Str'       => 'İşlem Başarılı',
                        'Banka_Sonuc_Kod' => '0',
                        'Siparis_ID'      => '20241229D2FF',
                    ],
                ],
            ],
        ];

        yield '3d_host_form_success' => [
            'input'    => \file_get_contents(__DIR__ . '/../../test_data/parampos/3d_host_form_response_success.xml'),
            'expected' => [
                'TO_Pre_Encrypting_OOSResponse' => [
                    'TO_Pre_Encrypting_OOSResult' => 'ImBuIu4mlRqIABImnjl/ikGHMe5ZOjZjP3wx61Wa7FxC3XrjnDTCsn7PngJ8DPdEV840PmqT+jVgsm+KaWcIZQq/lcJQKD3TdM99+MUcOKLxxFyByUJP3DkY9zZ4/2TJ7Px2HzZdfccockhMooHuM+A8PxjHKdK8z4pOvW/tpo/U4i6/jJPT9ZnhHL4GdRNOtu9l6sGtPHeKYC/FHYAZpGNXjR9+RCZGP/xTeFgC+Gl1o7dpRZaibII6PdPK1CNMdF8O1d1QI+PDCq/TxDSQxyyJgqovzYfBHT5mWjOx+WhiajrswNzVirR9hpii+Hq3sk+LnH90Psobh5db3F4BPpgUIuS6moVERKgq5HIhLkR4fe5TMfxcvOhj3Pg0RBQR',
                ],
            ],
        ];
    }
}
