<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\PosQuery\Response;

use DateTimeImmutable;
use DateTimeZone;
use Mews\Pos\DataMapper\PosQuery\Response\AbstractQueryResponseDataMapper;
use Mews\Pos\DataMapper\PosQuery\Response\GarantiPosQueryResponseDataMapper;
use Mews\Pos\Factory\ResponseValueFormatterFactory;
use Mews\Pos\Factory\ResponseValueMapperFactory;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\GarantiPos;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\Tests\TestUtil\TestUtilTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(GarantiPosQueryResponseDataMapper::class)]
#[CoversClass(AbstractQueryResponseDataMapper::class)]
class GarantiPosQueryResponseDataMapperTest extends TestCase
{
    use TestUtilTrait;

    private GarantiPosQueryResponseDataMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapper = new GarantiPosQueryResponseDataMapper(
            ResponseValueFormatterFactory::createForGateway(GarantiPos::class),
            ResponseValueMapperFactory::createForGateway(GarantiPos::class),
            new NullLogger()
        );
    }

    public function testSupports(): void
    {
        $this->assertTrue(GarantiPosQueryResponseDataMapper::supports(GarantiPos::class));
        $this->assertFalse(GarantiPosQueryResponseDataMapper::supports(AssecoPos::class));
    }

    #[DataProvider('mapHistoryResponseDataProvider')]
    public function testMapHistoryResponse(array $responseData, array $expected): void
    {
        $actual = $this->mapper->mapHistoryResponse($responseData);

        foreach (\array_keys($actual['transactions']) as $key) {
            $this->assertEquals(
                $expected['transactions'][$key]['transaction_time'],
                $actual['transactions'][$key]['transaction_time'],
                'tx: '.$key
            );
            $this->assertEquals(
                $expected['transactions'][$key]['capture_time'] ?? null,
                $actual['transactions'][$key]['capture_time'] ?? null,
                'capture_time tx: '.$key
            );
            unset(
                $actual['transactions'][$key]['transaction_time'],
                $expected['transactions'][$key]['transaction_time'],
                $actual['transactions'][$key]['capture_time'],
                $expected['transactions'][$key]['capture_time']
            );
        }

        $this->assertArrayHasKey('all', $actual);
        unset($actual['all']);

        $this->recursiveKsort($expected);
        $this->recursiveKsort($actual);

        $this->assertSame($expected, $actual);
    }

    public static function mapHistoryResponseDataProvider(): \Generator
    {
        $expectedRaw = \json_decode(
            \file_get_contents(__DIR__.'/../../../test_data/garanti/history/daily_range_history_expected.json'),
            true
        );

        foreach ($expectedRaw['transactions'] as &$item) {
            if (null !== $item['transaction_time']) {
                $item['transaction_time'] = new DateTimeImmutable(
                    $item['transaction_time']['date'],
                    new DateTimeZone($item['transaction_time']['timezone'])
                );
            }
            if (null !== $item['capture_time']) {
                $item['capture_time'] = new DateTimeImmutable(
                    $item['capture_time']['date'],
                    new DateTimeZone($item['capture_time']['timezone'])
                );
            }
        }
        unset($item);

        yield 'date_range_history' => [
            'responseData' => \json_decode(
                \file_get_contents(__DIR__.'/../../../test_data/garanti/history/date_range_history.json'),
                true
            ),
            'expected' => $expectedRaw,
        ];

        yield 'single_transaction' => [
            'responseData' => [
                'Mode'        => '',
                'Terminal'    => ['ProvUserID' => 'PROVAUT', 'UserID' => 'PROVAUT', 'ID' => '30691298', 'MerchantID' => '7000679'],
                'Customer'    => ['IPAddress' => '172.26.0.1', 'EmailAddress' => ''],
                'Order'       => [
                    'OrderID'            => '',
                    'GroupID'            => '',
                    'OrderListInqResult' => [
                        'OrderTxnList' => [
                            'TotalTxnCount'  => '1',
                            'TotalPageCount' => '1',
                            'ActPageNum'     => '1',
                            'OrderTxn'       => [
                                'Id'               => '1',
                                'LastTrxDate'      => null,
                                'TrxType'          => null,
                                'OrderID'          => 'ORDER123',
                                'CardNumberMasked' => null,
                                'BatchNum'         => '576200',
                                'AuthCode'         => null,
                                'RetrefNum'        => null,
                                'InstallmentCnt'   => null,
                                'AuthAmount'       => null,
                                'CurrencyCode'     => null,
                                'Status'           => null,
                                'ResponseCode'     => 'E500',
                                'SysErrMsg'        => 'System error',
                                'SafeType'         => '',
                            ],
                        ],
                    ],
                ],
                'Transaction' => [
                    'Response' => ['Code' => 'E500', 'ErrorMsg' => 'System error'],
                ],
            ],
            'expected' => [
                'proc_return_code' => 'E500',
                'error_code'       => 'E500',
                'error_message'    => 'System error',
                'status'           => 'declined',
                'trans_count'      => 0,
                'transactions'     => [],
            ],
        ];
    }

    #[DataProvider('mapBinListResponseDataProvider')]
    public function testMapBinListResponse(array $responseData, array $expected): void
    {
        $actual = $this->mapper->mapBinListResponse($responseData);

        unset($actual['all'], $expected['all']);
        $this->assertSame($expected, $actual);
    }

    /**
     * @return array<string, array{array<string, mixed>, array<string, mixed>}>
     */
    public static function mapBinListResponseDataProvider(): array
    {
        return [
            'success_single_bin' => [
                'responseData' => [
                    'Transaction' => ['Response' => ['Code' => '00', 'ErrorMsg' => null]],
                    'BINInqResult' => [
                        'BINList' => [
                            'BIN' => [
                                'BINNum'       => '415956',
                                'BankCode'     => '62',
                                'BankName'     => 'T. GARANTİ BANKASI A.Ş.',
                                'Organization' => 'VISA',
                                'CardType'     => 'C',
                                'Group'        => 'Garanti',
                                'Product'      => 'GOLD',
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
                            'bank_name'   => 'T. GARANTİ BANKASI A.Ş.',
                            'card_type'   => CreditCardInterface::CARD_TYPE_VISA,
                            'card_class'  => CreditCardInterface::CARD_CLASS_CREDIT,
                            'card_family' => null,
                        ],
                    ],
                ],
            ],
            'success_multiple_bins' => [
                'responseData' => [
                    'Transaction' => ['Response' => ['Code' => '00', 'ErrorMsg' => null]],
                    'BINInqResult' => [
                        'BINList' => [
                            'BIN' => [
                                [
                                    'BINNum'       => '415956',
                                    'BankCode'     => '62',
                                    'BankName'     => 'T. GARANTİ BANKASI A.Ş.',
                                    'Organization' => 'VISA',
                                    'CardType'     => 'C',
                                    'Group'        => 'Garanti',
                                    'Product'      => 'GOLD',
                                ],
                                [
                                    'BINNum'       => '530596',
                                    'BankCode'     => '62',
                                    'BankName'     => 'T. GARANTİ BANKASI A.Ş.',
                                    'Organization' => 'MASTERCARD',
                                    'CardType'     => 'D',
                                    'Group'        => 'Bonus',
                                    'Product'      => 'DEBIT',
                                ],
                                [
                                    'BINNum'       => '900100',
                                    'BankCode'     => '62',
                                    'BankName'     => 'T. GARANTİ BANKASI A.Ş.',
                                    'Organization' => 'MASTERCARD',
                                    'CardType'     => 'M',
                                    'Group'        => 'Bonus',
                                    'Product'      => 'STORE',
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
                            'bank_name'   => 'T. GARANTİ BANKASI A.Ş.',
                            'card_type'   => CreditCardInterface::CARD_TYPE_VISA,
                            'card_class'  => CreditCardInterface::CARD_CLASS_CREDIT,
                            'card_family' => null,
                        ],
                        [
                            'bin'         => '530596',
                            'bank_code'   => '62',
                            'bank_name'   => 'T. GARANTİ BANKASI A.Ş.',
                            'card_type'   => CreditCardInterface::CARD_TYPE_MASTERCARD,
                            'card_class'  => CreditCardInterface::CARD_CLASS_DEBIT,
                            'card_family' => null,
                        ],
                        [
                            'bin'         => '900100',
                            'bank_code'   => '62',
                            'bank_name'   => 'T. GARANTİ BANKASI A.Ş.',
                            'card_type'   => CreditCardInterface::CARD_TYPE_MASTERCARD,
                            'card_class'  => CreditCardInterface::CARD_CLASS_PREPAID,
                            'card_family' => null,
                        ],
                    ],
                ],
            ],
            'failure' => [
                'responseData' => [
                    'Transaction' => [
                        'Response' => [
                            'Code'     => 'E500',
                            'ErrorMsg' => 'System error',
                        ],
                    ],
                ],
                'expected' => [
                    'status'        => 'declined',
                    'error_message' => 'System error',
                    'bin_list'      => [],
                ],
            ],
        ];
    }
}
