<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Gateway;

use PHPUnit\Framework\Attributes\DataProvider;
use LogicException;
use Exception;
use RuntimeException;
use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Client\HttpClientStrategyInterface;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\Request\Mapper\PosNetPosRequestDataMapper;
use Mews\Pos\DataMapper\Request\Mapper\RequestDataMapperInterface;
use Mews\Pos\DataMapper\Request\ValueMapper\AssecoPosRequestValueMapper;
use Mews\Pos\DataMapper\Response\Mapper\PosNetPosResponseDataMapper;
use Mews\Pos\DataMapper\Response\Mapper\ResponseDataMapperInterface;
use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Model\Account\PosNetPosAccount;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exception\HashMismatchException;
use Mews\Pos\Exception\UnsupportedFormFormatException;
use Mews\Pos\Exception\UnsupportedPaymentModelException;
use Mews\Pos\Exception\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Gateway\AbstractGateway;
use Mews\Pos\Gateway\PosNetPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

#[CoversClass(PosNetPos::class)]
#[CoversClass(AbstractGateway::class)]
class PosNetPosTest extends TestCase
{
    private PosNetPosAccount $account;

    private array $config;

    private CreditCardInterface $card;

    private array $order;

    /** @var PosNetPos */
    private PosInterface $pos;

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

