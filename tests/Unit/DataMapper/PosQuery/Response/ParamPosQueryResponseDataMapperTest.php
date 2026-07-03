<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\PosQuery\Response;

use Mews\Pos\DataMapper\PosQuery\Response\AbstractQueryResponseDataMapper;
use Mews\Pos\DataMapper\PosQuery\Response\ParamPosQueryResponseDataMapper;
use Mews\Pos\Factory\ResponseValueFormatterFactory;
use Mews\Pos\Factory\ResponseValueMapperFactory;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\ParamPos;
use Mews\Pos\Model\Card\CreditCardInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(ParamPosQueryResponseDataMapper::class)]
#[CoversClass(AbstractQueryResponseDataMapper::class)]
class ParamPosQueryResponseDataMapperTest extends TestCase
{
    private ParamPosQueryResponseDataMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapper = new ParamPosQueryResponseDataMapper(
            ResponseValueFormatterFactory::createForGateway(ParamPos::class),
            ResponseValueMapperFactory::createForGateway(ParamPos::class),
            new NullLogger()
        );
    }

    public function testSupports(): void
    {
        $this->assertTrue(ParamPosQueryResponseDataMapper::supports(ParamPos::class));
        $this->assertFalse(ParamPosQueryResponseDataMapper::supports(AssecoPos::class));
    }

    #[DataProvider('mapInstallmentRatesResponseDataProvider')]
    public function testMapInstallmentRatesResponse(array $responseData, array $expected): void
    {
        $actual = $this->mapper->mapInstallmentRatesResponse($responseData);

        $this->assertArrayHasKey('all', $actual);
        $this->assertSame($responseData, $actual['all']);
        unset($actual['all']);

        ksort($actual);
        ksort($expected);
        $this->assertSame($expected, $actual);
    }

    public static function mapInstallmentRatesResponseDataProvider(): \Generator
    {
        yield 'success_multiple_card_programs' => [
            'responseData' => [
                'TP_Ozel_Oran_SK_ListeResponse' => [
                    '@xmlns'                      => 'https://turkpos.com.tr/',
                    'TP_Ozel_Oran_SK_ListeResult' => [
                        'Sonuc'     => '1',
                        'Sonuc_Str' => 'Başarılı',
                        'DT_Bilgi'  => [
                            'diffgr:diffgram' => [
                                'NewDataSet' => [
                                    'DT_Ozel_Oranlar_SK' => [
                                        [
                                            'Ozel_Oran_SK_ID'   => '849809',
                                            'Kredi_Karti_Banka' => 'Axess',
                                            'MO_01'             => '1.1900',
                                            'MO_02'             => '-2.0000',
                                            'MO_03'             => '6.6200',
                                            'MO_04'             => '-2.0000',
                                            'MO_05'             => '-2.0000',
                                            'MO_06'             => '10.5000',
                                            'MO_07'             => '-2.0000',
                                            'MO_08'             => '-2.0000',
                                            'MO_09'             => '0.0000',
                                            'MO_10'             => '-2.0000',
                                            'MO_11'             => '-2.0000',
                                            'MO_12'             => '0.0000',
                                        ],
                                        [
                                            'Ozel_Oran_SK_ID'   => '849805',
                                            'Kredi_Karti_Banka' => 'World',
                                            'MO_01'             => '1.1900',
                                            'MO_02'             => '-2.0000',
                                            'MO_03'             => '6.6200',
                                            'MO_04'             => '-2.0000',
                                            'MO_05'             => '-2.0000',
                                            'MO_06'             => '10.5000',
                                            'MO_07'             => '-2.0000',
                                            'MO_08'             => '-2.0000',
                                            'MO_09'             => '0.0000',
                                            'MO_10'             => '-2.0000',
                                            'MO_11'             => '-2.0000',
                                            'MO_12'             => '0.0000',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'expected'     => [
                'status'            => 'approved',
                'error_message'     => null,
                'installment_rates' => [
                    [
                        'bank_code'   => null,
                        'bank_name'   => null,
                        'card_prefix' => null,
                        'card_type'   => null,
                        'card_class'  => null,
                        'card_family' => CreditCardInterface::CARD_FAMILY_AXESS,
                        'rates'       => [
                            ['installment' => 3, 'rate' => 6.62, 'constant' => 0.0],
                            ['installment' => 6, 'rate' => 10.5, 'constant' => 0.0],
                            ['installment' => 9, 'rate' => 0.0, 'constant' => 0.0],
                            ['installment' => 12, 'rate' => 0.0, 'constant' => 0.0],
                        ],
                    ],
                    [
                        'bank_code'   => null,
                        'bank_name'   => null,
                        'card_prefix' => null,
                        'card_type'   => null,
                        'card_class'  => null,
                        'card_family' => CreditCardInterface::CARD_FAMILY_WORLD,
                        'rates'       => [
                            ['installment' => 3, 'rate' => 6.62, 'constant' => 0.0],
                            ['installment' => 6, 'rate' => 10.5, 'constant' => 0.0],
                            ['installment' => 9, 'rate' => 0.0, 'constant' => 0.0],
                            ['installment' => 12, 'rate' => 0.0, 'constant' => 0.0],
                        ],
                    ],
                ],
            ],
        ];

        yield 'success_single_card_program' => [
            'responseData' => [
                'TP_Ozel_Oran_SK_ListeResponse' => [
                    '@xmlns'                      => 'https://turkpos.com.tr/',
                    'TP_Ozel_Oran_SK_ListeResult' => [
                        'Sonuc'     => '1',
                        'Sonuc_Str' => 'Başarılı',
                        'DT_Bilgi'  => [
                            'diffgr:diffgram' => [
                                'NewDataSet' => [
                                    // Single item: decoded as associative array, not array-of-arrays.
                                    'DT_Ozel_Oranlar_SK' => [
                                        'Ozel_Oran_SK_ID'   => '849808',
                                        'Kredi_Karti_Banka' => 'Bonus',
                                        'MO_01'             => '1.1900',
                                        'MO_02'             => '-2.0000',
                                        'MO_03'             => '6.6200',
                                        'MO_04'             => '-2.0000',
                                        'MO_05'             => '-2.0000',
                                        'MO_06'             => '10.5000',
                                        'MO_07'             => '-2.0000',
                                        'MO_08'             => '-2.0000',
                                        'MO_09'             => '0.0000',
                                        'MO_10'             => '-2.0000',
                                        'MO_11'             => '-2.0000',
                                        'MO_12'             => '0.0000',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'expected'     => [
                'status'            => 'approved',
                'error_message'     => null,
                'installment_rates' => [
                    [
                        'bank_code'   => null,
                        'bank_name'   => null,
                        'card_prefix' => null,
                        'card_type'   => null,
                        'card_class'  => null,
                        'card_family' => CreditCardInterface::CARD_FAMILY_BONUS,
                        'rates'       => [
                            ['installment' => 3, 'rate' => 6.62, 'constant' => 0.0],
                            ['installment' => 6, 'rate' => 10.5, 'constant' => 0.0],
                            ['installment' => 9, 'rate' => 0.0, 'constant' => 0.0],
                            ['installment' => 12, 'rate' => 0.0, 'constant' => 0.0],
                        ],
                    ],
                ],
            ],
        ];

        yield 'failed' => [
            'responseData' => [
                'TP_Ozel_Oran_SK_ListeResponse' => [
                    '@xmlns'                      => 'https://turkpos.com.tr/',
                    'TP_Ozel_Oran_SK_ListeResult' => [
                        'Sonuc'     => '-1',
                        'Sonuc_Str' => 'Geçersiz GUID!',
                    ],
                ],
            ],
            'expected'     => [
                'status'            => 'declined',
                'error_message'     => 'Geçersiz GUID!',
                'installment_rates' => [],
            ],
        ];
    }

    #[DataProvider('mapHistoryResponseDataProvider')]
    public function testMapHistoryResponse(array $responseData, array $expected): void
    {
        $actual = $this->mapper->mapHistoryResponse($responseData);

        $this->assertCount($expected['trans_count'], $actual['transactions']);
        $this->assertSame($expected['proc_return_code'], $actual['proc_return_code']);
        $this->assertSame($expected['status'], $actual['status']);
        $this->assertArrayHasKey('all', $actual);
    }

    public static function mapHistoryResponseDataProvider(): \Generator
    {
        yield 'failed_negative_return_code' => [
            'responseData' => [
                'TP_Islem_IzlemeResponse' => [
                    'TP_Islem_IzlemeResult' => [
                        'Sonuc'     => '-1',
                        'Sonuc_Str' => 'Başarısız',
                    ],
                ],
            ],
            'expected'     => [
                'proc_return_code' => -1,
                'status'           => 'declined',
                'trans_count'      => 0,
            ],
        ];

        $fixture = \json_decode(
            \file_get_contents(__DIR__.'/../../../test_data/parampos/history_response_1.json'),
            true
        );

        $expectedData = \json_decode(
            \file_get_contents(__DIR__.'/../../../test_data/parampos/history_response_1_expected.json'),
            true
        );

        yield 'success' => [
            'responseData' => $fixture,
            'expected'     => [
                'proc_return_code' => $expectedData['proc_return_code'],
                'status'           => 'approved',
                'trans_count'      => $expectedData['trans_count'],
            ],
        ];
    }

    #[DataProvider('mapBinListResponseDataProvider')]
    public function testMapBinListResponse(array $responseData, array $expected): void
    {
        $actual = $this->mapper->mapBinListResponse($responseData);

        $this->assertArrayHasKey('all', $actual);
        $this->assertSame($responseData, $actual['all']);
        unset($actual['all'], $expected['all']);

        $this->assertSame($expected, $actual);
    }

    public static function mapBinListResponseDataProvider(): \Generator
    {
        yield 'success_single_bin' => [
            'responseData' => [
                'BIN_SanalPosResponse' => [
                    'BIN_SanalPosResult' => [
                        'Sonuc'     => 1,
                        'Sonuc_Str' => 'Basarili',
                        'DT_Bilgi'  => [
                            'diffgr:diffgram' => [
                                'NewDataSet' => [
                                    'Temp' => [
                                        'BIN'        => '415956',
                                        'Banka_Kodu' => '62',
                                        'Kart_Banka' => 'Garanti Bankası',
                                        'Kart_Org'   => 'VISA',
                                        'Kart_Tip'   => 'Kredi Kartı',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'expected' => [
                'status'        => 'approved',
                'error_message' => null,
                'bin_list'      => [
                    [
                        'bin'         => '415956',
                        'bank_code'   => '62',
                        'bank_name'   => 'Garanti Bankası',
                        'card_type'   => CreditCardInterface::CARD_TYPE_VISA,
                        'card_class'  => CreditCardInterface::CARD_CLASS_CREDIT,
                        'card_family' => null,
                    ],
                ],
            ],
        ];

        yield 'success_multiple_bins' => [
            'responseData' => [
                'BIN_SanalPosResponse' => [
                    'BIN_SanalPosResult' => [
                        'Sonuc'     => 1,
                        'Sonuc_Str' => 'Basarili',
                        'DT_Bilgi'  => [
                            'diffgr:diffgram' => [
                                'NewDataSet' => [
                                    'Temp' => [
                                        [
                                            'BIN'        => '415956',
                                            'Banka_Kodu' => '62',
                                            'Kart_Banka' => 'Garanti Bankası',
                                            'Kart_Org'   => 'VISA',
                                            'Kart_Tip'   => 'Kredi Kartı',
                                        ],
                                        [
                                            'BIN'        => '530596',
                                            'Banka_Kodu' => '62',
                                            'Kart_Banka' => 'Garanti Bankası',
                                            'Kart_Org'   => 'MASTER',
                                            'Kart_Tip'   => 'Debit Kart',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'expected' => [
                'status'        => 'approved',
                'error_message' => null,
                'bin_list'      => [
                    [
                        'bin'         => '415956',
                        'bank_code'   => '62',
                        'bank_name'   => 'Garanti Bankası',
                        'card_type'   => CreditCardInterface::CARD_TYPE_VISA,
                        'card_class'  => CreditCardInterface::CARD_CLASS_CREDIT,
                        'card_family' => null,
                    ],
                    [
                        'bin'         => '530596',
                        'bank_code'   => '62',
                        'bank_name'   => 'Garanti Bankası',
                        'card_type'   => CreditCardInterface::CARD_TYPE_MASTERCARD,
                        'card_class'  => CreditCardInterface::CARD_CLASS_DEBIT,
                        'card_family' => null,
                    ],
                ],
            ],
        ];

        yield 'failure' => [
            'responseData' => [
                'BIN_SanalPosResponse' => [
                    'BIN_SanalPosResult' => [
                        'Sonuc'     => -1,
                        'Sonuc_Str' => 'Hata oluştu',
                    ],
                ],
            ],
            'expected' => [
                'status'        => 'declined',
                'error_message' => 'Hata oluştu',
                'bin_list'      => [],
            ],
        ];
    }
}
