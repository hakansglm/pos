<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\Response\Mapper;

use Mews\Pos\DataMapper\Response\Mapper\AbstractResponseDataMapper;
use Mews\Pos\DataMapper\Response\Mapper\IyzicoPosResponseDataMapper;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Factory\ResponseValueFormatterFactory;
use Mews\Pos\Factory\ResponseValueMapperFactory;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\Gateways\IyzicoPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(IyzicoPosResponseDataMapper::class)]
#[CoversClass(AbstractResponseDataMapper::class)]
class IyzicoPosResponseDataMapperTest extends TestCase
{
    private IyzicoPosResponseDataMapper $responseDataMapper;

    /** @var LoggerInterface&MockObject */
    private MockObject $loggerMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->responseDataMapper = new IyzicoPosResponseDataMapper(
            ResponseValueFormatterFactory::createForGateway(IyzicoPos::class),
            ResponseValueMapperFactory::createForGateway(IyzicoPos::class),
            $this->loggerMock
        );
    }

    public function testSupports(): void
    {
        $this->assertTrue($this->responseDataMapper::supports(IyzicoPos::class));
        $this->assertFalse($this->responseDataMapper::supports(AkbankPos::class));
    }

    #[TestWith([null, false])]
    #[TestWith(['0', false])]
    #[TestWith(['1', true])]
    #[TestWith(['2', false])]
    #[TestWith(['4', false])]
    public function testIs3dAuthSuccess(?string $mdStatus, bool $expected): void
    {
        $this->assertSame($expected, $this->responseDataMapper->is3dAuthSuccess($mdStatus));
    }

    #[TestWith([[], null])]
    #[TestWith([['mdStatus' => '1'], '1'])]
    #[TestWith([['mdStatus' => '0'], '0'])]
    public function testExtractMdStatus(array $responseData, ?string $expected): void
    {
        $this->assertSame($expected, $this->responseDataMapper->extractMdStatus($responseData));
    }

    /**
     * @dataProvider paymentTestDataProvider
     */
    public function testMapPaymentResponse(string $txType, array $responseData, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->mapPaymentResponse($responseData, $txType, []);

        $this->assertArrayHasKey('all', $actualData);
        $this->assertSame($responseData, $actualData['all']);
        unset($actualData['all']);

        $this->assertTransactionTime($expectedData, $actualData);

        \ksort($expectedData);
        \ksort($actualData);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider threeDPaymentDataProvider
     */
    public function testMap3DPaymentData(
        string  $txType,
        array   $raw3DAuthData,
        ?array  $rawPaymentData,
        array   $expectedData
    ): void {
        $actualData = $this->responseDataMapper->map3DPaymentData(
            $raw3DAuthData,
            $rawPaymentData,
            $txType,
            []
        );

        $this->assertArrayHasKey('3d_all', $actualData);
        $this->assertSame($raw3DAuthData, $actualData['3d_all']);
        unset($actualData['3d_all']);

        $this->assertArrayHasKey('all', $actualData);
        if (null !== $rawPaymentData) {
            $this->assertSame($rawPaymentData, $actualData['all']);
        }

        unset($actualData['all']);

        $this->assertTransactionTime($expectedData, $actualData);

        \ksort($expectedData);
        \ksort($actualData);
        $this->assertSame($expectedData, $actualData);
    }

    public function testMap3DPayResponseDataThrows(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->responseDataMapper->map3DPayResponseData([], PosInterface::TX_TYPE_PAY_AUTH, []);
    }

    /**
     * @dataProvider threeDHostPaymentDataProvider
     */
    public function testMap3DHostResponseData(
        string $txType,
        array  $raw3DAuthData,
        array  $expectedData
    ): void {
        $actualData = $this->responseDataMapper->map3DHostResponseData($raw3DAuthData, $txType, []);

        $this->assertArrayHasKey('all', $actualData);
        $this->assertSame($raw3DAuthData, $actualData['all']);
        unset($actualData['all']);

        $this->assertTransactionTime($expectedData, $actualData);

        \ksort($expectedData);
        \ksort($actualData);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider cancelTestDataProvider
     */
    public function testMapCancelResponse(array $responseData, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->mapCancelResponse($responseData);

        $this->assertArrayHasKey('all', $actualData);
        $this->assertSame($responseData, $actualData['all']);
        unset($actualData['all']);

        \ksort($expectedData);
        \ksort($actualData);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider refundTestDataProvider
     */
    public function testMapRefundResponse(array $responseData, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->mapRefundResponse($responseData);

        $this->assertArrayHasKey('all', $actualData);
        $this->assertSame($responseData, $actualData['all']);
        unset($actualData['all']);

        \ksort($expectedData);
        \ksort($actualData);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider statusTestDataProvider
     */
    public function testMapStatusResponse(array $responseData, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->mapStatusResponse($responseData);

        $this->assertArrayHasKey('all', $actualData);
        $this->assertSame($responseData, $actualData['all']);
        unset($actualData['all']);

        \ksort($expectedData);
        \ksort($actualData);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider orderHistoryTestDataProvider
     */
    public function testMapOrderHistoryResponse(array $responseData, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->mapOrderHistoryResponse($responseData);

        $this->assertArrayHasKey('all', $actualData);
        $this->assertSame($responseData, $actualData['all']);
        unset($actualData['all']);

        foreach ($actualData['transactions'] as $i => $tx) {
            foreach (['transaction_time', 'cancel_time', 'capture_time', 'refund_time'] as $timeField) {
                if (!isset($expectedData['transactions'][$i][$timeField])) {
                    continue;
                }

                if ($expectedData['transactions'][$i][$timeField] instanceof \DateTimeImmutable) {
                    $this->assertInstanceOf(\DateTimeImmutable::class, $actualData['transactions'][$i][$timeField], $timeField);
                    $this->assertEquals($expectedData['transactions'][$i][$timeField], $actualData['transactions'][$i][$timeField]);
                } else {
                    $this->assertSame($expectedData['transactions'][$i][$timeField], $actualData['transactions'][$i][$timeField]);
                }

                unset($actualData['transactions'][$i][$timeField], $expectedData['transactions'][$i][$timeField]);
            }
        }

        \ksort($expectedData);
        \ksort($actualData);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider historyTestDataProvider
     */
    public function testMapHistoryResponse(array $responseData, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->mapHistoryResponse($responseData);

        $this->assertArrayHasKey('all', $actualData);
        $this->assertSame($responseData, $actualData['all']);
        unset($actualData['all']);

        foreach ($actualData['transactions'] as $i => $tx) {
            $this->assertTransactionTime($expectedData['transactions'][$i], $tx);
            unset(
                $actualData['transactions'][$i]['transaction_time'],
                $expectedData['transactions'][$i]['transaction_time']
            );
        }

        \ksort($expectedData);
        \ksort($actualData);
        $this->assertSame($expectedData, $actualData);
    }

    // ==================== Data Providers ====================

    public static function paymentTestDataProvider(): \Generator
    {
        yield 'non_secure_pay_auth_success' => [
            PosInterface::TX_TYPE_PAY_AUTH,
            self::loadJson('payment/non_secure_pay_auth_success.json'),
            self::loadExpected('payment/non_secure_pay_auth_success_expected.json'),
        ];

        yield 'non_secure_pay_auth_fail_incorrect_total_amount' => [
            PosInterface::TX_TYPE_PAY_AUTH,
            [
                'status'          => 'failure',
                'errorCode'       => '5062',
                'errorMessage'    => 'Gönderilen tutar tüm kırılımların toplam tutarına eşit olmalıdır',
                'locale'          => 'tr',
                'systemTime'      => 1781209772355,
                'conversationId'  => '20260611F399',
            ],
            [
                'transaction_type'  => PosInterface::TX_TYPE_PAY_AUTH,
                'installment_count' => null,
                'currency'          => null,
                'amount'            => null,
                'payment_model'     => PosInterface::MODEL_NON_SECURE,
                'auth_code'         => null,
                'ref_ret_num'       => null,
                'batch_num'         => null,
                'order_id'          => '20260611F399',
                'transaction_id'    => null,
                'transaction_time'  => new \DateTimeImmutable('2026-06-11 20:29:32', new \DateTimeZone('UTC')),
                'proc_return_code'  => 'failure',
                'status'            => 'declined',
                'error_code'        => '5062',
                'error_message'     => 'Gönderilen tutar tüm kırılımların toplam tutarına eşit olmalıdır',
            ],
        ];

        yield 'non_secure_pre_auth_success' => [
            PosInterface::TX_TYPE_PAY_PRE_AUTH,
            self::loadJson('payment/non_secure_pre_auth_success.json'),
            self::loadExpected('payment/non_secure_pre_auth_success_expected.json'),
        ];

        yield 'non_secure_post_auth_success' => [
            PosInterface::TX_TYPE_PAY_POST_AUTH,
            self::loadJson('payment/non_secure_post_auth_success.json'),
            self::loadExpected('payment/non_secure_post_auth_success_expected.json'),
        ];

        yield 'non_secure_post_auth_fail' => [
            PosInterface::TX_TYPE_PAY_POST_AUTH,
            [
                'status'         => 'failure',
                'errorCode'      => '5082',
                'errorMessage'   => 'Bu ödeme işleminin durumu POST_AUTH işlemi için uygun değildir',
                'locale'         => 'tr',
                'systemTime'     => 1781209778130,
                'conversationId' => '202606118557',
                'paymentId'      => '35790641',
            ],
            [
                'transaction_type'  => PosInterface::TX_TYPE_PAY_POST_AUTH,
                'installment_count' => null,
                'currency'          => null,
                'amount'            => null,
                'payment_model'     => PosInterface::MODEL_NON_SECURE,
                'auth_code'         => null,
                'ref_ret_num'       => null,
                'batch_num'         => null,
                'order_id'          => '202606118557',
                'transaction_id'    => '35790641',
                'transaction_time'  => new \DateTimeImmutable('2026-06-11 20:29:38', new \DateTimeZone('UTC')),
                'proc_return_code'  => 'failure',
                'status'            => 'declined',
                'error_code'        => '5082',
                'error_message'     => 'Bu ödeme işleminin durumu POST_AUTH işlemi için uygun değildir',
            ],
        ];
    }

    public static function threeDPaymentDataProvider(): \Generator
    {
        $paymentSuccess = self::loadJson('payment/non_secure_pay_auth_success.json');
        $expectedBase   = self::loadExpected('payment/non_secure_pay_auth_success_expected.json');

        yield '3d_secure_auth_success_with_payment' => [
            PosInterface::TX_TYPE_PAY_AUTH,
            ['mdStatus' => '1', 'conversationId' => 'order-20240606a1b2', 'status' => 'success'],
            $paymentSuccess,
            \array_merge($expectedBase, [
                'payment_model'        => PosInterface::MODEL_3D_SECURE,
                'md_status'            => '1',
                'md_error_message'     => null,
                'transaction_security' => null,
            ]),
        ];

        yield '3d_secure_auth_failure' => [
            PosInterface::TX_TYPE_PAY_AUTH,
            ['mdStatus' => '0', 'conversationId' => 'order-20240606fail', 'status' => 'failure'],
            null,
            [
                'order_id'             => 'order-20240606fail',
                'transaction_id'       => null,
                'transaction_time'     => null,
                'transaction_type'     => PosInterface::TX_TYPE_PAY_AUTH,
                'installment_count'    => null,
                'currency'             => null,
                'amount'               => null,
                'payment_model'        => PosInterface::MODEL_3D_SECURE,
                'auth_code'            => null,
                'ref_ret_num'          => null,
                'batch_num'            => null,
                'proc_return_code'     => 'failure',
                'status'               => 'declined',
                'error_code'           => null,
                'error_message'        => null,
                'md_status'            => '0',
                'md_error_message'     => null,
                'transaction_security' => null,
            ],
        ];

        // auth success but null rawPaymentResponseData → mapPaymentCommonPaymentResponse receives []
        // → hits the early-return branch: if ([] === $rawPaymentResponseData) { return $defaultResponse; }
        yield '3d_secure_auth_success_no_payment_data' => [
            PosInterface::TX_TYPE_PAY_AUTH,
            ['mdStatus' => '1', 'conversationId' => 'order-3d-nopay', 'status' => 'success'],
            null,
            [
                'order_id'             => null,
                'transaction_id'       => null,
                'transaction_time'     => null,
                'transaction_type'     => PosInterface::TX_TYPE_PAY_AUTH,
                'installment_count'    => null,
                'currency'             => null,
                'amount'               => null,
                'payment_model'        => PosInterface::MODEL_3D_SECURE,
                'auth_code'            => null,
                'ref_ret_num'          => null,
                'batch_num'            => null,
                'proc_return_code'     => null,
                'status'               => 'declined',
                'error_code'           => null,
                'error_message'        => null,
                'md_status'            => '1',
                'md_error_message'     => null,
                'transaction_security' => null,
            ],
        ];
    }

    public static function threeDHostPaymentDataProvider(): \Generator
    {
        $paymentSuccess = self::loadJson('payment/non_secure_pay_auth_success.json');
        $expectedBase   = self::loadExpected('payment/non_secure_pay_auth_success_expected.json');

        yield '3d_host_success' => [
            PosInterface::TX_TYPE_PAY_AUTH,
            \array_merge($paymentSuccess, ['mdStatus' => '1']),
            \array_merge($expectedBase, [
                'payment_model'        => PosInterface::MODEL_3D_HOST,
                'md_status'            => '1',
                'md_error_message'     => null,
                'transaction_security' => null,
            ]),
        ];

        yield '3d_host_failure' => [
            PosInterface::TX_TYPE_PAY_AUTH,
            ['mdStatus' => '0', 'conversationId' => 'order-20240606fail', 'status' => 'failure'],
            [
                'order_id'             => 'order-20240606fail',
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
                'proc_return_code'     => 'failure',
                'status'               => 'declined',
                'error_code'           => null,
                'error_message'        => null,
                'md_status'            => '0',
                'md_error_message'     => null,
                'transaction_security' => null,
            ],
        ];
    }

    public static function cancelTestDataProvider(): \Generator
    {
        yield 'cancel_success' => [
            self::loadJson('cancel/cancel_success.json'),
            self::loadExpected('cancel/cancel_success_expected.json'),
        ];

        yield 'cancel_failure_no_payment_id' => [
            ['status' => 'failure', 'errorCode' => '10000', 'errorMessage' => 'System Error', 'conversationId' => 'order-20240606fail'],
            [
                'order_id'         => 'order-20240606fail',
                'auth_code'        => null,
                'ref_ret_num'      => null,
                'proc_return_code' => 'failure',
                'transaction_id'   => null,
                'error_code'       => '10000',
                'error_message'    => 'System Error',
                'status'           => 'declined',
            ],
        ];

        yield 'cancel_failure_with_payment_id' => [
            [
                'status'         => 'failure',
                'errorCode'      => '8514',
                'errorMessage'   => 'Bu işlem önceden başlatıldı ve devam etmektedir',
                'locale'         => 'tr',
                'systemTime'     => 1781209775789,
                'conversationId' => '20260611C995',
                'paymentId'      => '35790626',
            ],
            [
                'order_id'         => '20260611C995',
                'auth_code'        => null,
                'ref_ret_num'      => null,
                'proc_return_code' => 'failure',
                'transaction_id'   => '35790626',
                'error_code'       => '8514',
                'error_message'    => 'Bu işlem önceden başlatıldı ve devam etmektedir',
                'status'           => 'declined',
            ],
        ];
    }

    public static function refundTestDataProvider(): \Generator
    {
        yield 'refund_success' => [
            self::loadJson('refund/refund_success.json'),
            self::loadExpected('refund/refund_success_expected.json'),
        ];

        yield 'refund_failure' => [
            [
                'status'         => 'failure',
                'errorCode'      => '8514',
                'errorMessage'   => 'Bu işlem önceden başlatıldı ve devam etmektedir',
                'locale'         => 'tr',
                'systemTime'     => 1781209780093,
                'conversationId' => '20260611C995',
                'paymentId'      => '35790626',
                'price'          => 10.01,
                'retryable'      => false,
            ],
            [
                'order_id'         => '20260611C995',
                'auth_code'        => null,
                'ref_ret_num'      => null,
                'proc_return_code' => 'failure',
                'transaction_id'   => null,
                'error_code'       => '8514',
                'error_message'    => 'Bu işlem önceden başlatıldı ve devam etmektedir',
                'status'           => 'declined',
            ],
        ];
    }

    public static function statusTestDataProvider(): \Generator
    {
        yield 'status_success' => [
            self::loadJson('status/status_success.json'),
            self::loadExpected('status/status_success_expected.json'),
        ];

        yield 'status_success_cancelled_order' => [
            self::loadJson('status/status_success_cancelled_order.json'),
            self::loadExpected('status/status_success_cancelled_order_expected.json'),
        ];

        yield 'status_failure_no_conversation_id' => [
            ['status' => 'failure', 'errorCode' => '11', 'errorMessage' => 'Geçersiz istek', 'locale' => 'tr', 'systemTime' => 1780482658567],
            [
                'order_id'          => null,
                'auth_code'         => null,
                'proc_return_code'  => 'failure',
                'transaction_id'    => null,
                'transaction_time'  => null,
                'capture_time'      => null,
                'error_message'     => 'Geçersiz istek',
                'ref_ret_num'       => null,
                'order_status'      => null,
                'transaction_type'  => null,
                'first_amount'      => null,
                'capture_amount'    => null,
                'status'            => 'declined',
                'error_code'        => '11',
                'capture'           => null,
                'currency'          => null,
                'masked_number'     => null,
                'refund_amount'     => null,
                'installment_count' => null,
                'refund_time'       => null,
                'cancel_time'       => null,
            ],
        ];

        yield 'status_failure_non_existent_order' => [
            ['status' => 'failure', 'errorCode' => '5087', 'errorMessage' => 'Üye işyerine ait ödeme kaydı bulunamadı', 'locale' => 'tr', 'systemTime' => 1781209773486, 'conversationId' => 'nonexistent-order-99999'],
            [
                'order_id'          => 'nonexistent-order-99999',
                'auth_code'         => null,
                'proc_return_code'  => 'failure',
                'transaction_id'    => null,
                'transaction_time'  => null,
                'capture_time'      => null,
                'error_message'     => 'Üye işyerine ait ödeme kaydı bulunamadı',
                'ref_ret_num'       => null,
                'order_status'      => null,
                'transaction_type'  => null,
                'first_amount'      => null,
                'capture_amount'    => null,
                'status'            => 'declined',
                'error_code'        => '5087',
                'capture'           => null,
                'currency'          => null,
                'masked_number'     => null,
                'refund_amount'     => null,
                'installment_count' => null,
                'refund_time'       => null,
                'cancel_time'       => null,
            ],
        ];

        yield 'status_success_minimal' => [
            [
                'status'         => 'success',
                'conversationId' => 'order-minimal-99',
                'paymentId'      => '11111',
                'paymentStatus'  => 'SUCCESS',
            ],
            [
                'order_id'          => 'order-minimal-99',
                'auth_code'         => null,
                'proc_return_code'  => 'success',
                'transaction_id'    => '11111',
                'transaction_time'  => null,
                'capture_time'      => null,
                'error_message'     => null,
                'ref_ret_num'       => null,
                'order_status'      => 'SUCCESS',
                'transaction_type'  => null,
                'first_amount'      => null,
                'capture_amount'    => null,
                'status'            => 'approved',
                'error_code'        => null,
                'capture'           => null,
                'currency'          => null,
                'masked_number'     => null,
                'refund_amount'     => null,
                'installment_count' => null,
                'refund_time'       => null,
                'cancel_time'       => null,
            ],
        ];
    }

    public static function orderHistoryTestDataProvider(): \Generator
    {
        yield 'order_history_success' => [
            self::loadJson('order_history/order_history_success.json'),
            self::loadExpectedWithTransactionTimes('order_history/order_history_success_expected.json'),
        ];

        yield 'order_history_success_non_existent_order' => [
            self::loadJson('order_history/order_history_success_non_existent_order.json'),
            self::loadExpected('order_history/order_history_success_non_existent_order_expected.json'),
        ];

        yield 'order_history_failure' => [
            ['status' => 'failure', 'errorCode' => '5087', 'errorMessage' => 'Üye işyerine ait ödeme kaydı bulunamadı', 'locale' => 'tr', 'systemTime' => 1749555555555, 'conversationId' => 'order-fail-123'],
            [
                'order_id'      => 'order-fail-123',
                'proc_return_code' => 'failure',
                'error_code'    => '5087',
                'error_message' => 'Üye işyerine ait ödeme kaydı bulunamadı',
                'trans_count'   => 0,
                'transactions'  => [],
                'status'        => 'declined',
            ],
        ];

        // Two payments: declined (paymentStatus=0, no paymentRefundStatus) and approved (paymentStatus=1, no paymentRefundStatus).
        // Covers TX_DECLINED→PAYMENT_STATUS_ERROR, PAYMENT_STATUS_PAYMENT_COMPLETED, and all isset null branches.
        yield 'order_history_declined_and_completed_minimal' => [
            [
                'status'         => 'success',
                'conversationId' => 'order-hist-min',
                'payments'       => [
                    [
                        'paymentId'     => 100,
                        'paymentStatus' => 0,
                        'threeDS'       => 0,
                        'phase'         => 'AUTH',
                    ],
                    [
                        'paymentId'     => 200,
                        'paymentStatus' => 1,
                        'threeDS'       => 0,
                        'phase'         => 'AUTH',
                    ],
                ],
            ],
            [
                'order_id'         => 'order-hist-min',
                'proc_return_code' => 'success',
                'error_code'       => null,
                'error_message'    => null,
                'trans_count'      => 2,
                'transactions'     => [
                    [
                        'auth_code'         => null,
                        'proc_return_code'  => null,
                        'transaction_id'    => 100,
                        'transaction_time'  => null,
                        'capture_time'      => null,
                        'error_message'     => null,
                        'ref_ret_num'       => null,
                        'order_status'      => PosInterface::PAYMENT_STATUS_ERROR,
                        'transaction_type'  => PosInterface::TX_TYPE_PAY_AUTH,
                        'first_amount'      => null,
                        'capture_amount'    => null,
                        'status'            => 'declined',
                        'error_code'        => null,
                        'capture'           => false,
                        'currency'          => null,
                        'masked_number'     => null,
                        'payment_model'     => PosInterface::MODEL_NON_SECURE,
                        'installment_count' => null,
                    ],
                    [
                        'auth_code'         => null,
                        'proc_return_code'  => null,
                        'transaction_id'    => 200,
                        'transaction_time'  => null,
                        'capture_time'      => null,
                        'error_message'     => null,
                        'ref_ret_num'       => null,
                        'order_status'      => PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED,
                        'transaction_type'  => PosInterface::TX_TYPE_PAY_AUTH,
                        'first_amount'      => null,
                        'capture_amount'    => null,
                        'status'            => 'approved',
                        'error_code'        => null,
                        'capture'           => true,
                        'currency'          => null,
                        'masked_number'     => null,
                        'payment_model'     => PosInterface::MODEL_NON_SECURE,
                        'installment_count' => null,
                    ],
                ],
                'status'           => 'approved',
            ],
        ];
    }

    public static function historyTestDataProvider(): \Generator
    {
        yield 'history_success' => [
            self::loadJson('history/history_success.json'),
            self::loadExpectedWithTransactionTimes('history/history_success_expected.json'),
        ];

        yield 'history_failure' => [
            ['status' => 'failure', 'errorCode' => '10000', 'errorMessage' => 'System Error', 'currentPage' => null, 'totalPageCount' => null],
            [
                'proc_return_code' => 'failure',
                'error_code'       => '10000',
                'error_message'    => 'System Error',
                'trans_count'      => 0,
                'transactions'     => [],
                'current_page'     => null,
                'total_pages'      => null,
                'status'           => 'declined',
            ],
        ];

        // PAYMENT with transactionStatus=0 (declined, PAYMENT_STATUS_ERROR, capture=false, capture_amount=null)
        // and null transactionType (transaction_type=null, txStatus=TX_APPROVED, order_status=null).
        yield 'history_declined_payment_and_null_type' => [
            [
                'status'         => 'success',
                'currentPage'    => 1,
                'totalPageCount' => 1,
                'transactions'   => [
                    [
                        'transactionType'   => 'PAYMENT',
                        'transactionId'     => 1001,
                        'transactionStatus' => 0,
                        'threeDS'           => 0,
                        'price'             => 10.01,
                        // intentionally no paidPrice → capture_amount = null
                    ],
                    [
                        // no transactionType → transaction_type = null
                        'transactionId' => 1002,
                        'threeDS'       => 0,
                        // no price, transactionCurrency, installment, transactionDate
                    ],
                ],
            ],
            [
                'proc_return_code' => 'success',
                'error_code'       => null,
                'error_message'    => null,
                'trans_count'      => 2,
                'current_page'     => 1,
                'total_pages'      => 1,
                'transactions'     => [
                    [
                        'auth_code'         => null,
                        'proc_return_code'  => null,
                        'transaction_id'    => 1001,
                        'transaction_time'  => null,
                        'capture_time'      => null,
                        'error_message'     => null,
                        'ref_ret_num'       => null,
                        'order_status'      => PosInterface::PAYMENT_STATUS_ERROR,
                        'transaction_type'  => PosInterface::TX_TYPE_PAY_AUTH,
                        'first_amount'      => 10.01,
                        'capture_amount'    => null,
                        'status'            => 'declined',
                        'error_code'        => null,
                        'capture'           => false,
                        'currency'          => null,
                        'masked_number'     => null,
                        'payment_model'     => PosInterface::MODEL_NON_SECURE,
                        'installment_count' => null,
                    ],
                    [
                        'auth_code'         => null,
                        'proc_return_code'  => null,
                        'transaction_id'    => 1002,
                        'transaction_time'  => null,
                        'capture_time'      => null,
                        'error_message'     => null,
                        'ref_ret_num'       => null,
                        'order_status'      => null,
                        'transaction_type'  => null,
                        'first_amount'      => null,
                        'capture_amount'    => null,
                        'status'            => 'approved',
                        'error_code'        => null,
                        'capture'           => null,
                        'currency'          => null,
                        'masked_number'     => null,
                        'payment_model'     => PosInterface::MODEL_NON_SECURE,
                        'installment_count' => null,
                    ],
                ],
                'status'           => 'approved',
            ],
        ];
    }

    // ==================== Helpers ====================

    private static function loadJson(string $path): array
    {
        return \json_decode(
            \file_get_contents(__DIR__.'/../../../test_data/iyzicopos/'.$path),
            true
        );
    }

    private static function loadExpected(string $path): array
    {
        $data = self::loadJson($path);
        if (isset($data['transaction_time']) && \is_array($data['transaction_time'])) {
            $data['transaction_time'] = self::arrayToDateTime($data['transaction_time']);
        }

        if (isset($data['capture_time']) && \is_array($data['capture_time'])) {
            $data['capture_time'] = self::arrayToDateTime($data['capture_time']);
        }

        if (isset($data['cancel_time']) && \is_array($data['cancel_time'])) {
            $data['cancel_time'] = self::arrayToDateTime($data['cancel_time']);
        }

        if (isset($data['refund_time']) && \is_array($data['refund_time'])) {
            $data['refund_time'] = self::arrayToDateTime($data['refund_time']);
        }

        return $data;
    }

    private static function loadExpectedWithTransactionTimes(string $path): array
    {
        $data = self::loadJson($path);
        foreach ($data['transactions'] ?? [] as $i => $tx) {
            foreach (['transaction_time', 'capture_time', 'cancel_time', 'refund_time'] as $field) {
                if (isset($tx[$field]) && \is_array($tx[$field])) {
                    $data['transactions'][$i][$field] = self::arrayToDateTime($tx[$field]);
                }
            }
        }

        return $data;
    }

    private static function arrayToDateTime(array $dtArray): \DateTimeImmutable
    {
        return new \DateTimeImmutable($dtArray['date'], new \DateTimeZone($dtArray['timezone']));
    }

    private function assertTransactionTime(array &$expectedData, array &$actualData): void
    {
        if (!isset($expectedData['transaction_time'])) {
            return;
        }

        if ($expectedData['transaction_time'] instanceof \DateTimeImmutable) {
            $this->assertInstanceOf(\DateTimeImmutable::class, $actualData['transaction_time']);
            $this->assertEquals($expectedData['transaction_time'], $actualData['transaction_time']);
        } else {
            $this->assertSame($expectedData['transaction_time'], $actualData['transaction_time']);
        }

        unset($actualData['transaction_time'], $expectedData['transaction_time']);
    }
}