    private AssecoPosRequestValueMapper $requestValueMapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'name'              => 'Yapıkredi',
            'class'             => PosNetPos::class,
            'gateway_endpoints' => [
                'gateway_3d' => 'https://setmpos.ykb.com/3DSWebService/YKBPaymentService',
            ],
        ];

        $this->account = AccountFactory::createPosNetPosAccount(
            'yapikredi',
            '6706598320',
            '67005551',
            '27426',
            '10,10,10,10,10,10,10,10'
        );

        $this->order = [
            'id'          => 'YKB_TST_190620093100_024',
            'amount'      => '1.75',
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'lang'        => PosInterface::LANG_TR,
        ];

        $this->requestValueMapper     = new AssecoPosRequestValueMapper();
        $this->requestMapperMock      = $this->createMock(PosNetPosRequestDataMapper::class);
        $this->responseMapperMock     = $this->createMock(PosNetPosResponseDataMapper::class);
        $this->cryptMock              = $this->createMock(CryptInterface::class);
        $this->httpClientStrategyMock = $this->createMock(HttpClientStrategyInterface::class);
        $this->httpClientMock         = $this->createMock(HttpClientInterface::class);
        $this->loggerMock             = $this->createMock(LoggerInterface::class);
        $this->eventDispatcherMock    = $this->createMock(EventDispatcherInterface::class);

        $this->pos = $this->createGateway($this->config);

        $this->card = CreditCardFactory::createForGateway($this->pos, '5555444433332222', '21', '12', '122', 'ahmet');
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

    public function testGet3DFormDataSuccess(): void
    {
        $txType       = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel = PosInterface::MODEL_3D_SECURE;
        $requestData  = ['request-data'];

        $responseData = [
            'approved'               => '1',
            'respCode'               => '',
            'respText'               => '',
            'oosRequestDataResponse' => [
                'data1' => 'AEFE78BFC852867FF57078B723E284D1BD52EED8264C6CBD110A1A9EA5EAA7533D1A82EFD614032D686C507738FDCDD2EDD00B22DEFEFE0795DC4674C16C02EBBFEC9DF0F495D5E23BE487A798BF8293C7C1D517D9600C96CBFD8816C9D8F8257442906CB9B10D8F1AABFBBD24AA6FB0E5533CDE67B0D9EA5ED621B91BF6991D5362182302B781241B56E47BAE1E86BC3D5AE7606212126A4E97AFC2',
                'data2' => '69D04861340091B7014B15158CA3C83413031B406F08B3792A0114C9958E6F0F216966C5EE32EAEEC7158BFF59DFCB77E20CD625',
                'sign'  => '9998F61E1D0C0FB6EC5203A748124F30',
            ],
        ];
        $formData     = [
            'gateway' => 'https://setmpos.ykb.com/3DSWebService/YKBPaymentService',
            'method'  => 'POST',
            'inputs'  => [
                'mid'         => '6706598320',
                'posnetID'    => '27426',
                'posnetData'  => 'AEFE78BFC852867FF57078B723E284D1BD52EED8264C6CBD110A1A9EA5EAA7533D1A82EFD614032D686C507738FDCDD2EDD00B22DEFEFE0795DC4674C16C02EBBFEC9DF0F495D5E23BE487A798BF8293C7C1D517D9600C96CBFD8816C9D8F8257442906CB9B10D8F1AABFBBD24AA6FB0E5533CDE67B0D9EA5ED621B91BF6991D5362182302B781241B56E47BAE1E86BC3D5AE7606212126A4E97AFC2',
                'posnetData2' => '69D04861340091B7014B15158CA3C83413031B406F08B3792A0114C9958E6F0F216966C5EE32EAEEC7158BFF59DFCB77E20CD625',
                'digest'      => '9998F61E1D0C0FB6EC5203A748124F30',
            ],
        ];
        $order        = [
            'id'          => 'TST_190620093100_024',
            'amount'      => '1.75',
            'success_url' => 'https://domain.com/success',
        ];

        $this->requestMapperMock->expects(self::once())
            ->method('create3DFormInitializeRequestData')
            ->with($this->pos->getAccount(), $order, PosInterface::MODEL_3D_SECURE, $txType, $this->card)
            ->willReturn($requestData);

        $this->configureClientResponse(
            $txType,
            $requestData,
            $responseData,
            $order,
            $paymentModel,
        );

        $this->requestMapperMock->expects(self::once())
            ->method('create3DFormData')
            ->with(
                $this->pos->getAccount(),
                $order,
                $paymentModel,
                $txType,
                $this->config['gateway_endpoints']['gateway_3d'],
                null,
                $responseData['oosRequestDataResponse']
            )
            ->willReturn($formData);

        $result = $this->pos->get3DFormData($order, PosInterface::MODEL_3D_SECURE, $txType, $this->card);

        $this->assertSame($formData, $result);
    }

    public function testGet3DFormDataMissingOosRequestDataResponse(): void
    {
        $txType      = PosInterface::TX_TYPE_PAY_AUTH;
        $requestData = ['request-data'];
        $order       = $this->order;

        $this->requestMapperMock->expects(self::once())
            ->method('create3DFormInitializeRequestData')
            ->with($this->pos->getAccount(), $order, PosInterface::MODEL_3D_SECURE, $txType, $this->card)
            ->willReturn($requestData);

        $this->configureClientResponse(
            $txType,
            $requestData,
            ['approved' => '1', 'respCode' => '', 'respText' => ''],
            $order,
            PosInterface::MODEL_3D_SECURE,
        );

        $this->requestMapperMock->expects(self::never())
            ->method('create3DFormData');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Beklenmeyen yanıt: oosRequestDataResponse eksik.');

        $this->pos->get3DFormData($order, PosInterface::MODEL_3D_SECURE, $txType, $this->card);
    }


    /**
     * @return void
     *
     * @throws Exception
     */
    public function testGet3DFormDataOosTransactionFail(): void
    {
        $txType      = PosInterface::TX_TYPE_PAY_AUTH;
        $requestData = ['request-data'];
        $order       = $this->order;
        $this->expectException(Exception::class);
        $this->requestMapperMock->expects(self::once())
            ->method('create3DFormInitializeRequestData')
            ->with($this->pos->getAccount(), $order, PosInterface::MODEL_3D_SECURE, $txType, $this->card)
            ->willReturn($requestData);

        $responseData = [
            'approved' => '0',
            'respCode' => '0003',
            'respText' => '148 MID,TID,IP HATALI:89.244.149.137',
        ];

        $this->configureClientResponse(
            $txType,
            $requestData,
            $responseData,
            $order,
            PosInterface::MODEL_3D_SECURE,
        );

        $this->requestMapperMock->expects(self::never())
            ->method('create3DFormData');

        $this->pos->get3DFormData($order, PosInterface::MODEL_3D_SECURE, $txType, $this->card);
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

    #[DataProvider('make3DPaymentDataProvider')]
    public function testMake3DPayment(
        array  $order,
        string $txType,
        array  $gatewayResponseData,
        array  $resolveResponse,
        array  $paymentResponse,
        array  $expectedResponse,
        bool   $is3DSuccess,
        bool   $isSuccess
    ): void {
        $paymentModel = PosInterface::MODEL_3D_SECURE;

        if ($is3DSuccess) {
            $this->cryptMock->expects(self::once())
                ->method('check3DHash')
                ->with($this->account, $resolveResponse['oosResolveMerchantDataResponse'])
                ->willReturn(true);
        }

        $this->responseMapperMock->expects(self::once())
            ->method('extractMdStatus')
            ->with($resolveResponse)
            ->willReturn('3d-status');

        $this->responseMapperMock->expects(self::once())
            ->method('is3dAuthSuccess')
            ->with('3d-status')
            ->willReturn($is3DSuccess);


        $resolveMerchantRequestData = [
            'resolveMerchantRequestData',
        ];
        $create3DPaymentRequestData = [
            'create3DPaymentRequestData',
        ];
        $this->requestMapperMock->expects(self::once())
            ->method('create3DResolveMerchantRequestData')
            ->with($this->account, $order, $gatewayResponseData)
            ->willReturn($resolveMerchantRequestData);

        if ($is3DSuccess) {
            $this->requestMapperMock->expects(self::once())
                ->method('create3DPaymentRequestData')
                ->with($this->account, $order, $txType, $gatewayResponseData)
                ->willReturn($create3DPaymentRequestData);

            $request1UpdatedData = $resolveMerchantRequestData + [
                    'test-update-request-data-with-event1' => true,
                ];
            $request2UpdatedData = $create3DPaymentRequestData + [
                    'test-update-request-data-with-event2' => true,
                ];

            $this->httpClientStrategyMock->expects(self::exactly(2))
                ->method('getClient')
                ->with($txType, $paymentModel)
                ->willReturn($this->httpClientMock);

            $this->httpClientMock->expects(self::exactly(2))
                ->method('request')
                ->willReturnMap([
                    [
                        $txType,
                        $paymentModel,
                        $request1UpdatedData,
                        $order,
                        null,
                        null,
                        null,
                        $resolveResponse,
                    ],
                    [
                        $txType,
                        $paymentModel,
                        $request2UpdatedData,
                        $order,
                        null,
                        null,
                        null,
                        $paymentResponse,
                    ],
                ]);

            $dispatchCallCount = 0;
            $this->eventDispatcherMock->expects(self::exactly(2))
                ->method('dispatch')
                ->with($this->isInstanceOf(RequestDataPreparedEvent::class))
                ->willReturnCallback(function ($dispatchedEvent) use (
                    &$dispatchCallCount,
                    $resolveMerchantRequestData,
                    $create3DPaymentRequestData,
                    $txType,
                    $order,
                    $paymentModel
                ): RequestDataPreparedEvent {
                    $dispatchCallCount++;
                    $this->assertInstanceOf(RequestDataPreparedEvent::class, $dispatchedEvent);
                    $this->assertSame($this->pos::class, $dispatchedEvent->getGatewayClass());
                    $this->assertSame($txType, $dispatchedEvent->getTxType());
                    $this->assertSame($order, $dispatchedEvent->getOrder());
                    $this->assertSame($paymentModel, $dispatchedEvent->getPaymentModel());

                    if ($dispatchCallCount === 1) {
                        $this->assertSame($resolveMerchantRequestData, $dispatchedEvent->getRequestData());
                        $updatedRequestData                                         = $dispatchedEvent->getRequestData();
                        $updatedRequestData['test-update-request-data-with-event1'] = true;
                        $dispatchedEvent->setRequestData($updatedRequestData);
                    } else {
                        $this->assertSame($create3DPaymentRequestData, $dispatchedEvent->getRequestData());
                        $updatedRequestData                                         = $dispatchedEvent->getRequestData();
                        $updatedRequestData['test-update-request-data-with-event2'] = true;
                        $dispatchedEvent->setRequestData($updatedRequestData);
                    }

                    return $dispatchedEvent;
                });

            $this->responseMapperMock->expects(self::once())
                ->method('map3DPaymentData')
                ->with($resolveResponse, $paymentResponse, $txType, $order)
                ->willReturn($expectedResponse);
        } else {
            $this->configureClientResponse(
                $txType,
                $resolveMerchantRequestData,
                $resolveResponse,
                $order,
                $paymentModel
            );

            $this->responseMapperMock->expects(self::once())
                ->method('map3DPaymentData')
                ->with($resolveResponse, null, $txType, $order)
                ->willReturn($expectedResponse);

            $this->requestMapperMock->expects(self::never())
                ->method('create3DPaymentRequestData');
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
        array  $resolveResponse,
        array  $paymentResponse,
        array  $expectedResponse,
        bool   $is3DSuccess,
        bool   $isSuccess
    ): void {
        $paymentModel = PosInterface::MODEL_3D_SECURE;

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
            ->with($resolveResponse)
            ->willReturn('3d-status');

        $this->responseMapperMock->expects(self::once())
            ->method('is3dAuthSuccess')
            ->with('3d-status')
            ->willReturn($is3DSuccess);


        $resolveMerchantRequestData = [
            'resolveMerchantRequestData',
        ];
        $create3DPaymentRequestData = [
            'create3DPaymentRequestData',
        ];
        $this->requestMapperMock->expects(self::once())
            ->method('create3DResolveMerchantRequestData')
            ->with($this->account, $order, $gatewayResponseData)
            ->willReturn($resolveMerchantRequestData);

        if ($is3DSuccess) {
            $this->requestMapperMock->expects(self::once())
                ->method('create3DPaymentRequestData')
                ->with($this->account, $order, $txType, $gatewayResponseData)
                ->willReturn($create3DPaymentRequestData);

            $request1UpdatedData = $resolveMerchantRequestData + [
                    'test-update-request-data-with-event1' => true,
                ];
            $request2UpdatedData = $create3DPaymentRequestData + [
                    'test-update-request-data-with-event2' => true,
                ];

            $this->httpClientStrategyMock->expects(self::exactly(2))
                ->method('getClient')
                ->with($txType, $paymentModel)
                ->willReturn($this->httpClientMock);

            $this->httpClientMock->expects(self::exactly(2))
                ->method('request')
                ->willReturnMap([
                    [
                        $txType,
                        $paymentModel,
                        $request1UpdatedData,
                        $order,
                        null,
                        null,
                        null,
                        $resolveResponse,
                    ],
                    [
                        $txType,
                        $paymentModel,
                        $request2UpdatedData,
                        $order,
                        null,
                        null,
                        null,
                        $paymentResponse,
                    ],
                ]);

            $dispatchCallCount = 0;
            $this->eventDispatcherMock->expects(self::exactly(2))
                ->method('dispatch')
                ->with($this->isInstanceOf(RequestDataPreparedEvent::class))
                ->willReturnCallback(function ($dispatchedEvent) use (
                    &$dispatchCallCount,
                    $resolveMerchantRequestData,
                    $create3DPaymentRequestData,
                    $txType,
                    $order,
                    $paymentModel
                ): RequestDataPreparedEvent {
                    $dispatchCallCount++;
                    $this->assertInstanceOf(RequestDataPreparedEvent::class, $dispatchedEvent);
                    $this->assertSame($this->pos::class, $dispatchedEvent->getGatewayClass());
                    $this->assertSame($txType, $dispatchedEvent->getTxType());
                    $this->assertSame($order, $dispatchedEvent->getOrder());
                    $this->assertSame($paymentModel, $dispatchedEvent->getPaymentModel());

                    if ($dispatchCallCount === 1) {
                        $this->assertSame($resolveMerchantRequestData, $dispatchedEvent->getRequestData());
                        $updatedRequestData                                         = $dispatchedEvent->getRequestData();
                        $updatedRequestData['test-update-request-data-with-event1'] = true;
                        $dispatchedEvent->setRequestData($updatedRequestData);
                    } else {
                        $this->assertSame($create3DPaymentRequestData, $dispatchedEvent->getRequestData());
                        $updatedRequestData                                         = $dispatchedEvent->getRequestData();
                        $updatedRequestData['test-update-request-data-with-event2'] = true;
                        $dispatchedEvent->setRequestData($updatedRequestData);
                    }

                    return $dispatchedEvent;
                });

            $this->responseMapperMock->expects(self::once())
                ->method('map3DPaymentData')
                ->with($resolveResponse, $paymentResponse, $txType, $order)
                ->willReturn($expectedResponse);
        } else {
            $this->configureClientResponse(
                $txType,
                $resolveMerchantRequestData,
                $resolveResponse,
                $order,
                $paymentModel
            );

            $this->responseMapperMock->expects(self::once())
                ->method('map3DPaymentData')
                ->with($resolveResponse, null, $txType, $order)
                ->willReturn($expectedResponse);

            $this->requestMapperMock->expects(self::never())
                ->method('create3DPaymentRequestData');
        }

        $result = $pos->payment(PosInterface::MODEL_3D_SECURE, $order, $txType, null, $gatewayResponseData);

        $this->assertSame($expectedResponse, $result);
        $this->assertSame($isSuccess, $pos->isSuccess());
    }

    public function testMake3DPaymentHashMismatchException(): void
    {
        $gatewayResponseData = [
            'approved'                       => '1',
            'respCode'                       => '',
            'respText'                       => '',
            'oosResolveMerchantDataResponse' => [
                'mdStatus'       => '1',
                'mdErrorMessage' => '',
                'mac'            => 'y0fU6rRA0OvqJ5GN6uMdHVu6Xra7QR1qeT9rN7R1L+o=',
            ],
        ];
        $txType              = PosInterface::TX_TYPE_PAY_AUTH;

        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->with($this->account, $gatewayResponseData['oosResolveMerchantDataResponse'])
            ->willReturn(false);

        $this->responseMapperMock->expects(self::once())
            ->method('is3dAuthSuccess')
            ->willReturn(true);

        $resolveMerchantRequestData = [
            'resolveMerchantRequestData',
        ];
        $this->requestMapperMock->expects(self::once())
            ->method('create3DResolveMerchantRequestData')
            ->willReturn($resolveMerchantRequestData);

        $this->requestMapperMock->expects(self::never())
            ->method('create3DPaymentRequestData');

        $this->configureClientResponse(
            PosInterface::TX_TYPE_PAY_AUTH,
            $resolveMerchantRequestData,
            $gatewayResponseData,
            [],
            PosInterface::MODEL_3D_SECURE
        );

        $this->expectException(HashMismatchException::class);
        $this->pos->payment(PosInterface::MODEL_3D_SECURE, [], $txType, null, $gatewayResponseData);
    }

    public function testMake3DHostPayment(): void
    {
        $txType = PosInterface::TX_TYPE_PAY_AUTH;

        $this->expectException(UnsupportedPaymentModelException::class);
        $this->pos->payment(PosInterface::MODEL_3D_HOST, [], $txType, null, ['abc']);
    }

    public function testMake3DPayPayment(): void
    {
        $txType = PosInterface::TX_TYPE_PAY_AUTH;

        $this->expectException(UnsupportedPaymentModelException::class);
        $this->pos->payment(PosInterface::MODEL_3D_PAY, [], $txType, null, ['abc']);
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

        $decodedResponse = ['decodedData'];
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

        $decodedResponse = ['decodedData'];
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

        $this->pos->payment(PosInterface::MODEL_NON_SECURE, $order, $txType);
    }


    #[DataProvider('statusRequestDataProvider')]
    public function testStatusRequest(array $order): void
    {
        $account     = $this->pos->getAccount();
        $txType      = PosInterface::TX_TYPE_STATUS;
        $requestData = ['createStatusRequestData'];

        $this->requestMapperMock->expects(self::once())
            ->method('createStatusRequestData')
            ->with($account, $order)
            ->willReturn($requestData);

        $decodedResponse = ['decodedData'];
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
            ->method('mapStatusResponse')
            ->with($decodedResponse)
            ->willReturn(['result']);

        $this->pos->status($order);
    }

    #[DataProvider('cancelRequestDataProvider')]
    public function testCancelRequest(array $order): void
    {
        $account     = $this->pos->getAccount();
        $txType      = PosInterface::TX_TYPE_CANCEL;
        $requestData = ['createCancelRequestData'];

        $this->requestMapperMock->expects(self::once())
            ->method('createCancelRequestData')
            ->with($account, $order)
            ->willReturn($requestData);

        $decodedResponse = ['decodedData'];
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
            ->method('mapCancelResponse')
            ->with($decodedResponse)
            ->willReturn(['result']);

        $this->pos->cancel($order);
    }

    #[DataProvider('refundRequestDataProvider')]
    public function testRefundRequest(array $order): void
    {
        $account     = $this->pos->getAccount();
        $txType      = PosInterface::TX_TYPE_REFUND;
        $requestData = ['createRefundRequestData'];

        $this->requestMapperMock->expects(self::once())
            ->method('createRefundRequestData')
            ->with($account, $order, $txType)
            ->willReturn($requestData);

        $decodedResponse = ['decodedData'];
        $this->configureClientResponse(
            $txType,
            $requestData,
            $decodedResponse,
            $order,
            PosInterface::MODEL_NON_SECURE,
            null,
            $this->account,
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapRefundResponse')
            ->with($decodedResponse)
            ->willReturn(['result']);

        $this->pos->refund($order);
    }

    public function testHistoryRequest(): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->pos->history([]);
    }

    public function testOrderHistoryRequest(): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->pos->orderHistory([]);
    }

    #[DataProvider('customQueryRequestDataProvider')]
    public function testCustomQueryRequest(array $requestData, ?string $apiUrl): void
    {
        $account = $this->pos->getAccount();
        $txType  = PosInterface::TX_TYPE_CUSTOM_QUERY;

        $updatedRequestData = $requestData + [
                'abc' => 'def',
            ];
        $this->requestMapperMock->expects(self::once())
            ->method('createCustomQueryRequestData')
            ->with($account, $requestData)
            ->willReturn($updatedRequestData);

        $this->configureClientResponse(
            $txType,
            $updatedRequestData,
            ['decodedResponse'],
            $requestData,
            PosInterface::MODEL_NON_SECURE,
            $apiUrl,
            $this->account
        );

        $this->pos->customQuery($requestData, $apiUrl);
    }

    public function testPaymentWithMissing3DGatewayResponseData(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('3D tür ödeme modelleri için bankadan 3D otorizasyon yanıt verileri gereklidir!');
        $this->pos->payment(PosInterface::MODEL_3D_SECURE, [], PosInterface::TX_TYPE_PAY_AUTH, null, null);
    }

    public function testPaymentWithUnsupportedPaymentModel(): void
    {
        $this->expectException(UnsupportedPaymentModelException::class);
        $this->pos->payment('unknown_model', [], PosInterface::TX_TYPE_PAY_AUTH, null, ['data']);
    }

    public function testMakeRegularPaymentWithInvalidTxType(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Invalid transaction type "cancel" provided');
        $this->pos->makeRegularPayment([], $this->card, PosInterface::TX_TYPE_CANCEL);
    }

    public static function customQueryRequestDataProvider(): array
    {
        return [
            [
                'requestData' => [
                    'id' => '2020110828BC',
                ],
                'api_url'     => 'https://setmpos.ykb.com/PosnetWebService/XML/xxxx',
            ],
            [
                'requestData' => [
                    'id' => '2020110828BC',
                ],
                'api_url'     => null,
            ],
        ];
    }

    public static function make3DPaymentDataProvider(): array
    {
        $resolveMerchantResponseData = [
            'MerchantPacket' => '',
            'BankPacket'     => '',
            'Sign'           => '',
        ];

        return [
            'auth_fail'      => [
                'order'           => [
                    'id' => '80603153823',
                ],
                'txType'          => 'pay',
                'request'         => $resolveMerchantResponseData,
                'resolveResponse' => [
                    'oosResolveMerchantDataResponse' => [
                        'xid'            => 'YKB_0000080603153823',
                        'amount'         => '5696',
                        'currency'       => 'TL',
                        'mdErrorMessage' => 'None 3D - Secure Transaction',
                        'mac'            => 'ED7254A3ABC264QOP67MN',
                    ],
                ],
                'paymentResponse' => [],
                'expected'        => [
                    'transaction_type'     => 'pay',
                    'transaction_security' => 'MPI fallback',
                    'remote_order_id'      => 'YKB_0000080603153823',
                    'status'               => 'declined',
                    'amount'               => 56.96,
                    'currency'             => 'TRY',
                    'payment_model'        => '3d',
                ],
                'is3DSuccess'     => false,
                'isSuccess'       => false,
            ],
            'fail2-md-empty' => [
                'order'           => [
                    'id' => '80603153823',
                ],
                'txType'          => 'pay',
                'request'         => $resolveMerchantResponseData,
                'resolveResponse' => [
                    'oosResolveMerchantDataResponse' => [
                        'xid'            => 'YKB_0000080603153823',
                        'amount'         => '5696',
                        'mdErrorMessage' => 'None 3D - Secure Transaction',
                        'mac'            => 'ED7254A3ABC264QOP67MN',
                    ],
                ],
                'paymentResponse' => [],
                'expected'        => [
                    'transaction_id'   => null,
                    'transaction_type' => 'pay',
                    'md_error_message' => 'None 3D - Secure Transaction',
                    'order_id'         => '80603153823',
                    'remote_order_id'  => 'YKB_0000080603153823',
                    'proc_return_code' => null,
                    'status'           => 'declined',
                ],
                'is3DSuccess'     => false,
                'isSuccess'       => false,
            ],
            'success'        => [
                'order'           => [
                    'id' => '80603153823',
                ],
                'txType'          => 'pay',
                'request'         => $resolveMerchantResponseData,
                'resolveResponse' => [
                    'approved'                       => '1',
                    'respCode'                       => '',
                    'respText'                       => '',
                    'oosResolveMerchantDataResponse' => [
                        'xid'            => 'YKB_0000080603153823',
                        'mdStatus'       => '1',
                        'mdErrorMessage' => '',
                        'mac'            => 'y0fU6rRA0OvqJ5GN6uMdHVu6Xra7QR1qeT9rN7R1L+o=',
                    ],
                ],
                'paymentResponse' => [
                    'approved' => '1',
                    'respCode' => '',
                    'respText' => '00',
                ],
                'expected'        => [
                    'transaction_id'   => null,
                    'transaction_type' => 'pay',
                    'status'           => 'approved',
                ],
                'is3DSuccess'     => true,
                'isSuccess'       => true,
            ],
        ];
    }

    public static function make3DPaymentWithoutHashCheckDataProvider(): array
    {
        $resolveMerchantResponseData = [
            'MerchantPacket' => '',
            'BankPacket'     => '',
            'Sign'           => '',
        ];

        return [
            'success' => [
                'order'           => [
                    'id' => '80603153823',
                ],
                'txType'          => 'pay',
                'request'         => $resolveMerchantResponseData,
                'resolveResponse' => [
                    'approved'                       => '1',
                    'respCode'                       => '',
                    'respText'                       => '',
                    'oosResolveMerchantDataResponse' => [
                        'xid'      => 'YKB_0000080603153823',
                        'amount'   => '5696',
                        'currency' => 'TL',
                        'mac'      => 'y0fU6rRA0OvqJ5GN6uMdHVu6Xra7QR1qeT9rN7R1L+o=',
                    ],
                ],
                'paymentResponse' => [
                    'approved'   => '1',
                    'respCode'   => '',
                    'respText'   => '00',
                    'mac'        => 'DF2323A3BMC782QOP42RT',
                    'hostlogkey' => '0000000002P0806031',
                    'authCode'   => '901477',
                ],
                'expected'        => [
                    'transaction_type' => 'pay',
                    'md_status'        => '1',
                    'status'           => 'approved',
                    'amount'           => 56.96,
                    'currency'         => 'TRY',
                    'payment_model'    => '3d',
                ],
                'is3DSuccess'     => true,
                'isSuccess'       => true,
            ],
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

    public static function statusRequestDataProvider(): array
    {
        return [
            [
                'order' => [
                    'id' => '2020110828BC',
                ],
            ],
        ];
    }

    public static function cancelRequestDataProvider(): array
    {
        return [
            [
                'order' => [
                    'id' => '2020110828BC',
                ],
            ],
        ];
    }

    public static function refundRequestDataProvider(): array
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
            '3d_secure_without_card'    => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_SECURE,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => false,
                'create_without_card'    => false,
                'expectedExceptionClass' => LogicException::class,
                'expectedExceptionMsg'   => 'Bu ödeme modeli için kart bilgileri zorunlu!',
            ],
            'unsupported_payment_model' => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_PAY,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => false,
                'create_without_card'    => false,
                'expectedExceptionClass' => LogicException::class,
                'expectedExceptionMsg'   => 'Mews\Pos\Gateway\PosNetPos ödeme altyapıda [pay] işlem tipi [3d, regular] ödeme model(ler) desteklemektedir. Sağlanan ödeme model: [3d_pay].',
            ],
            'non_payment_tx_type'       => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_PAY,
                'txType'                 => PosInterface::TX_TYPE_STATUS,
                'isWithCard'             => false,
                'create_with_card'       => false,
                'expectedExceptionClass' => LogicException::class,
                'expectedExceptionMsg'   => 'Hatalı işlem tipi! Desteklenen işlem tipleri: [pay, pre]',
            ],
            'post_auth_tx_type'         => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_PAY,
                'txType'                 => PosInterface::TX_TYPE_PAY_POST_AUTH,
                'isWithCard'             => true,
                'create_with_card'       => false,
                'expectedExceptionClass' => LogicException::class,
                'expectedExceptionMsg'   => 'Hatalı işlem tipi! Desteklenen işlem tipleri: [pay, pre]',
            ],
            'unsupported_form_format'   => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_SECURE,
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
        return new PosNetPos(
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
