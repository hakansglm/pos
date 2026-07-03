<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Gateway;

use PHPUnit\Framework\Attributes\DataProvider;
use LogicException;
use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Client\HttpClientStrategyInterface;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\Request\Mapper\RequestDataMapperInterface;
use Mews\Pos\DataMapper\Request\ValueMapper\AssecoPosRequestValueMapper;
use Mews\Pos\DataMapper\Response\Mapper\ResponseDataMapperInterface;
use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Model\Account\AssecoPosAccount;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exception\HashMismatchException;
use Mews\Pos\Exception\UnsupportedFormFormatException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Gateway\AbstractGateway;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

#[CoversClass(AssecoPos::class)]
#[CoversClass(AbstractGateway::class)]
class AssecoPosTest extends TestCase
{
    private AssecoPosAccount $account;

    /** @var AssecoPos */
    private PosInterface $pos;

    /** @var array<string, mixed> */
    private array $config;

    /** @var RequestDataMapperInterface & MockObject */
    private MockObject $requestMapperMock;

    /** @var ResponseDataMapperInterface & MockObject */
    private MockObject $responseMapperMock;

    /** @var CryptInterface & MockObject */
    private MockObject $cryptMock;

    /** @var HttpClientStrategyInterface & MockObject */
    private MockObject $httpClientStrategyMock;

    /** @var HttpClientInterface & MockObject */
    private MockObject $httpClientMock;

    /** @var LoggerInterface & MockObject */
    private MockObject $loggerMock;

    /** @var EventDispatcherInterface & MockObject */
    private MockObject $eventDispatcherMock;

    private CreditCardInterface $card;

    private array $order;

