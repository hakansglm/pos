<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\PosQuery\Response;

use Mews\Pos\DataMapper\PosQuery\Response\AbstractQueryResponseDataMapper;
use Mews\Pos\DataMapper\PosQuery\Response\ToslaPosQueryResponseDataMapper;
use Mews\Pos\Exception\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\ResponseValueFormatterFactory;
use Mews\Pos\Factory\ResponseValueMapperFactory;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\ToslaPos;
use Mews\Pos\Model\Card\CreditCardInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(ToslaPosQueryResponseDataMapper::class)]
#[CoversClass(AbstractQueryResponseDataMapper::class)]
class ToslaPosQueryResponseDataMapperTest extends TestCase
{
    private ToslaPosQueryResponseDataMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapper = new ToslaPosQueryResponseDataMapper(
            ResponseValueFormatterFactory::createForGateway(ToslaPos::class),
            ResponseValueMapperFactory::createForGateway(ToslaPos::class),
            new NullLogger()
        );
    }

    public function testSupports(): void
    {
        $this->assertTrue(ToslaPosQueryResponseDataMapper::supports(ToslaPos::class));
        $this->assertFalse(ToslaPosQueryResponseDataMapper::supports(AssecoPos::class));
    }

    public function testMapHistoryResponseThrows(): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->mapper->mapHistoryResponse([]);
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
        yield 'success' => [
            'responseData' => [
                'CardPrefix'         => 415956,
                'BankId'             => 13,
                'BankCode'           => '0111',
                'BankName'           => 'QNB FinansBank',
                'CardName'           => 'Card Finans',
                'CardClass'          => 'Kredi Kartı',
                'CardType'           => 'Visa',
                'Country'            => 'TR',
                'CommissionPackages' => [
                    [
                        'packageName'     => null,
                        'InstallmentRate' => [
                            // T1 is skipped (count < 2)
                            'T1'  => ['Rate' => 0, 'Constant' => 0],
                            'T2'  => ['Rate' => 1, 'Constant' => 0],
                            'T3'  => ['Rate' => 1, 'Constant' => 0],
                            'T4'  => ['Rate' => 1, 'Constant' => 0],
                            'T5'  => ['Rate' => 1, 'Constant' => 0],
                            'T6'  => ['Rate' => 1, 'Constant' => 0],
                            'T7'  => ['Rate' => 1, 'Constant' => 0],
                            'T8'  => ['Rate' => 1, 'Constant' => 0],
                            'T9'  => ['Rate' => 1, 'Constant' => 0],
                            'T10' => ['Rate' => 1, 'Constant' => 0],
                            'T11' => ['Rate' => 1, 'Constant' => 0],
                            'T12' => ['Rate' => 1, 'Constant' => 0],
                        ],
                        'BankCommission'  => 1.1,
                    ],
                ],
                'Code'               => 0,
                'Message'            => '',
            ],
            'expected'     => [
                'status'            => 'approved',
                'error_message'     => null,
                'installment_rates' => [
                    [
                        'bank_code'   => 111,
                        'bank_name'   => 'QNB FinansBank',
                        'card_prefix' => '415956',
                        'card_type'   => CreditCardInterface::CARD_TYPE_VISA,
                        'card_class'  => CreditCardInterface::CARD_CLASS_CREDIT,
                        'card_family' => null,
                        'rates'       => [
                            ['installment' => 2, 'rate' => 1.0, 'constant' => 0.0],
                            ['installment' => 3, 'rate' => 1.0, 'constant' => 0.0],
                            ['installment' => 4, 'rate' => 1.0, 'constant' => 0.0],
                            ['installment' => 5, 'rate' => 1.0, 'constant' => 0.0],
                            ['installment' => 6, 'rate' => 1.0, 'constant' => 0.0],
                            ['installment' => 7, 'rate' => 1.0, 'constant' => 0.0],
                            ['installment' => 8, 'rate' => 1.0, 'constant' => 0.0],
                            ['installment' => 9, 'rate' => 1.0, 'constant' => 0.0],
                            ['installment' => 10, 'rate' => 1.0, 'constant' => 0.0],
                            ['installment' => 11, 'rate' => 1.0, 'constant' => 0.0],
                            ['installment' => 12, 'rate' => 1.0, 'constant' => 0.0],
                        ],
                    ],
                ],
            ],
        ];

        yield 'success_no_installment_rates' => [
            'responseData' => [
                'CardPrefix'         => 415956,
                'BankId'             => 13,
                'BankCode'           => '0111',
                'BankName'           => 'QNB FinansBank',
                'CardName'           => 'Card Finans',
                'CardClass'          => 'Kredi Kartı',
                'CardType'           => 'Visa',
                'Country'            => 'TR',
                'CommissionPackages' => [
                    [
                        'packageName'     => null,
                        'InstallmentRate' => [],
                        'BankCommission'  => 1.1,
                    ],
                ],
                'Code'               => 0,
                'Message'            => '',
            ],
            'expected'     => [
                'status'            => 'approved',
                'error_message'     => null,
                'installment_rates' => [],
            ],
        ];

        yield 'failed' => [
            'responseData' => [
                'Code'    => 1,
                'Message' => 'Geçersiz istek',
            ],
            'expected'     => [
                'status'            => 'declined',
                'error_message'     => 'Geçersiz istek',
                'installment_rates' => [],
            ],
        ];
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
        yield 'success' => [
            'responseData' => [
                'IsExist'            => false,
                'PlatformId'         => 0,
                'BrandId'            => 0,
                'CardTypeId'         => 0,
                'InstallmentOptions' => [
                    ['Installment' => 1, 'Title' => 'Tek Çekim', 'Amount' => 10000, 'Currency' => 949],
                    ['Installment' => 2, 'Title' => '2 Taksit', 'Amount' => 10101, 'Currency' => 949],
                    ['Installment' => 3, 'Title' => '3 Taksit', 'Amount' => 10101, 'Currency' => 949],
                ],
                'Code'               => 0,
                'Message'            => 'Başarılı',
            ],
            'expected'     => [
                'status'             => 'approved',
                'error_message'      => null,
                'installment_prices' => [
                    [
                        'bank_code'   => null,
                        'bank_name'   => null,
                        'card_prefix' => null,
                        'card_type'   => null,
                        'card_class'  => null,
                        'card_family' => null,
                        'prices'      => [
                            ['installment' => 1, 'installment_price' => 10000.0, 'total_price' => 10000.0],
                            ['installment' => 2, 'installment_price' => 5050.5, 'total_price' => 10101.0],
                            ['installment' => 3, 'installment_price' => 3367.0, 'total_price' => 10101.0],
                        ],
                    ],
                ],
            ],
        ];

        yield 'failed' => [
            'responseData' => [
                'Code'    => 1,
                'Message' => 'Geçersiz istek',
            ],
            'expected'     => [
                'status'             => 'declined',
                'error_message'      => 'Geçersiz istek',
                'installment_prices' => [],
            ],
        ];
    }
}
