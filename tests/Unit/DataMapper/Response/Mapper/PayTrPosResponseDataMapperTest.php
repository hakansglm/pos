<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\Response\Mapper;

use DateTimeImmutable;
use Mews\Pos\DataMapper\Response\Mapper\AbstractResponseDataMapper;
use Mews\Pos\DataMapper\Response\Mapper\PayTrPosResponseDataMapper;
use Mews\Pos\DataMapper\Response\ValueFormatter\PayTrPosResponseValueFormatter;
use Mews\Pos\DataMapper\Response\ValueMapper\PayTrPosResponseValueMapper;
use Mews\Pos\Exception\NotImplementedException;
use Mews\Pos\Gateway\AkbankPos;
use Mews\Pos\Gateway\PayTrPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(PayTrPosResponseDataMapper::class)]
#[CoversClass(AbstractResponseDataMapper::class)]
class PayTrPosResponseDataMapperTest extends TestCase
{
    private PayTrPosResponseDataMapper $responseDataMapper;

    /** @var LoggerInterface&MockObject */
    private MockObject $loggerMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->responseDataMapper = new PayTrPosResponseDataMapper(
            new PayTrPosResponseValueFormatter(),
            new PayTrPosResponseValueMapper(),
            $this->loggerMock
        );
    }

    public function testSupports(): void
    {
        $this->assertTrue($this->responseDataMapper::supports(PayTrPos::class));
        $this->assertFalse($this->responseDataMapper::supports(AkbankPos::class));
    }

    public function testMap3DPaymentDataThrows(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->responseDataMapper->map3DPaymentData([], [], PosInterface::TX_TYPE_PAY_AUTH, []);
    }

    public function testMapCancelResponseThrows(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->responseDataMapper->mapCancelResponse([]);
    }

    public function testMapOrderHistoryResponseThrows(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->responseDataMapper->mapOrderHistoryResponse([]);
    }

    #[TestWith(["success", true])]
    #[TestWith(["failed", false])]
    #[TestWith(["error", false])]
    #[TestWith([null, false])]
    public function testIs3dAuthSuccess(?string $mdStatus, bool $expected): void
    {
        $this->assertSame($expected, $this->responseDataMapper->is3dAuthSuccess($mdStatus));
    }

    #[TestWith([["status" => "success"], "success"])]
    #[TestWith([["status" => "failed"], "failed"])]
    #[TestWith([[], null])]
    public function testExtractMdStatus(array $data, ?string $expected): void
    {
        $this->assertSame($expected, $this->responseDataMapper->extractMdStatus($data));
    }

    /**
     * @param array<string, mixed> $responseData
     * @param array<string, mixed> $order
     * @param array<string, mixed> $expectedData
     */
    #[DataProvider('paymentResponseDataProvider')]
    public function testMapPaymentResponse(array $responseData, array $order, array $expectedData): void
    {
        $before     = new DateTimeImmutable();
        $actualData = $this->responseDataMapper->mapPaymentResponse(
            $responseData,
            PosInterface::TX_TYPE_PAY_AUTH,
            $order
        );
        $after = new DateTimeImmutable();

        $this->assertArrayHasKey('all', $actualData);
        unset($actualData['all']);

        if ($actualData['transaction_time'] instanceof DateTimeImmutable) {
            $this->assertGreaterThanOrEqual($before->getTimestamp(), $actualData['transaction_time']->getTimestamp());
            $this->assertLessThanOrEqual($after->getTimestamp(), $actualData['transaction_time']->getTimestamp());
            unset($actualData['transaction_time'], $expectedData['transaction_time']);
        }

        \ksort($expectedData);
        \ksort($actualData);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @param array<string, mixed> $responseData
     * @param array<string, mixed> $expectedData
     */
    #[DataProvider('threeDHostResponseDataProvider')]
    public function testMap3DHostResponseData(array $responseData, array $expectedData): void
    {
        $before     = new DateTimeImmutable();
        $actualData = $this->responseDataMapper->map3DHostResponseData(
            $responseData,
            PosInterface::TX_TYPE_PAY_AUTH,
            []
        );
        $after = new DateTimeImmutable();

        $this->assertArrayHasKey('all', $actualData);
        unset($actualData['all']);

        if (isset($actualData['transaction_time']) && $actualData['transaction_time'] instanceof DateTimeImmutable) {
            $this->assertGreaterThanOrEqual($before->getTimestamp(), $actualData['transaction_time']->getTimestamp());
            $this->assertLessThanOrEqual($after->getTimestamp(), $actualData['transaction_time']->getTimestamp());
            unset($actualData['transaction_time'], $expectedData['transaction_time']);
        }

        \ksort($expectedData);
        \ksort($actualData);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @return array<string, array{responseData: array<string, mixed>, order: array<string, mixed>, expectedData: array<string, mixed>}>
     */
    public static function paymentResponseDataProvider(): array
    {
        $defaultExpected = [
            'order_id'          => null,
            'transaction_id'    => null,
            'transaction_time'  => null,
            'transaction_type'  => PosInterface::TX_TYPE_PAY_AUTH,
            'installment_count' => null,
            'currency'          => null,
            'amount'            => null,
            'payment_model'     => PosInterface::MODEL_NON_SECURE,
            'auth_code'         => null,
            'ref_ret_num'       => null,
            'batch_num'         => null,
            'proc_return_code'  => null,
            'status'            => AbstractResponseDataMapper::TX_DECLINED,
            'error_code'        => null,
            'error_message'     => null,
        ];

        return [
            'empty_response' => [
                'responseData' => [],
                'order'        => ['id' => 'order-1'],
                'expectedData' => $defaultExpected,
            ],
            'success' => [
                'responseData' => [
                    'merchant_oid'      => '20260623E335',
                    'status'            => 'success',
                    'total_amount'      => '1001',
                    'currency'          => 'TL',
                    'installment_count' => '0',
                ],
                'order'        => ['id' => '20260623E335'],
                'expectedData' => array_merge($defaultExpected, [
                    'order_id'          => '20260623E335',
                    'transaction_time'  => null, // asserted via range check, then unset
                    'installment_count' => 0,
                    'currency'          => PosInterface::CURRENCY_TRY,
                    'amount'            => 10.01,
                    'status'            => AbstractResponseDataMapper::TX_APPROVED,
                ]),
            ],
            'success_with_installment' => [
                'responseData' => [
                    'merchant_oid'      => 'order-inst',
                    'status'            => 'success',
                    'total_amount'      => '30000',
                    'currency'          => 'USD',
                    'installment_count' => '3',
                ],
                'order'        => ['id' => 'order-inst'],
                'expectedData' => array_merge($defaultExpected, [
                    'order_id'          => 'order-inst',
                    'transaction_time'  => null,
                    'installment_count' => 3,
                    'currency'          => PosInterface::CURRENCY_USD,
                    'amount'            => 300.0,
                    'status'            => AbstractResponseDataMapper::TX_APPROVED,
                ]),
            ],
            'failure_with_failed_reason_msg' => [
                'responseData' => [
                    'merchant_oid'       => 'order-fail',
                    'status'             => 'failed',
                    'failed_reason_code' => '51',
                    'failed_reason_msg'  => 'Insufficient funds',
                ],
                'order'        => ['id' => 'order-fail', 'amount' => 10.50, 'currency' => PosInterface::CURRENCY_TRY],
                'expectedData' => array_merge($defaultExpected, [
                    'order_id'     => 'order-fail',
                    'amount'       => null,
                    'currency'     => PosInterface::CURRENCY_TRY,
                    'error_code'   => '51',
                    'error_message' => 'Insufficient funds',
                ]),
            ],
            'failure_no_merchant_oid_uses_order_id' => [
                'responseData' => [
                    'status'             => 'failed',
                    'failed_reason_code' => '99',
                ],
                'order'        => ['id' => 'fallback-id'],
                'expectedData' => array_merge($defaultExpected, [
                    'order_id'   => 'fallback-id',
                    'error_code' => '99',
                ]),
            ],
        ];
    }

    /**
     * @return array<string, array{responseData: array<string, mixed>, expectedData: array<string, mixed>}>
     */
    public static function threeDHostResponseDataProvider(): array
    {
        $defaultExpected = [
            'order_id'             => null,
            'transaction_id'       => null,
            'transaction_time'     => null,
            'transaction_type'     => PosInterface::TX_TYPE_PAY_AUTH,
            'installment_count'    => null,
            'currency'             => null,
            'amount'               => null,
            'payment_model'        => PosInterface::MODEL_3D_HOST,
            'auth_code'            => null,
            'ref_ret_num'          => null,
            'batch_num'            => null,
            'proc_return_code'     => null,
            'status'               => AbstractResponseDataMapper::TX_DECLINED,
            'error_code'           => null,
            'error_message'        => null,
            'md_status'            => null,
            'md_error_message'     => null,
            'transaction_security' => null,
        ];

        return [
            'empty_callback' => [
                'responseData' => [],
                'expectedData' => $defaultExpected,
            ],
            'single_fail_message' => [
                'responseData' => ['fail_message' => '3D auth rejected by bank'],
                'expectedData' => array_merge($defaultExpected, [
                    'md_error_message' => '3D auth rejected by bank',
                ]),
            ],
            'success' => [
                'responseData' => [
                    'hash'              => '+jxQ32k1XbdS6i7iBQecCZ7fFhRrStaZBMvbsRzRVZE=',
                    'merchant_oid'      => '20260624B505',
                    'status'            => 'success',
                    'total_amount'      => '1001',
                    'payment_type'      => 'card',
                    'payment_amount'    => '1001',
                    'currency'          => 'TL',
                    'installment_count' => '1',
                    'merchant_id'       => '123456',
                    'test_mode'         => '1',
                ],
                'expectedData' => array_merge($defaultExpected, [
                    'order_id'          => '20260624B505',
                    'transaction_time'  => null, // asserted via range check, then unset
                    'installment_count' => 0,
                    'currency'          => PosInterface::CURRENCY_TRY,
                    'amount'            => 10.01,
                    'status'            => AbstractResponseDataMapper::TX_APPROVED,
                ]),
            ],
            'failure' => [
                'responseData' => [
                    'merchant_oid'       => 'order-host-fail',
                    'status'             => 'failed',
                    'total_amount'       => '0',
                    'failed_reason_code' => '05',
                    'failed_reason_msg'  => 'Do not honour',
                    'currency'           => 'TL',
                    'installment_count'  => '0',
                    'hash'               => 'bad-hash',
                ],
                'expectedData' => array_merge($defaultExpected, [
                    'order_id'      => 'order-host-fail',
                    'error_code'    => '05',
                    'error_message' => 'Do not honour',
                ]),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $responseData
     * @param array<string, mixed> $expectedData
     */
    #[DataProvider('statusResponseDataProvider')]
    public function testMapStatusResponse(array $responseData, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->mapStatusResponse($responseData);

        $this->assertArrayHasKey('all', $actualData);
        unset($actualData['all']);

        foreach (['transaction_time', 'capture_time', 'refund_time'] as $dtKey) {
            $this->assertEquals($expectedData[$dtKey], $actualData[$dtKey]);
            $actualData[$dtKey] = $expectedData[$dtKey];
        }

        \ksort($expectedData);
        \ksort($actualData);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @param array<string, mixed> $responseData
     * @param array<string, mixed> $expectedData
     */
    #[DataProvider('threeDPayResponseDataProvider')]
    public function testMap3DPayResponseData(array $responseData, array $expectedData): void
    {
        $before     = new DateTimeImmutable();
        $actualData = $this->responseDataMapper->map3DPayResponseData(
            $responseData,
            PosInterface::TX_TYPE_PAY_AUTH,
            []
        );
        $after = new DateTimeImmutable();

        $this->assertArrayHasKey('all', $actualData);
        unset($actualData['all']);

        if (isset($actualData['transaction_time'])) {
            $this->assertInstanceOf(DateTimeImmutable::class, $actualData['transaction_time']);
            $this->assertGreaterThanOrEqual($before->getTimestamp(), $actualData['transaction_time']->getTimestamp());
            $this->assertLessThanOrEqual($after->getTimestamp(), $actualData['transaction_time']->getTimestamp());
            unset($actualData['transaction_time'], $expectedData['transaction_time']);
        }

        \ksort($expectedData);
        \ksort($actualData);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @param array<string, mixed> $responseData
     * @param array<string, mixed> $expectedData
     */
    #[DataProvider('refundResponseDataProvider')]
    public function testMapRefundResponse(array $responseData, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->mapRefundResponse($responseData);

        $this->assertArrayHasKey('all', $actualData);
        unset($actualData['all']);

        \ksort($expectedData);
        \ksort($actualData);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @param array<string, mixed> $responseData
     * @param array<string, mixed> $expectedData
     */
    #[DataProvider('historyResponseDataProvider')]
    public function testMapHistoryResponse(array $responseData, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->mapHistoryResponse($responseData);

        $this->assertArrayHasKey('all', $actualData);
        unset($actualData['all']);

        $actualTransactions   = $actualData['transactions'] ?? [];
        $expectedTransactions = $expectedData['transactions'] ?? [];
        unset($actualData['transactions'], $expectedData['transactions']);

        $this->assertCount(\count($expectedTransactions), $actualTransactions);

        foreach ($expectedTransactions as $i => $expectedTx) {
            $actualTx = $actualTransactions[$i];
            if (isset($actualTx['transaction_time'])) {
                $this->assertInstanceOf(DateTimeImmutable::class, $actualTx['transaction_time']);
                $this->assertEquals($expectedTx['transaction_time'], $actualTx['transaction_time']);
                $actualTx['transaction_time'] = $expectedTx['transaction_time'];
            }

            \ksort($actualTx);
            \ksort($expectedTx);
            $this->assertSame($expectedTx, $actualTx, sprintf('Transaction %s mismatch', $i));
        }

        \ksort($expectedData);
        \ksort($actualData);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @return array<string, array{responseData: array<string, mixed>, expectedData: array<string, mixed>}>
     */
    public static function threeDPayResponseDataProvider(): array
    {
        $defaultExpected = [
            'order_id'             => null,
            'transaction_id'       => null,
            'transaction_time'     => null,
            'transaction_type'     => PosInterface::TX_TYPE_PAY_AUTH,
            'installment_count'    => null,
            'currency'             => null,
            'amount'               => null,
            'payment_model'        => PosInterface::MODEL_3D_PAY,
            'auth_code'            => null,
            'ref_ret_num'          => null,
            'batch_num'            => null,
            'proc_return_code'     => null,
            'status'               => AbstractResponseDataMapper::TX_DECLINED,
            'error_code'           => null,
            'error_message'        => null,
            'md_status'            => null,
            'md_error_message'     => null,
            'transaction_security' => null,
        ];

        return [
            'empty_callback_data' => [
                'responseData' => [],
                'expectedData' => $defaultExpected,
            ],
            'single_key_fail_message' => [
                'responseData' => ['fail_message' => 'Test modu hata mesajı. (Gerçek modda ilgili hata mesajı dönecektir)'],
                'expectedData' => array_merge($defaultExpected, [
                    'md_error_message' => 'Test modu hata mesajı. (Gerçek modda ilgili hata mesajı dönecektir)',
                ]),
            ],
            'success' => [
                'responseData' => [
                    'hash'              => 'kwKu739W963OrNp5CSIecYlBpp8x2mql1wDWtSWZhjc=',
                    'merchant_oid'      => '20260623E335',
                    'status'            => 'success',
                    'total_amount'      => '1001',
                    'payment_type'      => 'card',
                    'payment_amount'    => '1001',
                    'currency'          => 'TL',
                    'installment_count' => '1',
                    'merchant_id'       => '123456',
                    'test_mode'         => '1',
                ],
                'expectedData' => [
                    'order_id'             => '20260623E335',
                    'transaction_id'       => null,
                    'transaction_time'     => null, // replaced by range-assertion + unset in test
                    'transaction_type'     => PosInterface::TX_TYPE_PAY_AUTH,
                    'installment_count'    => 0,
                    'currency'             => PosInterface::CURRENCY_TRY,
                    'amount'               => 10.01,
                    'payment_model'        => PosInterface::MODEL_3D_PAY,
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'batch_num'            => null,
                    'proc_return_code'     => null,
                    'status'               => AbstractResponseDataMapper::TX_APPROVED,
                    'error_code'           => null,
                    'error_message'        => null,
                    'md_status'            => null,
                    'md_error_message'     => null,
                    'transaction_security' => null,
                ],
            ],
            'failure' => [
                'responseData' => [
                    'merchant_oid'       => 'test-order-456',
                    'status'             => 'failed',
                    'total_amount'       => '0',
                    'hash'               => 'xyz789hash',
                    'payment_type'       => '',
                    'failed_reason_code' => '51',
                    'failed_reason_msg'  => 'Insufficient funds',
                    'test_mode'          => '1',
                    'currency'           => 'TL',
                    'installment_count'  => '0',
                ],
                'expectedData' => [
                    'order_id'             => 'test-order-456',
                    'transaction_id'       => null,
                    'transaction_time'     => null,
                    'transaction_type'     => PosInterface::TX_TYPE_PAY_AUTH,
                    'installment_count'    => null,
                    'currency'             => null,
                    'amount'               => null,
                    'payment_model'        => PosInterface::MODEL_3D_PAY,
                    'auth_code'            => null,
                    'ref_ret_num'          => null,
                    'batch_num'            => null,
                    'proc_return_code'     => null,
                    'status'               => AbstractResponseDataMapper::TX_DECLINED,
                    'error_code'           => '51',
                    'error_message'        => 'Insufficient funds',
                    'md_status'            => null,
                    'md_error_message'     => null,
                    'transaction_security' => null,
                ],
            ],
        ];
    }

    /**
     * @return array<string, array{responseData: array<string, mixed>, expectedData: array<string, mixed>}>
     */
    public static function statusResponseDataProvider(): array
    {
        return [
            'success_payment_completed_no_refund' => [
                'responseData' => [
                    'status'         => 'success',
                    'net_tutar'      => '0.00',
                    'kesinti_tutari' => '0.00',
                    'kesinti_orani'  => '0.00',
                    'payment_amount' => '10.01',
                    'payment_total'  => '10.01',
                    'payment_date'   => '23.06.2026',
                    'auth_date'      => '23.06.2026',
                    'auth_code'      => 'XXXXXX',
                    'currency'       => 'TL',
                    'taksit'         => '0',
                    'kart_marka'     => '',
                    'masked_pan'     => null,
                    'odeme_tipi'     => 'KART',
                    'test_mode'      => '1',
                    'returns'        => [],
                ],
                'expectedData' => [
                    'order_id'          => null,
                    'auth_code'         => null,
                    'proc_return_code'  => 'success',
                    'transaction_id'    => null,
                    'transaction_type'  => null,
                    'transaction_time'  => new DateTimeImmutable('23.06.2026'),
                    'capture_time'      => new DateTimeImmutable('23.06.2026'),
                    'ref_ret_num'       => null,
                    'order_status'      => PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED,
                    'first_amount'      => 10.01,
                    'capture_amount'    => 10.01,
                    'status'            => AbstractResponseDataMapper::TX_APPROVED,
                    'error_code'        => null,
                    'error_message'     => null,
                    'capture'           => true,
                    'currency'          => PosInterface::CURRENCY_TRY,
                    'masked_number'     => null,
                    'refund_amount'     => null,
                    'refund_time'       => null,
                    'cancel_time'       => null,
                    'installment_count' => 0,
                ],
            ],
            'success_payment_completed_with_installment' => [
                'responseData' => [
                    'status'         => 'success',
                    'net_tutar'      => '0.00',
                    'kesinti_tutari' => '0.00',
                    'kesinti_orani'  => '0.00',
                    'payment_amount' => '100.00',
                    'payment_total'  => '100.00',
                    'payment_date'   => '23.06.2026',
                    'auth_date'      => '23.06.2026',
                    'auth_code'      => 'YYYYYY',
                    'currency'       => 'TL',
                    'taksit'         => '3',
                    'kart_marka'     => 'VISA',
                    'masked_pan'     => '411111****1111',
                    'odeme_tipi'     => 'KART',
                    'test_mode'      => '1',
                    'returns'        => [],
                ],
                'expectedData' => [
                    'order_id'          => null,
                    'auth_code'         => null,
                    'proc_return_code'  => 'success',
                    'transaction_id'    => null,
                    'transaction_type'  => null,
                    'transaction_time'  => new DateTimeImmutable('23.06.2026'),
                    'capture_time'      => new DateTimeImmutable('23.06.2026'),
                    'ref_ret_num'       => null,
                    'order_status'      => PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED,
                    'first_amount'      => 100.0,
                    'capture_amount'    => 100.0,
                    'status'            => AbstractResponseDataMapper::TX_APPROVED,
                    'error_code'        => null,
                    'error_message'     => null,
                    'capture'           => true,
                    'currency'          => PosInterface::CURRENCY_TRY,
                    'masked_number'     => '411111****1111',
                    'refund_amount'     => null,
                    'refund_time'       => null,
                    'cancel_time'       => null,
                    'installment_count' => 3,
                ],
            ],
            'success_partially_refunded' => [
                'responseData' => [
                    'status'         => 'success',
                    'net_tutar'      => '0.00',
                    'kesinti_tutari' => '0.00',
                    'kesinti_orani'  => '0.00',
                    'payment_amount' => '10.00',
                    'payment_total'  => '10.00',
                    'payment_date'   => '23.06.2026',
                    'auth_date'      => '23.06.2026',
                    'auth_code'      => 'ZZZZZZ',
                    'currency'       => 'TL',
                    'taksit'         => '0',
                    'kart_marka'     => '',
                    'masked_pan'     => null,
                    'odeme_tipi'     => 'KART',
                    'test_mode'      => '1',
                    'returns'        => [
                        // PayTR sends refund amounts in the returns array as integer cents (÷100 via TX_TYPE_PAY_AUTH)
                        ['refund_amount' => '500', 'return_date' => '24.06.2026'],
                    ],
                ],
                'expectedData' => [
                    'order_id'          => null,
                    'auth_code'         => null,
                    'proc_return_code'  => 'success',
                    'transaction_id'    => null,
                    'transaction_type'  => null,
                    'transaction_time'  => new DateTimeImmutable('23.06.2026'),
                    'capture_time'      => new DateTimeImmutable('23.06.2026'),
                    'ref_ret_num'       => null,
                    'order_status'      => PosInterface::PAYMENT_STATUS_PARTIALLY_REFUNDED,
                    'first_amount'      => 10.0,
                    'capture_amount'    => 10.0,
                    'status'            => AbstractResponseDataMapper::TX_APPROVED,
                    'error_code'        => null,
                    'error_message'     => null,
                    'capture'           => true,
                    'currency'          => PosInterface::CURRENCY_TRY,
                    'masked_number'     => null,
                    'refund_amount'     => 5.0,
                    'refund_time'       => new DateTimeImmutable('24.06.2026'),
                    'cancel_time'       => null,
                    'installment_count' => 0,
                ],
            ],
            'success_fully_refunded' => [
                'responseData' => [
                    'status'         => 'success',
                    'net_tutar'      => '0.00',
                    'kesinti_tutari' => '0.00',
                    'kesinti_orani'  => '0.00',
                    'payment_amount' => '10.00',
                    'payment_total'  => '10.00',
                    'payment_date'   => '23.06.2026',
                    'auth_date'      => '23.06.2026',
                    'auth_code'      => 'AAAAAA',
                    'currency'       => 'TL',
                    'taksit'         => '0',
                    'kart_marka'     => '',
                    'masked_pan'     => null,
                    'odeme_tipi'     => 'KART',
                    'test_mode'      => '1',
                    'returns'        => [
                        // 1000 cents = 10.00 TL, equals payment_total → FULLY_REFUNDED
                        ['refund_amount' => '1000', 'return_date' => '25.06.2026'],
                    ],
                ],
                'expectedData' => [
                    'order_id'          => null,
                    'auth_code'         => null,
                    'proc_return_code'  => 'success',
                    'transaction_id'    => null,
                    'transaction_type'  => null,
                    'transaction_time'  => new DateTimeImmutable('23.06.2026'),
                    'capture_time'      => new DateTimeImmutable('23.06.2026'),
                    'ref_ret_num'       => null,
                    'order_status'      => PosInterface::PAYMENT_STATUS_FULLY_REFUNDED,
                    'first_amount'      => 10.0,
                    'capture_amount'    => 10.0,
                    'status'            => AbstractResponseDataMapper::TX_APPROVED,
                    'error_code'        => null,
                    'error_message'     => null,
                    'capture'           => true,
                    'currency'          => PosInterface::CURRENCY_TRY,
                    'masked_number'     => null,
                    'refund_amount'     => 10.0,
                    'refund_time'       => new DateTimeImmutable('25.06.2026'),
                    'cancel_time'       => null,
                    'installment_count' => 0,
                ],
            ],
            'failure_order_not_found' => [
                'responseData' => [
                    'status'  => 'error',
                    'err_no'  => '004',
                    'err_msg' => 'merchant_oid ile basarili odeme bulunamadi',
                ],
                'expectedData' => [
                    'order_id'          => null,
                    'auth_code'         => null,
                    'proc_return_code'  => null,
                    'transaction_id'    => null,
                    'transaction_type'  => null,
                    'transaction_time'  => null,
                    'capture_time'      => null,
                    'ref_ret_num'       => null,
                    'order_status'      => null,
                    'first_amount'      => null,
                    'capture_amount'    => null,
                    'status'            => AbstractResponseDataMapper::TX_DECLINED,
                    'error_code'        => '004',
                    'error_message'     => 'merchant_oid ile basarili odeme bulunamadi',
                    'capture'           => null,
                    'currency'          => null,
                    'masked_number'     => null,
                    'refund_amount'     => null,
                    'refund_time'       => null,
                    'cancel_time'       => null,
                    'installment_count' => null,
                ],
            ],
            // Covers null branches: no payment_amount, payment_total, currency, payment_date, taksit
            'success_minimal_fields_no_optional' => [
                'responseData' => [
                    'status'  => 'success',
                    'returns' => [],
                ],
                'expectedData' => [
                    'order_id'          => null,
                    'auth_code'         => null,
                    'proc_return_code'  => 'success',
                    'transaction_id'    => null,
                    'transaction_type'  => null,
                    'transaction_time'  => null,
                    'capture_time'      => null,
                    'ref_ret_num'       => null,
                    'order_status'      => PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED,
                    'first_amount'      => null,
                    'capture_amount'    => null,
                    'status'            => AbstractResponseDataMapper::TX_APPROVED,
                    'error_code'        => null,
                    'error_message'     => null,
                    'capture'           => false,
                    'currency'          => null,
                    'masked_number'     => null,
                    'refund_amount'     => null,
                    'refund_time'       => null,
                    'cancel_time'       => null,
                    'installment_count' => null,
                ],
            ],
        ];
    }

    /**
     * @return array<string, array{responseData: array<string, mixed>, expectedData: array<string, mixed>}>
     */
    public static function refundResponseDataProvider(): array
    {
        return [
            'success' => [
                'responseData' => [
                    'status'        => 'success',
                    'is_test'       => '1',
                    'merchant_oid'  => '2026062350B3',
                    'return_amount' => '6.00',
                    'reference_no'  => null,
                ],
                'expectedData' => [
                    'order_id'      => '2026062350B3',
                    'status'        => AbstractResponseDataMapper::TX_APPROVED,
                    'refund_amount' => 6.0,
                    'ref_ret_num'   => null,
                    'error_code'    => null,
                    'error_message' => null,
                ],
            ],
            'failure_amount_exceeds_remaining' => [
                'responseData' => [
                    'status'  => 'error',
                    'err_no'  => '009',
                    'err_msg' => 'Talep edilen iade tutari (12 TL), kalan tutardan (10.01 TL) fazla olamaz.',
                ],
                'expectedData' => [
                    'order_id'      => null,
                    'status'        => AbstractResponseDataMapper::TX_DECLINED,
                    'refund_amount' => null,
                    'ref_ret_num'   => null,
                    'error_code'    => '009',
                    'error_message' => 'Talep edilen iade tutari (12 TL), kalan tutardan (10.01 TL) fazla olamaz.',
                ],
            ],
        ];
    }

    /**
     * @return array<string, array{responseData: array<string, mixed>, expectedData: array<string, mixed>}>
     */
    public static function historyResponseDataProvider(): array
    {
        return [
            'success_with_transactions' => [
                'responseData' => [
                    'status' => 'success',
                    'list'   => [
                        [
                            'islem_tipi'   => 'S',
                            'islem_tutari' => '10.00',
                            'odeme_tutari' => '10.00',
                            'net_tutar'    => '9.76',
                            'islem_tarihi' => '23.06.2026',
                            'para_birimi'  => 'TL',
                            'taksit'       => '0',
                            'kart_no'      => '455359AAA6747',
                            'siparis_no'   => 'ABC123',
                            'odeme_tipi'   => 'KART',
                        ],
                        [
                            'islem_tipi'   => 'I',
                            'islem_tutari' => '5.00',
                            'odeme_tutari' => '5.00',
                            'net_tutar'    => '5.00',
                            'islem_tarihi' => '23.06.2026',
                            'para_birimi'  => 'USD',
                            'taksit'       => '3',
                            'kart_no'      => '411111XXXXXX1111',
                            'siparis_no'   => 'DEF456',
                            'odeme_tipi'   => 'KART',
                        ],
                    ],
                ],
                'expectedData' => [
                    'proc_return_code' => 'success',
                    'error_code'       => null,
                    'error_message'    => null,
                    'trans_count'      => 2,
                    'status'           => AbstractResponseDataMapper::TX_APPROVED,
                    'transactions'     => [
                        [
                            'order_id'          => 'ABC123',
                            'transaction_type'  => PosInterface::TX_TYPE_PAY_AUTH,
                            'transaction_time'  => new DateTimeImmutable('23.06.2026'),
                            'first_amount'      => 10.0,
                            'capture_amount'    => 10.0,
                            'currency'          => PosInterface::CURRENCY_TRY,
                            'masked_number'     => '455359AAA6747',
                            'installment_count' => 0,
                            'auth_code'         => null,
                            'proc_return_code'  => null,
                            'transaction_id'    => null,
                            'capture_time'      => null,
                            'error_message'     => null,
                            'ref_ret_num'       => null,
                            'order_status'      => null,
                            'status'            => AbstractResponseDataMapper::TX_APPROVED,
                            'error_code'        => null,
                            'capture'           => null,
                        ],
                        [
                            'order_id'          => 'DEF456',
                            'transaction_type'  => PosInterface::TX_TYPE_REFUND,
                            'transaction_time'  => new DateTimeImmutable('23.06.2026'),
                            'first_amount'      => 5.0,
                            'capture_amount'    => 5.0,
                            'currency'          => PosInterface::CURRENCY_USD,
                            'masked_number'     => '411111XXXXXX1111',
                            'installment_count' => 3,
                            'auth_code'         => null,
                            'proc_return_code'  => null,
                            'transaction_id'    => null,
                            'capture_time'      => null,
                            'error_message'     => null,
                            'ref_ret_num'       => null,
                            'order_status'      => null,
                            'status'            => AbstractResponseDataMapper::TX_APPROVED,
                            'error_code'        => null,
                            'capture'           => null,
                        ],
                    ],
                ],
            ],
            'failed_no_transactions' => [
                'responseData' => [
                    'status' => 'failed',
                ],
                'expectedData' => [
                    'status'           => AbstractResponseDataMapper::TX_DECLINED,
                    'proc_return_code' => 'failed',
                    'error_code'       => 'failed',
                    'error_message'    => null,
                    'trans_count'      => 0,
                    'transactions'     => [],
                ],
            ],
            'error_response' => [
                'responseData' => [
                    'status' => 'error',
                    'err_no' => '204',
                    'err_msg' => 'start_date ile end_date arasi 3 gunden fazla olamaz merchant_id',
                ],
                'expectedData' => [
                    'status'           => AbstractResponseDataMapper::TX_DECLINED,
                    'proc_return_code' => 'error',
                    'error_code'       => '204',
                    'error_message'    => 'start_date ile end_date arasi 3 gunden fazla olamaz merchant_id',
                    'trans_count'      => 0,
                    'transactions'     => [],
                ],
            ],
            // Covers null branches: no islem_tipi, para_birimi, islem_tarihi, islem_tutari, odeme_tutari, taksit
            'success_transaction_with_missing_optional_fields' => [
                'responseData' => [
                    'status' => 'success',
                    'list'   => [
                        ['siparis_no' => 'MINIMAL-TX'],
                    ],
                ],
                'expectedData' => [
                    'proc_return_code' => 'success',
                    'error_code'       => null,
                    'error_message'    => null,
                    'trans_count'      => 1,
                    'status'           => AbstractResponseDataMapper::TX_APPROVED,
                    'transactions'     => [
                        [
                            'order_id'          => 'MINIMAL-TX',
                            'transaction_type'  => null,
                            'transaction_time'  => null,
                            'first_amount'      => null,
                            'capture_amount'    => null,
                            'currency'          => null,
                            'masked_number'     => null,
                            'installment_count' => null,
                            'auth_code'         => null,
                            'proc_return_code'  => null,
                            'transaction_id'    => null,
                            'capture_time'      => null,
                            'error_message'     => null,
                            'ref_ret_num'       => null,
                            'order_status'      => null,
                            'status'            => AbstractResponseDataMapper::TX_APPROVED,
                            'error_code'        => null,
                            'capture'           => null,
                        ],
                    ],
                ],
            ],
            'success_real_sale_and_refund_transactions' => [
                'responseData' => [
                    'status' => 'success',
                    'list'   => [
                        // S (sale) transactions — amounts 1.00–100.00 TL; net_tutar can be negative when fee > amount
                        ['islem_tipi' => 'S', 'net_tutar' => -9,  'kesinti_tutari' => 5, 'kesinti_orani' => 2, 'islem_tutari' => '1.00',   'odeme_tutari' => '1.00',   'islem_tarihi' => '24.06.2026', 'para_birimi' => 'TL', 'taksit' => '0', 'kart_marka' => 'WORLD', 'kart_no' => '979203***0796', 'siparis_no' => 'c4ec9590b81811bd37cd01de13c1f9ab', 'odeme_tipi' => 'KART'],
                        ['islem_tipi' => 'S', 'net_tutar' => -8,  'kesinti_tutari' => 5, 'kesinti_orani' => 2, 'islem_tutari' => '2.00',   'odeme_tutari' => '2.00',   'islem_tarihi' => '24.06.2026', 'para_birimi' => 'TL', 'taksit' => '0', 'kart_marka' => 'WORLD', 'kart_no' => '979203***0796', 'siparis_no' => 'a13e0a19a4ed497cf7e5ce3580ab1866', 'odeme_tipi' => 'KART'],
                        ['islem_tipi' => 'S', 'net_tutar' => -5,  'kesinti_tutari' => 5, 'kesinti_orani' => 2, 'islem_tutari' => '5.00',   'odeme_tutari' => '5.00',   'islem_tarihi' => '24.06.2026', 'para_birimi' => 'TL', 'taksit' => '0', 'kart_marka' => 'WORLD', 'kart_no' => '979203***0796', 'siparis_no' => 'f61ddaddf753c2eac4eca04764c5ea36', 'odeme_tipi' => 'KART'],
                        ['islem_tipi' => 'S', 'net_tutar' => 0,   'kesinti_tutari' => 5, 'kesinti_orani' => 2, 'islem_tutari' => '10.00',  'odeme_tutari' => '10.00',  'islem_tarihi' => '24.06.2026', 'para_birimi' => 'TL', 'taksit' => '0', 'kart_marka' => 'WORLD', 'kart_no' => '979203***0796', 'siparis_no' => 'dbe3776cbd0cc28258d3a41a1f131836', 'odeme_tipi' => 'KART'],
                        ['islem_tipi' => 'S', 'net_tutar' => 90,  'kesinti_tutari' => 5, 'kesinti_orani' => 2, 'islem_tutari' => '100.00', 'odeme_tutari' => '100.00', 'islem_tarihi' => '24.06.2026', 'para_birimi' => 'TL', 'taksit' => '0', 'kart_marka' => 'WORLD', 'kart_no' => '979203***0796', 'siparis_no' => 'e464176d290d43990cc2bcd0f0416244', 'odeme_tipi' => 'KART'],
                        // I (iade/refund) transactions — all 10 from the response
                        ['islem_tipi' => 'I', 'net_tutar' => 1,   'kesinti_tutari' => 5, 'kesinti_orani' => 2, 'islem_tutari' => '11.00',  'odeme_tutari' => '11.00',  'islem_tarihi' => '24.06.2026', 'para_birimi' => 'TL', 'taksit' => '0', 'kart_marka' => 'WORLD', 'kart_no' => '979203***0796', 'siparis_no' => 'ee1cb4946480b49064f18074f3fd30d3', 'odeme_tipi' => 'KART'],
                        ['islem_tipi' => 'I', 'net_tutar' => 12,  'kesinti_tutari' => 5, 'kesinti_orani' => 2, 'islem_tutari' => '22.00',  'odeme_tutari' => '22.00',  'islem_tarihi' => '24.06.2026', 'para_birimi' => 'TL', 'taksit' => '0', 'kart_marka' => 'WORLD', 'kart_no' => '979203***0796', 'siparis_no' => '91dc444670805428cee67b30ab2ae403', 'odeme_tipi' => 'KART'],
                        ['islem_tipi' => 'I', 'net_tutar' => 64,  'kesinti_tutari' => 5, 'kesinti_orani' => 2, 'islem_tutari' => '74.00',  'odeme_tutari' => '74.00',  'islem_tarihi' => '24.06.2026', 'para_birimi' => 'TL', 'taksit' => '0', 'kart_marka' => 'WORLD', 'kart_no' => '979203***0796', 'siparis_no' => 'ddafc553d777c99be952c0f6555c2a92', 'odeme_tipi' => 'KART'],
                    ],
                ],
                'expectedData' => [
                    'proc_return_code' => 'success',
                    'error_code'       => null,
                    'error_message'    => null,
                    'trans_count'      => 8,
                    'status'           => AbstractResponseDataMapper::TX_APPROVED,
                    'transactions'     => [
                        ['order_id' => 'c4ec9590b81811bd37cd01de13c1f9ab', 'transaction_type' => PosInterface::TX_TYPE_PAY_AUTH, 'transaction_time' => new DateTimeImmutable('24.06.2026'), 'first_amount' => 1.0,   'capture_amount' => 1.0,   'currency' => PosInterface::CURRENCY_TRY, 'masked_number' => '979203***0796', 'installment_count' => 0, 'auth_code' => null, 'proc_return_code' => null, 'transaction_id' => null, 'capture_time' => null, 'error_message' => null, 'ref_ret_num' => null, 'order_status' => null, 'status' => AbstractResponseDataMapper::TX_APPROVED, 'error_code' => null, 'capture' => null],
                        ['order_id' => 'a13e0a19a4ed497cf7e5ce3580ab1866', 'transaction_type' => PosInterface::TX_TYPE_PAY_AUTH, 'transaction_time' => new DateTimeImmutable('24.06.2026'), 'first_amount' => 2.0,   'capture_amount' => 2.0,   'currency' => PosInterface::CURRENCY_TRY, 'masked_number' => '979203***0796', 'installment_count' => 0, 'auth_code' => null, 'proc_return_code' => null, 'transaction_id' => null, 'capture_time' => null, 'error_message' => null, 'ref_ret_num' => null, 'order_status' => null, 'status' => AbstractResponseDataMapper::TX_APPROVED, 'error_code' => null, 'capture' => null],
                        ['order_id' => 'f61ddaddf753c2eac4eca04764c5ea36', 'transaction_type' => PosInterface::TX_TYPE_PAY_AUTH, 'transaction_time' => new DateTimeImmutable('24.06.2026'), 'first_amount' => 5.0,   'capture_amount' => 5.0,   'currency' => PosInterface::CURRENCY_TRY, 'masked_number' => '979203***0796', 'installment_count' => 0, 'auth_code' => null, 'proc_return_code' => null, 'transaction_id' => null, 'capture_time' => null, 'error_message' => null, 'ref_ret_num' => null, 'order_status' => null, 'status' => AbstractResponseDataMapper::TX_APPROVED, 'error_code' => null, 'capture' => null],
                        ['order_id' => 'dbe3776cbd0cc28258d3a41a1f131836', 'transaction_type' => PosInterface::TX_TYPE_PAY_AUTH, 'transaction_time' => new DateTimeImmutable('24.06.2026'), 'first_amount' => 10.0,  'capture_amount' => 10.0,  'currency' => PosInterface::CURRENCY_TRY, 'masked_number' => '979203***0796', 'installment_count' => 0, 'auth_code' => null, 'proc_return_code' => null, 'transaction_id' => null, 'capture_time' => null, 'error_message' => null, 'ref_ret_num' => null, 'order_status' => null, 'status' => AbstractResponseDataMapper::TX_APPROVED, 'error_code' => null, 'capture' => null],
                        ['order_id' => 'e464176d290d43990cc2bcd0f0416244', 'transaction_type' => PosInterface::TX_TYPE_PAY_AUTH, 'transaction_time' => new DateTimeImmutable('24.06.2026'), 'first_amount' => 100.0, 'capture_amount' => 100.0, 'currency' => PosInterface::CURRENCY_TRY, 'masked_number' => '979203***0796', 'installment_count' => 0, 'auth_code' => null, 'proc_return_code' => null, 'transaction_id' => null, 'capture_time' => null, 'error_message' => null, 'ref_ret_num' => null, 'order_status' => null, 'status' => AbstractResponseDataMapper::TX_APPROVED, 'error_code' => null, 'capture' => null],
                        ['order_id' => 'ee1cb4946480b49064f18074f3fd30d3', 'transaction_type' => PosInterface::TX_TYPE_REFUND,   'transaction_time' => new DateTimeImmutable('24.06.2026'), 'first_amount' => 11.0,  'capture_amount' => 11.0,  'currency' => PosInterface::CURRENCY_TRY, 'masked_number' => '979203***0796', 'installment_count' => 0, 'auth_code' => null, 'proc_return_code' => null, 'transaction_id' => null, 'capture_time' => null, 'error_message' => null, 'ref_ret_num' => null, 'order_status' => null, 'status' => AbstractResponseDataMapper::TX_APPROVED, 'error_code' => null, 'capture' => null],
                        ['order_id' => '91dc444670805428cee67b30ab2ae403', 'transaction_type' => PosInterface::TX_TYPE_REFUND,   'transaction_time' => new DateTimeImmutable('24.06.2026'), 'first_amount' => 22.0,  'capture_amount' => 22.0,  'currency' => PosInterface::CURRENCY_TRY, 'masked_number' => '979203***0796', 'installment_count' => 0, 'auth_code' => null, 'proc_return_code' => null, 'transaction_id' => null, 'capture_time' => null, 'error_message' => null, 'ref_ret_num' => null, 'order_status' => null, 'status' => AbstractResponseDataMapper::TX_APPROVED, 'error_code' => null, 'capture' => null],
                        ['order_id' => 'ddafc553d777c99be952c0f6555c2a92', 'transaction_type' => PosInterface::TX_TYPE_REFUND,   'transaction_time' => new DateTimeImmutable('24.06.2026'), 'first_amount' => 74.0,  'capture_amount' => 74.0,  'currency' => PosInterface::CURRENCY_TRY, 'masked_number' => '979203***0796', 'installment_count' => 0, 'auth_code' => null, 'proc_return_code' => null, 'transaction_id' => null, 'capture_time' => null, 'error_message' => null, 'ref_ret_num' => null, 'order_status' => null, 'status' => AbstractResponseDataMapper::TX_APPROVED, 'error_code' => null, 'capture' => null],
                  ],
                ],
            ],
        ];
    }
}
