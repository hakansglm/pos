<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\PosQuery\Response;

use Mews\Pos\DataMapper\PosQuery\Response\AbstractQueryResponseDataMapper;
use Mews\Pos\DataMapper\PosQuery\Response\IyzicoPosQueryResponseDataMapper;
use Mews\Pos\Exception\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\ResponseValueFormatterFactory;
use Mews\Pos\Factory\ResponseValueMapperFactory;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\IyzicoPos;
use Mews\Pos\Model\Card\CreditCardInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(IyzicoPosQueryResponseDataMapper::class)]
#[CoversClass(AbstractQueryResponseDataMapper::class)]
class IyzicoPosQueryResponseDataMapperTest extends TestCase
{
    private IyzicoPosQueryResponseDataMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapper = new IyzicoPosQueryResponseDataMapper(
            ResponseValueFormatterFactory::createForGateway(IyzicoPos::class),
            ResponseValueMapperFactory::createForGateway(IyzicoPos::class),
            new NullLogger()
        );
    }

    public function testSupports(): void
    {
        $this->assertTrue(IyzicoPosQueryResponseDataMapper::supports(IyzicoPos::class));
        $this->assertFalse(IyzicoPosQueryResponseDataMapper::supports(AssecoPos::class));
    }

    public function testMapInstallmentRatesResponseThrows(): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->mapper->mapInstallmentRatesResponse([]);
    }

    #[DataProvider('mapHistoryResponseDataProvider')]
    public function testMapHistoryResponse(array $responseData, array $expected): void
    {
        $actual = $this->mapper->mapHistoryResponse($responseData);

        $this->assertCount($expected['trans_count'], $actual['transactions']);
        $this->assertSame($expected['proc_return_code'], $actual['proc_return_code']);
        $this->assertSame($expected['status'], $actual['status']);
        $this->assertSame($expected['error_code'], $actual['error_code']);
        $this->assertSame($expected['error_message'], $actual['error_message']);
        $this->assertArrayHasKey('all', $actual);
    }

    #[DataProvider('mapInstallmentPricesResponseDataProvider')]
    public function testMapInstallmentPricesResponse(array $responseData, array $expected): void
    {
        $actual = $this->mapper->mapInstallmentPricesResponse($responseData);

        $this->assertArrayHasKey('all', $actual);
        $this->assertSame($responseData, $actual['all']);
        unset($actual['all']);

        ksort($actual);
        ksort($expected);
        $this->assertSame($expected, $actual);
    }

    public static function mapInstallmentPricesResponseDataProvider(): \Generator
    {
        yield 'success_with_bin' => [
            'responseData' => [
                'status'             => 'success',
                'locale'             => 'tr',
                'systemTime'         => 1782903507085,
                'conversationId'     => '6E27274945B4EDF8F024EC0E',
                'installmentDetails' => [
                    [
                        'binNumber'          => '54308100',
                        'price'              => 100.0,
                        'cardType'           => 'CREDIT_CARD',
                        'cardAssociation'    => 'MASTER_CARD',
                        'cardFamilyName'     => 'Paraf',
                        'force3ds'           => 0,
                        'bankCode'           => 12,
                        'bankName'           => 'Halkbank',
                        'forceCvc'           => 0,
                        'commercial'         => 0,
                        'dccEnabled'         => 0,
                        'agricultureEnabled' => 0,
                        'installmentPrices'  => [
                            ['installmentPrice' => 100.0, 'totalPrice' => 100.0, 'installmentNumber' => 1],
                            ['installmentPrice' => 51.61, 'totalPrice' => 103.22, 'installmentNumber' => 2],
                            ['installmentPrice' => 35.08, 'totalPrice' => 105.25, 'installmentNumber' => 3],
                            ['installmentPrice' => 18.7, 'totalPrice' => 112.22, 'installmentNumber' => 6],
                            ['installmentPrice' => 13.35, 'totalPrice' => 120.19, 'installmentNumber' => 9],
                            ['installmentPrice' => 10.77, 'totalPrice' => 129.2, 'installmentNumber' => 12],
                        ],
                    ],
                ],
            ],
            'expected'     => [
                'status'             => 'approved',
                'error_message'      => null,
                'installment_prices' => [
                    [
                        'bank_code'   => 12,
                        'bank_name'   => 'Halkbank',
                        'card_prefix' => '54308100',
                        'card_type'   => 'master',
                        'card_class'  => CreditCardInterface::CARD_CLASS_CREDIT,
                        'card_family' => CreditCardInterface::CARD_FAMILY_PARAF,
                        'prices'      => [
                            ['installment' => 1, 'installment_price' => 100.0, 'total_price' => 100.0],
                            ['installment' => 2, 'installment_price' => 51.61, 'total_price' => 103.22],
                            ['installment' => 3, 'installment_price' => 35.08, 'total_price' => 105.25],
                            ['installment' => 6, 'installment_price' => 18.7, 'total_price' => 112.22],
                            ['installment' => 9, 'installment_price' => 13.35, 'total_price' => 120.19],
                            ['installment' => 12, 'installment_price' => 10.77, 'total_price' => 129.2],
                        ],
                    ],
                ],
            ],
        ];

        yield 'success_without_bin' => [
            'responseData' => [
                'status'             => 'success',
                'locale'             => 'tr',
                'systemTime'         => 1782915155905,
                'conversationId'     => '00B074DD4AC09BD2DAB9DF78',
                'installmentDetails' => [
                    [
                        'price'              => 100,
                        'cardFamilyName'     => 'Paraf',
                        'force3ds'           => 0,
                        'bankCode'           => 12,
                        'bankName'           => 'Halkbank',
                        'forceCvc'           => 0,
                        'dccEnabled'         => 0,
                        'agricultureEnabled' => 0,
                        'installmentPrices'  => [
                            ['installmentPrice' => 100.0, 'totalPrice' => 100.0, 'installmentNumber' => 1],
                            ['installmentPrice' => 51.61, 'totalPrice' => 103.22, 'installmentNumber' => 2],
                            ['installmentPrice' => 35.08, 'totalPrice' => 105.25, 'installmentNumber' => 3],
                        ],
                    ],
                    [
                        'price'              => 100,
                        'cardFamilyName'     => 'Bonus',
                        'force3ds'           => 0,
                        'bankCode'           => 134,
                        'bankName'           => 'Denizbank',
                        'forceCvc'           => 0,
                        'dccEnabled'         => 0,
                        'agricultureEnabled' => 0,
                        'installmentPrices'  => [
                            ['installmentPrice' => 100.0, 'totalPrice' => 100.0, 'installmentNumber' => 1],
                            ['installmentPrice' => 51.61, 'totalPrice' => 103.22, 'installmentNumber' => 2],
                        ],
                    ],
                ],
            ],
            'expected'     => [
                'status'             => 'approved',
                'error_message'      => null,
                'installment_prices' => [
                    [
                        'bank_code'   => 12,
                        'bank_name'   => 'Halkbank',
                        'card_prefix' => null,
                        'card_type'   => null,
                        'card_class'  => null,
                        'card_family' => CreditCardInterface::CARD_FAMILY_PARAF,
                        'prices'      => [
                            ['installment' => 1, 'installment_price' => 100.0, 'total_price' => 100.0],
                            ['installment' => 2, 'installment_price' => 51.61, 'total_price' => 103.22],
                            ['installment' => 3, 'installment_price' => 35.08, 'total_price' => 105.25],
                        ],
                    ],
                    [
                        'bank_code'   => 134,
                        'bank_name'   => 'Denizbank',
                        'card_prefix' => null,
                        'card_type'   => null,
                        'card_class'  => null,
                        'card_family' => CreditCardInterface::CARD_FAMILY_BONUS,
                        'prices'      => [
                            ['installment' => 1, 'installment_price' => 100.0, 'total_price' => 100.0],
                            ['installment' => 2, 'installment_price' => 51.61, 'total_price' => 103.22],
                        ],
                    ],
                ],
            ],
        ];

        yield 'failure' => [
            'responseData' => [
                'status'       => 'failure',
                'errorCode'    => '10000',
                'errorMessage' => 'System Error',
            ],
            'expected' => [
                'status'             => 'declined',
                'error_message'      => 'System Error',
                'installment_prices' => [],
            ],
        ];
    }

    public static function mapHistoryResponseDataProvider(): \Generator
    {
        yield 'failure' => [
            'responseData' => [
                'status'         => 'failure',
                'errorCode'      => '10000',
                'errorMessage'   => 'System Error',
                'currentPage'    => null,
                'totalPageCount' => null,
            ],
            'expected' => [
                'proc_return_code' => 'failure',
                'error_code'       => '10000',
                'error_message'    => 'System Error',
                'status'           => 'declined',
                'trans_count'      => 0,
            ],
        ];

        yield 'success_single_payment' => [
            'responseData' => [
                'status'         => 'success',
                'currentPage'    => 1,
                'totalPageCount' => 1,
                'transactions'   => [
                    [
                        'transactionType'     => 'PAYMENT',
                        'transactionId'       => 1001,
                        'transactionStatus'   => 2,
                        'threeDS'             => 1,
                        'price'               => 10.01,
                        'paidPrice'           => 10.01,
                        'transactionCurrency' => 'TRY',
                        'installment'         => '1',
                        'transactionDate'     => '2024-05-06 10:00:00',
                    ],
                ],
            ],
            'expected' => [
                'proc_return_code' => 'success',
                'error_code'       => null,
                'error_message'    => null,
                'status'           => 'approved',
                'trans_count'      => 1,
            ],
        ];

        yield 'success_cancel' => [
            'responseData' => [
                'status'         => 'success',
                'currentPage'    => 1,
                'totalPageCount' => 1,
                'transactions'   => [
                    [
                        'transactionType'     => 'CANCEL',
                        'transactionId'       => 2001,
                        'threeDS'             => 0,
                        'price'               => 10.01,
                        'transactionCurrency' => 'TRY',
                        'installment'         => '0',
                        'transactionDate'     => '2024-05-06 10:00:00',
                    ],
                ],
            ],
            'expected' => [
                'proc_return_code' => 'success',
                'error_code'       => null,
                'error_message'    => null,
                'status'           => 'approved',
                'trans_count'      => 1,
            ],
        ];

        yield 'declined_payment' => [
            'responseData' => [
                'status'         => 'success',
                'currentPage'    => 1,
                'totalPageCount' => 1,
                'transactions'   => [
                    [
                        'transactionType'     => 'PAYMENT',
                        'transactionId'       => 3001,
                        'transactionStatus'   => 0,
                        'threeDS'             => 0,
                        'price'               => 10.01,
                        'transactionCurrency' => 'TRY',
                        'installment'         => '1',
                        'transactionDate'     => '2024-05-06 10:00:00',
                    ],
                ],
            ],
            'expected' => [
                'proc_return_code' => 'success',
                'error_code'       => null,
                'error_message'    => null,
                'status'           => 'approved',
                'trans_count'      => 1,
            ],
        ];

        yield 'success_refund' => [
            'responseData' => [
                'status'         => 'success',
                'currentPage'    => 1,
                'totalPageCount' => 1,
                'transactions'   => [
                    [
                        'transactionType'     => 'REFUND',
                        'transactionId'       => 4001,
                        'threeDS'             => 0,
                        'price'               => 10.01,
                        'transactionCurrency' => 'TRY',
                        'installment'         => '0',
                        'transactionDate'     => '2024-05-06 11:00:00',
                    ],
                ],
            ],
            'expected' => [
                'proc_return_code' => 'success',
                'error_code'       => null,
                'error_message'    => null,
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

    public static function mapBinListResponseDataProvider(): \Generator
    {
        yield 'success_credit_visa' => [
            'responseData' => [
                'status'          => 'success',
                'binNumber'       => '415956',
                'cardType'        => 'CREDIT_CARD',
                'cardAssociation' => 'VISA',
                'cardFamily'      => 'Garanti',
                'bankName'        => 'Garanti Bankası',
                'bankCode'        => 62,
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
                        'card_family' => 'Garanti',
                    ],
                ],
            ],
        ];

        yield 'success_debit_mastercard' => [
            'responseData' => [
                'status'          => 'success',
                'binNumber'       => '530596',
                'cardType'        => 'DEBIT_CARD',
                'cardAssociation' => 'MASTER_CARD',
                'cardFamily'      => 'Bonus',
                'bankName'        => 'Garanti Bankası',
                'bankCode'        => 62,
            ],
            'expected' => [
                'status'        => 'approved',
                'error_message' => null,
                'bin_list'      => [
                    [
                        'bin'         => '530596',
                        'bank_code'   => '62',
                        'bank_name'   => 'Garanti Bankası',
                        'card_type'   => CreditCardInterface::CARD_TYPE_MASTERCARD,
                        'card_class'  => CreditCardInterface::CARD_CLASS_DEBIT,
                        'card_family' => CreditCardInterface::CARD_FAMILY_BONUS,
                    ],
                ],
            ],
        ];

        yield 'failure' => [
            'responseData' => [
                'status'       => 'failure',
                'errorCode'    => 'BIN001',
                'errorMessage' => 'BIN not found',
            ],
            'expected' => [
                'status'        => 'declined',
                'error_message' => 'BIN not found',
                'bin_list'      => [],
            ],
        ];
    }
}