    private AssecoPosRequestValueMapper $requestValueMapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'name'              => 'AKBANK T.A.S.',
            'class'             => AssecoPos::class,
            'gateway_endpoints' => [
                'gateway_3d' => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
            ],
        ];

        $this->account = AccountFactory::createAssecoPosAccount(
            'akbank',
            '700655000200',
            'ISBANKAPI',
            'ISBANK07',
            'TRPS0200'
        );

        $this->order = [
            'id'          => 'order222',
            'amount'      => '100.25',
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'lang'        => PosInterface::LANG_TR,
        ];

        $this->requestValueMapper     = new AssecoPosRequestValueMapper();
        $this->requestMapperMock      = $this->createMock(RequestDataMapperInterface::class);
        $this->responseMapperMock     = $this->createMock(ResponseDataMapperInterface::class);
        $this->cryptMock              = $this->createMock(CryptInterface::class);
        $this->httpClientStrategyMock = $this->createMock(HttpClientStrategyInterface::class);
        $this->httpClientMock         = $this->createMock(HttpClientInterface::class);
        $this->loggerMock             = $this->createMock(LoggerInterface::class);
        $this->eventDispatcherMock    = $this->createMock(EventDispatcherInterface::class);

        $this->pos = $this->createGateway($this->config);

        $this->card = CreditCardFactory::createForGateway(
            $this->pos,
            '5555444433332222',
            '21',
            '12',
            '122',
            'ahmet',
            CreditCardInterface::CARD_TYPE_VISA
        );
    }

    /**
     * @return void
     */
    public function testInit(): void
    {
        $this->assertCount(count($this->requestValueMapper->getCurrencyMappings()), $this->pos->getCurrencies());
        $this->assertSame($this->account, $this->pos->getAccount());
        $this->assertFalse($this->pos->isTestMode());
        $this->assertSame($this->cryptMock, $this->pos->getCrypt());
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function testGet3DFormData(
        bool $isWithCard
    ): void {
        $card         = $isWithCard ? $this->card : null;
        $paymentModel = $isWithCard ? PosInterface::MODEL_3D_SECURE : PosInterface::MODEL_3D_HOST;
        $order        = ['id' => '124'];
        $txType       = PosInterface::TX_TYPE_PAY_PRE_AUTH;

        $this->requestMapperMock->expects(self::once())
            ->method('create3DFormData')
            ->with(
                $this->pos->getAccount(),
                $order,
                $paymentModel,
                $txType,
                $this->config['gateway_endpoints']['gateway_3d'],
                $card
            )
            ->willReturn(['formData']);

        $actual = $this->pos->get3DFormData($order, $paymentModel, $txType, $card, !$isWithCard);

        $this->assertSame(['formData'], $actual);
    }

    #[DataProvider('threeDFormDataBadInputsProvider')]
    public function testGet3DFormDataWithBadInputs(
        array   $order,
        string  $paymentModel,
        string  $txType,
        bool    $isWithCard,
        bool    $createWithoutCard,
        string  $expectedExceptionClass,
        string  $expectedExceptionMsg,
        ?string $formFormat = null
    ): void {
        $card = $isWithCard ? $this->card : null;

        $this->expectException($expectedExceptionClass);
        $this->expectExceptionMessage($expectedExceptionMsg);

        $this->pos->get3DFormData($order, $paymentModel, $txType, $card, $createWithoutCard, $formFormat);
    }

    /**
     * @return void
     */
    public function testMake3DHostPaymentSuccess(): void
    {
        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->willReturn(true);

        $testData            = [
            'order'        => ['id' => 'order-3dhost-assecopos'],
            'txType'       => 'pay',
            'paymentData'  => [
                'oid'      => '202210305DCF',
                'mdStatus' => '1',
            ],
            'expectedData' => ['status' => 'approved'],
        ];
        $gatewayResponseData = $testData['paymentData'];
        $order               = $testData['order'];
        $txType              = $testData['txType'];

        $this->responseMapperMock->expects(self::once())
            ->method('map3DHostResponseData')
            ->with($gatewayResponseData, $txType, $order)
            ->willReturn($testData['expectedData']);

        $pos = $this->pos;

        $result = $pos->payment(PosInterface::MODEL_3D_HOST, $order, $txType, null, $gatewayResponseData);

        $this->assertSame($testData['expectedData'], $result);
        $this->assertTrue($pos->isSuccess());
    }

    /**
     * @return void
     */
    public function testMake3DHostPaymentWithoutHashCheckSuccess(): void
    {
        $config = $this->config;
        $config += [
            'gateway_configs' => [
                'disable_3d_hash_check' => true,
            ],
        ];

        $pos = $this->createGateway($config);

        $this->cryptMock->expects(self::never())
            ->method('check3DHash');

        $testData            = [
            'order'        => ['id' => 'order-3dhost-nohash'],
            'txType'       => 'pay',
            'paymentData'  => [
                'oid'      => '202210305DCF',
                'mdStatus' => '1',
            ],
            'expectedData' => ['status' => 'approved'],
        ];
        $gatewayResponseData = $testData['paymentData'];
        $order               = $testData['order'];
        $txType              = $testData['txType'];

        $this->responseMapperMock->expects(self::once())
            ->method('map3DHostResponseData')
            ->with($gatewayResponseData, $txType, $order)
            ->willReturn($testData['expectedData']);

        $result = $pos->payment(PosInterface::MODEL_3D_HOST, $order, $txType, null, $gatewayResponseData);

        $this->assertSame($testData['expectedData'], $result);
        $this->assertTrue($pos->isSuccess());
    }

    public function testMake3DHostPaymentHashMismatchException(): void
    {
        $txType = PosInterface::TX_TYPE_PAY_PRE_AUTH;
        $data   = [
            'oid'      => '202210305DCF',
            'mdStatus' => '1',
        ];

        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->with($this->account, $data)
            ->willReturn(false);

        $this->responseMapperMock->expects(self::never())
            ->method('map3DHostResponseData');

        $this->expectException(HashMismatchException::class);
        $this->pos->payment(PosInterface::MODEL_3D_HOST, [], $txType, null, $data);
    }

    /**
     * @return void
     */
    public function testMake3DPayPaymentSuccess(): void
    {
        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->willReturn(true);

        $testData            = [
            'order'        => ['currency' => 'TRY', 'amount' => 1.01],
            'txType'       => 'pay',
            'paymentData'  => ['oid' => '2022103030CB', 'mdStatus' => '1'],
            'expectedData' => ['status' => 'approved'],
        ];
        $gatewayResponseData = $testData['paymentData'];
        $order               = $testData['order'];
        $txType              = $testData['txType'];

        $this->responseMapperMock->expects(self::once())
            ->method('map3DPayResponseData')
            ->with($gatewayResponseData, $txType, $order)
            ->willReturn($testData['expectedData']);

        $pos = $this->pos;

        $result = $pos->payment(PosInterface::MODEL_3D_PAY, $order, $txType, null, $gatewayResponseData);

        $this->assertSame($testData['expectedData'], $result);
        $this->assertTrue($pos->isSuccess());
    }

    /**
     * @return void
     */
    public function testMake3DPayPaymentWithoutHashCheckSuccess(): void
    {
        $config = $this->config;
        $config += [
            'gateway_configs' => [
                'disable_3d_hash_check' => true,
            ],
        ];

        $pos = $this->createGateway($config);

        $this->cryptMock->expects(self::never())
            ->method('check3DHash');

        $testData            = [
            'order'        => ['currency' => 'TRY', 'amount' => 1.01],
            'txType'       => 'pay',
            'paymentData'  => ['oid' => '2022103030CB', 'mdStatus' => '1'],
            'expectedData' => ['status' => 'approved'],
        ];
        $gatewayResponseData = $testData['paymentData'];
        $order               = $testData['order'];
        $txType              = $testData['txType'];

        $this->responseMapperMock->expects(self::once())
            ->method('map3DPayResponseData')
            ->with($gatewayResponseData, $txType, $order)
            ->willReturn($testData['expectedData']);

        $result = $pos->payment(PosInterface::MODEL_3D_PAY, $order, $txType, null, $gatewayResponseData);

        $this->assertSame($testData['expectedData'], $result);
        $this->assertTrue($pos->isSuccess());
    }

    public function testMake3DPayPaymentHashMismatchException(): void
    {
        $data   = ['oid' => '2022103030CB', 'mdStatus' => '1'];
        $txType = PosInterface::TX_TYPE_PAY_PRE_AUTH;

        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->with($this->account, $data)
            ->willReturn(false);

        $this->responseMapperMock->expects(self::never())
            ->method('map3DPayResponseData');

        $this->expectException(HashMismatchException::class);
        $this->pos->payment(PosInterface::MODEL_3D_PAY, [], $txType, null, $data);
    }

    #[DataProvider('statusDataProvider')]
    public function testStatus(array $bankResponse, array $expectedData, bool $isSuccess): void
    {
        $account     = $this->pos->getAccount();
        $txType      = PosInterface::TX_TYPE_STATUS;
        $requestData = ['createStatusRequestData'];
        $order       = $this->order;

        $this->requestMapperMock->expects(self::once())
            ->method('createStatusRequestData')
            ->with($account, $order)
            ->willReturn($requestData);

        $this->configureClientResponse(
            $txType,
            $requestData,
            $bankResponse,
            $order,
            PosInterface::MODEL_NON_SECURE,
            null,
            $this->account
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapStatusResponse')
            ->with($bankResponse)
            ->willReturn($expectedData);

        $result = $this->pos->status($order);

        $this->assertSame($expectedData, $result);
        $this->assertSame($isSuccess, $this->pos->isSuccess());
    }

    #[DataProvider('orderHistoryDataProvider')]
    public function testOrderHistory(array $bankResponse, array $expectedData, bool $isSuccess): void
    {
        $account     = $this->pos->getAccount();
        $txType      = PosInterface::TX_TYPE_ORDER_HISTORY;
        $requestData = ['createOrderHistoryRequestData'];
        $order       = $this->order;

        $this->requestMapperMock->expects(self::once())
            ->method('createOrderHistoryRequestData')
            ->with($account, $order)
            ->willReturn($requestData);

        $this->configureClientResponse(
            $txType,
            $requestData,
            $bankResponse,
            $order,
            PosInterface::MODEL_NON_SECURE,
            null,
            $this->account
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapOrderHistoryResponse')
            ->with($bankResponse)
            ->willReturn($expectedData);

        $result = $this->pos->orderHistory($order);

        $this->assertSame($expectedData, $result);
        $this->assertSame($isSuccess, $this->pos->isSuccess());
    }

    #[DataProvider('cancelDataProvider')]
    public function testCancel(array $bankResponse, array $expectedData, bool $isSuccess): void
    {
        $account     = $this->pos->getAccount();
        $txType      = PosInterface::TX_TYPE_CANCEL;
        $requestData = ['createCancelRequestData'];
        $order       = $this->order;

        $this->requestMapperMock->expects(self::once())
            ->method('createCancelRequestData')
            ->with($account, $order)
            ->willReturn($requestData);

        $this->configureClientResponse(
            $txType,
            $requestData,
            $bankResponse,
            $order,
            PosInterface::MODEL_NON_SECURE,
            null,
            $this->account
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapCancelResponse')
            ->with($bankResponse)
            ->willReturn($expectedData);

        $result = $this->pos->cancel($order);

        $this->assertSame($expectedData, $result);
        $this->assertSame($isSuccess, $this->pos->isSuccess());
    }

    #[DataProvider('refundDataProvider')]
    public function testRefund(array $bankResponse, array $expectedData, bool $isSuccess): void
    {
        $account     = $this->pos->getAccount();
        $txType      = PosInterface::TX_TYPE_REFUND;
        $requestData = ['createRefundRequestData'];
        $order       = $this->order;

        $this->requestMapperMock->expects(self::once())
            ->method('createRefundRequestData')
            ->with($account, $order, $txType)
            ->willReturn($requestData);

        $this->configureClientResponse(
            $txType,
            $requestData,
            $bankResponse,
            $order,
            PosInterface::MODEL_NON_SECURE,
            null,
            $this->account
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapRefundResponse')
            ->with($bankResponse)
            ->willReturn($expectedData);

        $result = $this->pos->refund($order);

        $this->assertSame($expectedData, $result);
        $this->assertSame($isSuccess, $this->pos->isSuccess());
    }

    #[DataProvider('make3DPaymentDataProvider')]
    public function testMake3DPayment(
        array  $order,
        string $txType,
        array  $gatewayResponseData,
        array  $paymentResponse,
        array  $expectedResponse,
        bool   $is3DSuccess,
        bool   $isSuccess
    ): void {
        if ($is3DSuccess) {
            $this->cryptMock->expects(self::once())
                ->method('check3DHash')
                ->with($this->account, $gatewayResponseData)
                ->willReturn(true);
        }

        $this->responseMapperMock->expects(self::once())
            ->method('extractMdStatus')
            ->with($gatewayResponseData)
            ->willReturn('3d-status');

        $this->responseMapperMock->expects(self::once())
            ->method('is3dAuthSuccess')
            ->with('3d-status')
            ->willReturn($is3DSuccess);

        $create3DPaymentRequestData = [
            'create3DPaymentRequestData',
        ];
        if ($is3DSuccess) {
            $this->requestMapperMock->expects(self::once())
                ->method('create3DPaymentRequestData')
                ->with($this->account, $order, $txType, $gatewayResponseData)
                ->willReturn($create3DPaymentRequestData);

            $this->configureClientResponse(
                $txType,
                $create3DPaymentRequestData,
                $paymentResponse,
                $order,
                PosInterface::MODEL_3D_SECURE,
            );

            $this->responseMapperMock->expects(self::once())
                ->method('map3DPaymentData')
                ->with($gatewayResponseData, $paymentResponse, $txType, $order)
                ->willReturn($expectedResponse);
        } else {
            $this->responseMapperMock->expects(self::once())
                ->method('map3DPaymentData')
                ->with($gatewayResponseData, null, $txType, $order)
                ->willReturn($expectedResponse);
            $this->requestMapperMock->expects(self::never())
                ->method('create3DPaymentRequestData');
            $this->eventDispatcherMock->expects(self::never())
                ->method('dispatch');
        }

        $result = $this->pos->payment(PosInterface::MODEL_3D_SECURE, $order, $txType, null, $gatewayResponseData);

        $this->assertSame($expectedResponse, $result);
        $this->assertSame($isSuccess, $this->pos->isSuccess());
    }

    #[DataProvider('make3DPaymentWithoutHashCheckDataProvider')]
    public function testMake3DPaymentWithoutHashCheck(
        array  $order,
        string $txType,
        array  $gatewayResponseData,
        array  $paymentResponse,
        array  $expectedResponse,
        bool   $is3DSuccess,
        bool   $isSuccess
    ): void {
        $config = $this->config;
        $config += [
            'gateway_configs' => [
                'disable_3d_hash_check' => true,
            ],
        ];

        $pos = $this->createGateway($config);

        $this->cryptMock->expects(self::never())
            ->method('check3DHash');

        $this->responseMapperMock->expects(self::once())
            ->method('extractMdStatus')
            ->with($gatewayResponseData)
            ->willReturn('3d-status');

        $this->responseMapperMock->expects(self::once())
            ->method('is3dAuthSuccess')
            ->with('3d-status')
            ->willReturn($is3DSuccess);

        $create3DPaymentRequestData = [
            'create3DPaymentRequestData',
        ];
        if ($is3DSuccess) {
            $this->requestMapperMock->expects(self::once())
                ->method('create3DPaymentRequestData')
                ->with($this->account, $order, $txType, $gatewayResponseData)
                ->willReturn($create3DPaymentRequestData);

            $this->configureClientResponse(
                $txType,
                $create3DPaymentRequestData,
                $paymentResponse,
                $order,
                PosInterface::MODEL_3D_SECURE,
            );

            $this->responseMapperMock->expects(self::once())
                ->method('map3DPaymentData')
                ->with($gatewayResponseData, $paymentResponse, $txType, $order)
                ->willReturn($expectedResponse);
        } else {
            $this->responseMapperMock->expects(self::once())
                ->method('map3DPaymentData')
                ->with($gatewayResponseData, null, $txType, $order)
                ->willReturn($expectedResponse);
            $this->requestMapperMock->expects(self::never())
                ->method('create3DPaymentRequestData');
            $this->eventDispatcherMock->expects(self::never())
                ->method('dispatch');
        }

        $result = $pos->payment(PosInterface::MODEL_3D_SECURE, $order, $txType, null, $gatewayResponseData);

        $this->assertSame($expectedResponse, $result);
        $this->assertSame($isSuccess, $pos->isSuccess());
    }

    public function testMake3DPaymentHashMismatchException(): void
    {
        $data = ['oid' => '20221030FE4C', 'mdStatus' => '1'];

        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->with($this->account, $data)
            ->willReturn(false);

        $this->responseMapperMock->expects(self::once())
            ->method('is3dAuthSuccess')
            ->willReturn(true);

        $this->responseMapperMock->expects(self::never())
            ->method('map3DPaymentData');
        $this->requestMapperMock->expects(self::never())
            ->method('create3DPaymentRequestData');
        $this->eventDispatcherMock->expects(self::never())
            ->method('dispatch');

        $this->expectException(HashMismatchException::class);
        $this->pos->payment(PosInterface::MODEL_3D_SECURE, [], PosInterface::TX_TYPE_PAY_AUTH, null, $data);
    }


    #[DataProvider('makeRegularPaymentDataProvider')]
    public function testMakeRegularPayment(array $order, string $txType): void
    {
        $account     = $this->pos->getAccount();
        $card        = $this->card;
        $requestData = ['createNonSecurePaymentRequestData'];
        $this->requestMapperMock->expects(self::once())
            ->method('createNonSecurePaymentRequestData')
            ->with($account, $order, $txType, $card)
            ->willReturn($requestData);

        $decodedResponse = ['paymentResponse'];
        $this->configureClientResponse(
            $txType,
            $requestData,
            $decodedResponse,
            $order,
            PosInterface::MODEL_NON_SECURE,
            null,
            $this->account
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapPaymentResponse')
            ->with($decodedResponse, $txType, $order)
            ->willReturn(['result']);

        $this->pos->payment(PosInterface::MODEL_NON_SECURE, $order, $txType, $card);
    }

    #[DataProvider('makeRegularPostAuthPaymentDataProvider')]
    public function testMakeRegularPostAuthPayment(array $order): void
    {
        $account     = $this->pos->getAccount();
        $txType      = PosInterface::TX_TYPE_PAY_POST_AUTH;
        $requestData = ['createNonSecurePostAuthPaymentRequestData'];

        $this->requestMapperMock->expects(self::once())
            ->method('createNonSecurePostAuthPaymentRequestData')
            ->with($account, $order)
            ->willReturn($requestData);

        $decodedResponse = ['paymentResponse'];
        $this->configureClientResponse(
            $txType,
            $requestData,
            $decodedResponse,
            $order,
            PosInterface::MODEL_NON_SECURE,
            null,
            $this->account
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapPaymentResponse')
            ->with(['paymentResponse'], $txType, $order)
            ->willReturn(['result']);

        $this->pos->payment(PosInterface::MODEL_NON_SECURE, $order, $txType);
    }

    public static function make3DPaymentDataProvider(): array
    {
        return [
            'auth_fail'                    => [
                'order'               => ['currency' => 'TRY', 'amount' => 1.01],
                'txType'              => 'pay',
                'gatewayResponseData' => ['oid' => '2022103076E7', 'mdStatus' => '0'],
                'paymentResponse'     => [],
                'expected'            => ['status' => 'declined'],
                'is3DSuccess'         => false,
                'isSuccess'           => false,
            ],
            '3d_auth_success_payment_fail' => [
                'order'               => ['currency' => 'TRY', 'amount' => 1.01],
                'txType'              => 'pay',
                'gatewayResponseData' => ['oid' => '20221030FE4C', 'mdStatus' => '1'],
                'paymentResponse'     => ['ProcReturnCode' => '99'],
                'expected'            => ['status' => 'declined'],
                'is3DSuccess'         => true,
                'isSuccess'           => false,
            ],
            'success'                      => [
                'order'               => ['currency' => 'TRY', 'amount' => 1.01],
                'txType'              => 'pay',
                'gatewayResponseData' => ['oid' => '202210304547', 'mdStatus' => '1'],
                'paymentResponse'     => ['ProcReturnCode' => '00'],
                'expected'            => ['status' => 'approved'],
                'is3DSuccess'         => true,
                'isSuccess'           => true,
            ],
        ];
    }

    public static function make3DPaymentWithoutHashCheckDataProvider(): array
    {
        return [
            '3d_auth_success_payment_fail' => [
                'order'               => ['currency' => 'TRY', 'amount' => 1.01],
                'txType'              => 'pay',
                'gatewayResponseData' => ['oid' => '20221030FE4C', 'mdStatus' => '1'],
                'paymentResponse'     => ['ProcReturnCode' => '99'],
                'expected'            => ['status' => 'declined'],
                'is3DSuccess'         => true,
                'isSuccess'           => false,
            ],
            'success'                      => [
                'order'               => ['currency' => 'TRY', 'amount' => 1.01],
                'txType'              => 'pay',
                'gatewayResponseData' => ['oid' => '202210304547', 'mdStatus' => '1'],
                'paymentResponse'     => ['ProcReturnCode' => '00'],
                'expected'            => ['status' => 'approved'],
                'is3DSuccess'         => true,
                'isSuccess'           => true,
            ],
        ];
    }

    public static function cancelDataProvider(): array
    {
        return [
            'fail_1'    => [
                'bank_response' => ['ProcReturnCode' => '99'],
                'expected_data' => ['status' => 'declined'],
                'isSuccess'     => false,
            ],
            'success_1' => [
                'bank_response' => ['ProcReturnCode' => '00'],
                'expected_data' => ['status' => 'approved'],
                'isSuccess'     => true,
            ],
        ];
    }

    public static function refundDataProvider(): array
    {
        return [
            'fail_1' => [
                'bank_response' => ['ProcReturnCode' => '99'],
                'expected_data' => ['status' => 'declined'],
                'isSuccess'     => false,
            ],
        ];
    }

    public static function statusDataProvider(): iterable
    {
        yield [
            'bank_response' => ['ProcReturnCode' => '99'],
            'expected_data' => ['status' => 'declined'],
            'isSuccess'     => false,
        ];
        yield [
            'bank_response' => ['ProcReturnCode' => '00'],
            'expected_data' => ['status' => 'approved'],
            'isSuccess'     => true,
        ];
    }

    public static function orderHistoryDataProvider(): iterable
    {
        yield [
            'bank_response' => ['ProcReturnCode' => '00'],
            'expected_data' => ['status' => 'approved'],
            'isSuccess'     => true,
        ];
        yield [
            'bank_response' => ['ProcReturnCode' => '05'],
            'expected_data' => ['status' => 'declined'],
            'isSuccess'     => false,
        ];
    }

    public static function makeRegularPaymentDataProvider(): array
    {
        return [
            [
                'order'  => [
                    'id' => '2020110828BC',
                ],
                'txType' => PosInterface::TX_TYPE_PAY_AUTH,
            ],
            [
                'order'  => [
                    'id' => '2020110828BC',
                ],
                'txType' => PosInterface::TX_TYPE_PAY_PRE_AUTH,
            ],
        ];
    }

    public static function makeRegularPostAuthPaymentDataProvider(): array
    {
        return [
            [
                'order' => [
                    'id' => '2020110828BC',
                ],
            ],
        ];
    }

    public static function threeDFormDataBadInputsProvider(): array
    {
        return [
            '3d_secure_without_card'  => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_SECURE,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => false,
                'create_with_card'       => false,
                'expectedExceptionClass' => LogicException::class,
                'expectedExceptionMsg'   => 'Bu ödeme modeli için kart bilgileri zorunlu!',
            ],
            '3d_pay_without_card'     => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_PAY,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => false,
                'create_with_card'       => false,
                'expectedExceptionClass' => LogicException::class,
                'expectedExceptionMsg'   => 'Bu ödeme modeli için kart bilgileri zorunlu!',
            ],
            'non_payment_tx_type'     => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_PAY,
                'txType'                 => PosInterface::TX_TYPE_STATUS,
                'isWithCard'             => false,
                'create_with_card'       => false,
                'expectedExceptionClass' => LogicException::class,
                'expectedExceptionMsg'   => 'Hatalı işlem tipi! Desteklenen işlem tipleri: [pay, pre]',
            ],
            'post_auth_tx_type'       => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_PAY,
                'txType'                 => PosInterface::TX_TYPE_PAY_POST_AUTH,
                'isWithCard'             => true,
                'create_with_card'       => false,
                'expectedExceptionClass' => LogicException::class,
                'expectedExceptionMsg'   => 'Hatalı işlem tipi! Desteklenen işlem tipleri: [pay, pre]',
            ],
            'unsupported_form_format' => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_PAY,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => true,
                'create_without_card'    => false,
                'expectedExceptionClass' => UnsupportedFormFormatException::class,
                'expectedExceptionMsg'   => 'Unsupported 3D form format!',
                'formFormat'             => PosInterface::FORM_FORMAT_HTML,
            ],
        ];
    }

    private function createGateway(array $config, ?AbstractPosAccount $account = null): PosInterface
    {
        return new AssecoPos(
            $config,
            $account ?? $this->account,
            $this->requestValueMapper,
            $this->requestMapperMock,
            $this->responseMapperMock,
            $this->cryptMock,
            $this->eventDispatcherMock,
            $this->httpClientStrategyMock,
            $this->loggerMock,
        );
    }

    private function configureClientResponse(
        string              $txType,
        array               $requestData,
        array               $decodedResponse,
        array               $order,
        string              $paymentModel,
        ?string             $apiUrl = null,
        ?AbstractPosAccount $account = null
    ): void {
        $updatedRequestDataPreparedEvent = null;

        $this->httpClientStrategyMock->expects(self::once())
            ->method('getClient')
            ->with($txType, $paymentModel)
            ->willReturn($this->httpClientMock);

        $this->httpClientMock->expects(self::once())
            ->method('request')
            ->with(
                $txType,
                $paymentModel,
                $this->callback(fn (array $requestData): bool => $requestData['test-update-request-data-with-event'] === true),
                $order,
                $apiUrl,
                $account
            )->willReturn($decodedResponse);


        $this->eventDispatcherMock->expects(self::once())
            ->method('dispatch')
            ->with($this->logicalAnd(
                $this->isInstanceOf(RequestDataPreparedEvent::class),
                $this->callback(
                    function (RequestDataPreparedEvent $dispatchedEvent) use ($requestData, $txType, $order, $paymentModel, &$updatedRequestDataPreparedEvent): bool {
                        $updatedRequestDataPreparedEvent = $dispatchedEvent;

                        return $this->pos::class === $dispatchedEvent->getGatewayClass()
                            && $txType === $dispatchedEvent->getTxType()
                            && $requestData === $dispatchedEvent->getRequestData()
                            && $order === $dispatchedEvent->getOrder()
                            && $paymentModel === $dispatchedEvent->getPaymentModel();
                    }
                )
            ))
            ->willReturnCallback(function () use (&$updatedRequestDataPreparedEvent): ?RequestDataPreparedEvent {
                $updatedRequestData                                        = $updatedRequestDataPreparedEvent->getRequestData();
                $updatedRequestData['test-update-request-data-with-event'] = true;
                $updatedRequestDataPreparedEvent->setRequestData($updatedRequestData);

                return $updatedRequestDataPreparedEvent;
            });
    }
}
