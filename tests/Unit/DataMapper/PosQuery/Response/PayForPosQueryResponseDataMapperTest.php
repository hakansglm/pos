<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\PosQuery\Response;

use DateTimeImmutable;
use Mews\Pos\DataMapper\PosQuery\Response\AbstractQueryResponseDataMapper;
use Mews\Pos\DataMapper\PosQuery\Response\PayForPosQueryResponseDataMapper;
use Mews\Pos\Exception\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\ResponseValueFormatterFactory;
use Mews\Pos\Factory\ResponseValueMapperFactory;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\PayForPos;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(PayForPosQueryResponseDataMapper::class)]
#[CoversClass(AbstractQueryResponseDataMapper::class)]
class PayForPosQueryResponseDataMapperTest extends TestCase
{
    private PayForPosQueryResponseDataMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapper = new PayForPosQueryResponseDataMapper(
            ResponseValueFormatterFactory::createForGateway(PayForPos::class),
            ResponseValueMapperFactory::createForGateway(PayForPos::class),
            new NullLogger()
        );
    }

    public function testSupports(): void
    {
        $this->assertTrue(PayForPosQueryResponseDataMapper::supports(PayForPos::class));
        $this->assertFalse(PayForPosQueryResponseDataMapper::supports(AssecoPos::class));
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

        foreach (\array_keys($actual['transactions']) as $key) {
            $this->assertEquals(
                $expected['transactions'][$key]['transaction_time'],
                $actual['transactions'][$key]['transaction_time'],
                'tx: '.$key
            );
            $this->assertEquals(
                $expected['transactions'][$key]['capture_time'],
                $actual['transactions'][$key]['capture_time'],
                'tx: '.$key
            );
            unset(
                $actual['transactions'][$key]['transaction_time'],
                $expected['transactions'][$key]['transaction_time'],
                $actual['transactions'][$key]['capture_time'],
                $expected['transactions'][$key]['capture_time']
            );
            \ksort($actual['transactions'][$key]);
            \ksort($expected['transactions'][$key]);
        }

        $this->assertArrayHasKey('all', $actual);
        $this->assertIsArray($actual['all']);
        $this->assertNotEmpty($actual['all']);
        unset($actual['all']);

        $this->assertSame($expected, $actual);
    }

    public static function mapHistoryResponseDataProvider(): array
    {
        return [
            'single_success_pay' => [
                'responseData' => \json_decode(
                    \file_get_contents(__DIR__.'/../../../test_data/payfor/history/success_pay_response.json'),
                    true
                ),
                'expected' => [
                    'proc_return_code' => '00',
                    'error_code'       => null,
                    'error_message'    => null,
                    'status'           => 'approved',
                    'trans_count'      => 1,
                    'transactions'     => [
                        [
                            'auth_code'        => 'S90726',
                            'proc_return_code' => '00',
                            'transaction_id'   => null,
                            'transaction_time' => new DateTimeImmutable('2024-01-21T21:40:47'),
                            'capture_time'     => new DateTimeImmutable('2024-01-21T21:40:47'),
                            'error_message'    => null,
                            'ref_ret_num'      => null,
                            'order_status'     => 'PAYMENT_COMPLETED',
                            'transaction_type' => 'pay',
                            'first_amount'     => 1.01,
                            'capture_amount'   => 1.01,
                            'status'           => 'approved',
                            'error_code'       => null,
                            'capture'          => true,
                            'currency'         => 'TRY',
                            'masked_number'    => '415565******6111',
                            'order_id'         => '202401212A22',
                        ],
                    ],
                ],
            ],
            'single_success_pre_pay' => [
                'responseData' => \json_decode(
                    \file_get_contents(__DIR__.'/../../../test_data/payfor/history/success_pre_pay_response.json'),
                    true
                ),
                'expected' => [
                    'proc_return_code' => '00',
                    'error_code'       => null,
                    'error_message'    => null,
                    'status'           => 'approved',
                    'trans_count'      => 1,
                    'transactions'     => [
                        [
                            'auth_code'        => 'S95711',
                            'proc_return_code' => '00',
                            'transaction_id'   => null,
                            'transaction_time' => new DateTimeImmutable('2024-01-21T21:59:31'),
                            'capture_time'     => null,
                            'error_message'    => null,
                            'ref_ret_num'      => null,
                            'order_status'     => 'PRE_AUTH_COMPLETED',
                            'transaction_type' => 'pre',
                            'first_amount'     => 2.01,
                            'capture_amount'   => null,
                            'status'           => 'approved',
                            'error_code'       => null,
                            'capture'          => false,
                            'currency'         => 'TRY',
                            'masked_number'    => '415565******6111',
                            'order_id'         => '2024012186F9',
                        ],
                    ],
                ],
            ],
            'single_failed_order_not_found' => [
                'responseData' => \json_decode(
                    \file_get_contents(__DIR__.'/../../../test_data/payfor/history/fail_order_not_found_response.json'),
                    true
                ),
                'expected' => [
                    'proc_return_code' => 'V013',
                    'error_code'       => 'V013',
                    'error_message'    => 'Seçili İşlem Bulunamadı!',
                    'status'           => 'declined',
                    'trans_count'      => 0,
                    'transactions'     => [],
                ],
            ],
            'daily_history' => [
                'responseData' => \json_decode(
                    \file_get_contents(__DIR__.'/../../../test_data/payfor/history/daily_history.json'),
                    true
                ),
                'expected' => [
                    'proc_return_code' => null,
                    'error_code'       => null,
                    'error_message'    => null,
                    'status'           => null,
                    'trans_count'      => 3,
                    'transactions'     => [
                        [
                            'auth_code'        => null,
                            'proc_return_code' => 'V000',
                            'transaction_id'   => null,
                            'transaction_time' => null,
                            'capture_time'     => null,
                            'error_message'    => null,
                            'ref_ret_num'      => null,
                            'order_status'     => null,
                            'transaction_type' => 'pay',
                            'first_amount'     => null,
                            'capture_amount'   => null,
                            'status'           => 'declined',
                            'error_code'       => 'V000',
                            'capture'          => null,
                            'currency'         => 'TRY',
                            'masked_number'    => null,
                            'order_id'         => '3450201880',
                        ],
                        [
                            'auth_code'        => null,
                            'proc_return_code' => 'V000',
                            'transaction_id'   => null,
                            'transaction_time' => null,
                            'capture_time'     => null,
                            'error_message'    => null,
                            'ref_ret_num'      => null,
                            'order_status'     => null,
                            'transaction_type' => 'pay',
                            'first_amount'     => null,
                            'capture_amount'   => null,
                            'status'           => 'declined',
                            'error_code'       => 'V000',
                            'capture'          => null,
                            'currency'         => 'TRY',
                            'masked_number'    => null,
                            'order_id'         => '1171158618',
                        ],
                        [
                            'auth_code'        => 'S70708',
                            'proc_return_code' => '00',
                            'transaction_id'   => null,
                            'transaction_time' => new DateTimeImmutable('2024-03-14T21:40:18'),
                            'capture_time'     => new DateTimeImmutable('2024-03-14T21:40:18'),
                            'error_message'    => null,
                            'ref_ret_num'      => null,
                            'order_status'     => 'PAYMENT_COMPLETED',
                            'transaction_type' => 'pay',
                            'first_amount'     => 100.0,
                            'capture_amount'   => 100.0,
                            'status'           => 'approved',
                            'error_code'       => null,
                            'capture'          => true,
                            'currency'         => 'TRY',
                            'masked_number'    => '415956******7732',
                            'order_id'         => '1427731461',
                        ],
                    ],
                ],
            ],
        ];
    }
}
