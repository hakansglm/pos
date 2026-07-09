<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\PosQuery\Response;

use Generator;
use Mews\Pos\DataMapper\PosQuery\Response\AbstractQueryResponseDataMapper;
use Mews\Pos\DataMapper\PosQuery\Response\PayTrPosQueryResponseDataMapper;
use Mews\Pos\Exception\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\ResponseValueFormatterFactory;
use Mews\Pos\Factory\ResponseValueMapperFactory;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\PayTrPos;
use Mews\Pos\Model\Card\CreditCardInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(PayTrPosQueryResponseDataMapper::class)]
#[CoversClass(AbstractQueryResponseDataMapper::class)]
class PayTrPosQueryResponseDataMapperTest extends TestCase
{
    private PayTrPosQueryResponseDataMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapper = new PayTrPosQueryResponseDataMapper(
            ResponseValueFormatterFactory::createForGateway(PayTrPos::class),
            ResponseValueMapperFactory::createForGateway(PayTrPos::class),
            new NullLogger()
        );
    }

    public function testSupports(): void
    {
        $this->assertTrue(PayTrPosQueryResponseDataMapper::supports(PayTrPos::class));
        $this->assertFalse(PayTrPosQueryResponseDataMapper::supports(AssecoPos::class));
    }

    public function testMapInstallmentPricesResponseThrows(): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->mapper->mapInstallmentPricesResponse([]);
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

    public static function mapInstallmentRatesResponseDataProvider(): Generator
    {
        $rates = [
            ['installment' => 2, 'rate' => 7.28, 'constant' => 0.0],
            ['installment' => 3, 'rate' => 9.32, 'constant' => 0.0],
            ['installment' => 4, 'rate' => 11.35, 'constant' => 0.0],
            ['installment' => 5, 'rate' => 13.4, 'constant' => 0.0],
            ['installment' => 6, 'rate' => 15.43, 'constant' => 0.0],
            ['installment' => 7, 'rate' => 17.46, 'constant' => 0.0],
            ['installment' => 8, 'rate' => 19.5, 'constant' => 0.0],
            ['installment' => 9, 'rate' => 21.55, 'constant' => 0.0],
            ['installment' => 10, 'rate' => 23.57, 'constant' => 0.0],
            ['installment' => 11, 'rate' => 25.61, 'constant' => 0.0],
            ['installment' => 12, 'rate' => 27.65, 'constant' => 0.0],
        ];

        $oranlar = [
            'world'      => ['taksit_2' => 7.28, 'taksit_3' => 9.32, 'taksit_4' => 11.35, 'taksit_5' => 13.4, 'taksit_6' => 15.43, 'taksit_7' => 17.46, 'taksit_8' => 19.5, 'taksit_9' => 21.55, 'taksit_10' => 23.57, 'taksit_11' => 25.61, 'taksit_12' => 27.65],
            'axess'      => ['taksit_2' => 7.28, 'taksit_3' => 9.32, 'taksit_4' => 11.35, 'taksit_5' => 13.4, 'taksit_6' => 15.43, 'taksit_7' => 17.46, 'taksit_8' => 19.5, 'taksit_9' => 21.55, 'taksit_10' => 23.57, 'taksit_11' => 25.61, 'taksit_12' => 27.65],
            'cardfinans' => ['taksit_2' => 7.28, 'taksit_3' => 9.32, 'taksit_4' => 11.35, 'taksit_5' => 13.4, 'taksit_6' => 15.43, 'taksit_7' => 17.46, 'taksit_8' => 19.5, 'taksit_9' => 21.55, 'taksit_10' => 23.57, 'taksit_11' => 25.61, 'taksit_12' => 27.65],
            'paraf'      => ['taksit_2' => 7.28, 'taksit_3' => 9.32, 'taksit_4' => 11.35, 'taksit_5' => 13.4, 'taksit_6' => 15.43, 'taksit_7' => 17.46, 'taksit_8' => 19.5, 'taksit_9' => 21.55, 'taksit_10' => 23.57, 'taksit_11' => 25.61, 'taksit_12' => 27.65],
            'advantage'  => ['taksit_2' => 7.28, 'taksit_3' => 9.32, 'taksit_4' => 11.35, 'taksit_5' => 13.4, 'taksit_6' => 15.43, 'taksit_7' => 17.46, 'taksit_8' => 19.5, 'taksit_9' => 21.55, 'taksit_10' => 23.57, 'taksit_11' => 25.61, 'taksit_12' => 27.65],
            'bonus'      => ['taksit_2' => 7.28, 'taksit_3' => 9.32, 'taksit_4' => 11.35, 'taksit_5' => 13.4, 'taksit_6' => 15.43, 'taksit_7' => 17.46, 'taksit_8' => 19.5, 'taksit_9' => 21.55, 'taksit_10' => 23.57, 'taksit_11' => 25.61, 'taksit_12' => 27.65],
            'saglamkart' => ['taksit_2' => 7.28, 'taksit_3' => 9.32, 'taksit_4' => 11.35, 'taksit_5' => 13.4, 'taksit_6' => 15.43, 'taksit_7' => 17.46, 'taksit_8' => 19.5, 'taksit_9' => 21.55, 'taksit_10' => 23.57, 'taksit_11' => 25.61, 'taksit_12' => 27.65],
        ];

        yield 'success' => [
            'responseData' => [
                'status'           => 'success',
                'request_id'       => '20260623DF4B729920',
                'max_inst_non_bus' => '12',
                'oranlar'          => $oranlar,
            ],
            'expected'     => [
                'status'            => 'approved',
                'error_message'     => null,
                'installment_rates' => [
                    ['bank_code' => null, 'bank_name' => null, 'card_prefix' => null, 'card_type' => null, 'card_class' => null, 'card_family' => CreditCardInterface::CARD_FAMILY_WORLD, 'rates' => $rates],
                    ['bank_code' => null, 'bank_name' => null, 'card_prefix' => null, 'card_type' => null, 'card_class' => null, 'card_family' => CreditCardInterface::CARD_FAMILY_AXESS, 'rates' => $rates],
                    ['bank_code' => null, 'bank_name' => null, 'card_prefix' => null, 'card_type' => null, 'card_class' => null, 'card_family' => CreditCardInterface::CARD_FAMILY_CARDFINANS, 'rates' => $rates],
                    ['bank_code' => null, 'bank_name' => null, 'card_prefix' => null, 'card_type' => null, 'card_class' => null, 'card_family' => CreditCardInterface::CARD_FAMILY_PARAF, 'rates' => $rates],
                    ['bank_code' => null, 'bank_name' => null, 'card_prefix' => null, 'card_type' => null, 'card_class' => null, 'card_family' => CreditCardInterface::CARD_FAMILY_ADVANTAGE, 'rates' => $rates],
                    ['bank_code' => null, 'bank_name' => null, 'card_prefix' => null, 'card_type' => null, 'card_class' => null, 'card_family' => CreditCardInterface::CARD_FAMILY_BONUS, 'rates' => $rates],
                    ['bank_code' => null, 'bank_name' => null, 'card_prefix' => null, 'card_type' => null, 'card_class' => null, 'card_family' => CreditCardInterface::CARD_FAMILY_SAGLAMKART, 'rates' => $rates],
                ],
            ],
        ];

        yield 'success_with_taksit_1_skipped' => [
            'responseData' => [
                'status'     => 'success',
                'request_id' => '20260623DF4B',
                'oranlar'    => [
                    'world' => ['taksit_1' => 0.0, 'taksit_2' => 5.0, 'taksit_3' => 7.5],
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
                        'card_family' => CreditCardInterface::CARD_FAMILY_WORLD,
                        'rates'       => [
                            ['installment' => 2, 'rate' => 5.0, 'constant' => 0.0],
                            ['installment' => 3, 'rate' => 7.5, 'constant' => 0.0],
                        ],
                    ],
                ],
            ],
        ];

        yield 'failed' => [
            'responseData' => [
                'status'  => 'failed',
                'err_msg' => 'Geçersiz token',
            ],
            'expected'     => [
                'status'            => 'declined',
                'error_message'     => 'Geçersiz token',
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
        $this->assertSame($expected['error_code'], $actual['error_code']);
        $this->assertArrayHasKey('all', $actual);
    }

    public static function mapHistoryResponseDataProvider(): Generator
    {
        yield 'failed' => [
            'responseData' => [
                'status'  => 'failed',
                'err_no'  => 'ERR_001',
                'err_msg' => 'Hata mesajı',
            ],
            'expected'     => [
                'proc_return_code' => 'failed',
                'error_code'       => 'ERR_001',
                'status'           => 'declined',
                'trans_count'      => 0,
            ],
        ];

        yield 'success_with_transactions' => [
            'responseData' => [
                'status' => 'success',
                'list'   => [
                    [
                        'siparis_no'   => 'ORDER001',
                        'islem_tipi'   => 'sale',
                        'islem_tarihi' => '2024-05-01 10:00:00',
                        'islem_tutari' => '100.00',
                        'odeme_tutari' => '100.00',
                        'para_birimi'  => 'TRY',
                        'kart_no'      => '411111******1111',
                        'taksit'       => '1',
                    ],
                ],
            ],
            'expected'     => [
                'proc_return_code' => 'success',
                'error_code'       => null,
                'status'           => 'approved',
                'trans_count'      => 1,
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

    public static function mapBinListResponseDataProvider(): Generator
    {
        yield 'success_credit_visa' => [
            'responseData' => [
                'status'       => 'success',
                'cardType'     => 'credit',
                'businessCard' => 'n',
                'bank'         => 'Garanti Bankası',
                'brand'        => 'Bonus',
                'schema'       => 'VISA',
                'bankCode'     => 62,
                'allow_non3d'  => 'Y',
            ],
            'expected' => [
                'status'        => 'approved',
                'error_message' => null,
                'bin_list'      => [
                    [
                        'bin'         => null,
                        'bank_code'   => '62',
                        'bank_name'   => 'Garanti Bankası',
                        'card_type'   => CreditCardInterface::CARD_TYPE_VISA,
                        'card_class'  => CreditCardInterface::CARD_CLASS_CREDIT,
                        'card_family' => 'Bonus',
                    ],
                ],
            ],
        ];

        yield 'failed_unknown_bin' => [
            'responseData' => [
                'status' => 'failed',
            ],
            'expected' => [
                'status'        => 'declined',
                'error_message' => 'failed',
                'bin_list'      => [],
            ],
        ];

        yield 'error_invalid_request' => [
            'responseData' => [
                'status'  => 'error',
                'err_msg' => 'Invalid merchant_id',
            ],
            'expected' => [
                'status'        => 'declined',
                'error_message' => 'Invalid merchant_id',
                'bin_list'      => [],
            ],
        ];
    }
}
