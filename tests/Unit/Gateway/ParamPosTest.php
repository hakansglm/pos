<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Gateway;

use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use DateTimeImmutable;
use LogicException;
use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Client\HttpClientStrategyInterface;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\Request\Mapper\ParamPosRequestDataMapper;
use Mews\Pos\DataMapper\Request\Mapper\RequestDataMapperInterface;
use Mews\Pos\DataMapper\Request\ValueMapper\ParamPosRequestValueMapper;
use Mews\Pos\DataMapper\Response\Mapper\ResponseDataMapperInterface;
use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Model\Account\ParamPosAccount;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exception\HashMismatchException;
use Mews\Pos\Exception\UnsupportedFormFormatException;
use Mews\Pos\Exception\UnsupportedPaymentModelException;
use Mews\Pos\Exception\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Gateway\AbstractGateway;
use Mews\Pos\Gateway\ParamPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

#[CoversClass(ParamPos::class)]
#[CoversClass(AbstractGateway::class)]
class ParamPosTest extends TestCase
{
    private ParamPosAccount $account;

    private array $config;

    /** @var ParamPos */
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

    /** @var ParamPosRequestValueMapper & MockObject */
    private MockObject $requestValueMapperMock;

    private CreditCardInterface $card;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'name'              => 'param-pos',
            'class'             => ParamPos::class,
            'gateway_endpoints' => [],
        ];

        $this->account = AccountFactory::createParamPosAccount(
            'param-pos',
            '10738',
            'Test',
            'Test',
            '0c13d406-873b-403b-9c09-a5766840d98c'
        );

        $this->requestValueMapperMock = $this->createMock(ParamPosRequestValueMapper::class);
        $this->requestMapperMock      = $this->createMock(ParamPosRequestDataMapper::class);
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
        $this->requestValueMapperMock->expects(self::once())
            ->method('getCurrencyMappings')
            ->willReturn([PosInterface::CURRENCY_TRY => '1000']);
        $this->assertSame($this->account, $this->pos->getAccount());
        $this->assertSame([PosInterface::CURRENCY_TRY], $this->pos->getCurrencies());
        $this->assertFalse($this->pos->isTestMode());
        $this->assertSame($this->cryptMock, $this->pos->getCrypt());
    }

    #[DataProvider('threeDFormDataProvider')]
    public function testGet3DFormData(
        array   $order,
        string  $paymentModel,
        string  $txType,
        bool    $isWithCard,
        array   $requestData,
        ?string $gatewayUrl,
        array   $decodedResponseData,
        $formData
    ): void {
        $card = $isWithCard ? $this->card : null;
        $this->requestMapperMock->expects(self::once())
            ->method('create3DFormInitializeRequestData')
            ->with($this->pos->getAccount(), $order)
            ->willReturn($requestData);

        $this->configureClientResponse(
            PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD,
            $requestData,
            $decodedResponseData,
            $order,
            $paymentModel
        );

        $this->requestMapperMock->expects(self::once())
            ->method('create3DFormData')
            ->with(
                $this->pos->getAccount(),
                $order,
                $paymentModel,
                $txType,
                $gatewayUrl,
                null,
                $decodedResponseData
            )
            ->willReturn($formData);

        $actual = $this->pos->get3DFormData($order, $paymentModel, $txType, $card, !$isWithCard);

        $this->assertSame($actual, $formData);
    }

    #[DataProvider('threeDFormDataFailResponseProvider')]
    public function testGet3DFormDataFailResponse(
        array  $order,
        string $paymentModel,
        string $txType,
        array  $requestData,
        array  $decodedResponseData
    ): void {
        $this->requestMapperMock->expects(self::once())
            ->method('create3DFormInitializeRequestData')
            ->with($this->pos->getAccount(), $order)
            ->willReturn($requestData);

        $this->configureClientResponse(
            PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD,
            $requestData,
            $decodedResponseData,
            $order,
            $paymentModel
        );

        $this->requestMapperMock->expects(self::never())
            ->method('create3DFormData');

        $this->expectException(RuntimeException::class);
        $this->pos->get3DFormData($order, $paymentModel, $txType, $this->card);
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
                PosInterface::MODEL_3D_SECURE
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

    #[DataProvider('make3DPaymentDataProvider')]
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
                PosInterface::MODEL_3D_SECURE
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

    #[DataProvider('make3DPaymentDataForeignCurrencyProvider')]
    public function testMake3DPaymentForeignCurrency(
        array  $order,
        string $txType,
        array  $gatewayResponseData,
        array  $expectedResponse,
        bool   $isSuccess
    ): void {
        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->with($this->pos->getAccount(), $gatewayResponseData)
            ->willReturn(true);

        $this->responseMapperMock->expects(self::never())
            ->method('extractMdStatus');

        $this->responseMapperMock->expects(self::never())
            ->method('is3dAuthSuccess');

        $this->responseMapperMock->expects(self::once())
            ->method('map3DPayResponseData')
            ->willReturn($expectedResponse);

        $result = $this->pos->payment(PosInterface::MODEL_3D_SECURE, $order, $txType, null, $gatewayResponseData);

        $this->assertSame($expectedResponse, $result);
        $this->assertSame($isSuccess, $this->pos->isSuccess());
    }

    public function testMake3DPaymentHashMismatchException(): void
    {
        $data = [
            'md'        => '444676:13FDE30917BF65D853787DB838390849D73151A10FC8C1192AC72660F2464521:3473:##190100000',
            'islemHash' => 'jF0PD92E+dM394Z1h5qm4SB6pPo=',
        ];

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

    #[DataProvider('make3DPayPaymentDataProvider')]
    public function testMake3DPayPayment(
        array  $order,
        string $txType,
        array  $gatewayResponseData,
        array  $expectedResponse,
        bool   $isSuccess
    ): void {
        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->with($this->pos->getAccount(), $gatewayResponseData)
            ->willReturn(true);

        $this->responseMapperMock->expects(self::never())
            ->method('extractMdStatus');

        $this->responseMapperMock->expects(self::never())
            ->method('is3dAuthSuccess');

        $this->responseMapperMock->expects(self::once())
            ->method('map3DPayResponseData')
            ->willReturn($expectedResponse);

        $result = $this->pos->payment(PosInterface::MODEL_3D_PAY, $order, $txType, null, $gatewayResponseData);

        $this->assertSame($expectedResponse, $result);
        $this->assertSame($isSuccess, $this->pos->isSuccess());
    }

    #[DataProvider('make3DPayPaymentDataProvider')]
    public function testMake3DPayPaymentWithoutHashCheck(
        array  $order,
        string $txType,
        array  $gatewayResponseData,
        array  $expectedResponse,
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

        $this->responseMapperMock->expects(self::never())
            ->method('extractMdStatus');

        $this->responseMapperMock->expects(self::never())
            ->method('is3dAuthSuccess');

        $this->responseMapperMock->expects(self::once())
            ->method('map3DPayResponseData')
            ->willReturn($expectedResponse);

        $result = $pos->payment(PosInterface::MODEL_3D_PAY, $order, $txType, null, $gatewayResponseData);

        $this->assertSame($expectedResponse, $result);
        $this->assertSame($isSuccess, $pos->isSuccess());
    }

    public function testMake3DPayPaymentHashMismatchException(): void
    {
        $gatewayResponseData = [
            'TURKPOS_RETVAL_Hash'              => 'LOpkL9J8vne8E2j0A0HKOhUWGhI=',
            'TURKPOS_RETVAL_Islem_GUID'        => '77f11031-cce8-4131-bf95-142303732608',
            'TURKPOS_RETVAL_SanalPOS_Islem_ID' => '6021847062',
        ];
        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->with($this->account, $gatewayResponseData)
            ->willReturn(false);

        $this->expectException(HashMismatchException::class);

        $this->pos->payment(PosInterface::MODEL_3D_PAY, [], PosInterface::TX_TYPE_PAY_AUTH, null, $gatewayResponseData);
    }

    public function testMake3DHostPayment(): void
    {
        $this->expectException(UnsupportedPaymentModelException::class);
        $this->pos->payment(PosInterface::MODEL_3D_HOST, [], PosInterface::TX_TYPE_PAY_AUTH, null, ['abc']);
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
            $this->account
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapRefundResponse')
            ->with($decodedResponse)
            ->willReturn(['result']);

        $this->pos->refund($order);
    }

    #[DataProvider('historyRequestDataProvider')]
    public function testHistoryRequest(array $order): void
    {
        $account     = $this->pos->getAccount();
        $txType      = PosInterface::TX_TYPE_HISTORY;
        $requestData = ['createHistoryRequestData'];

        $this->requestMapperMock->expects(self::once())
            ->method('createHistoryRequestData')
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
            ->method('mapHistoryResponse')
            ->with($decodedResponse)
            ->willReturn(['result']);

        $this->pos->history($order);
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

    public static function customQueryRequestDataProvider(): array
    {
        return [
            [
                'requestData' => [
                    'id' => '2020110828BC',
                ],
                'api_url'     => 'https://test-dmz.param.com.tr/turkpos.ws/service_turkpos_test.asmx/abc',
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
        return [
            'auth_fail'                    => [
                'order'               => [
                    'currency' => 'TRY',
                    'amount'   => 1.01,
                ],
                'txType'              => 'pay',
                'gatewayResponseData' => [
                    'md'                => '444676:84E83D96A7CEC3A5815D49EB7F64D2709D1BC30425D578D118B9819A81749FB8:4429:##190100000',
                    'mdStatus'          => '0',
                    'orderId'           => '20241229C152',
                    'transactionAmount' => '1000,01',
                    'islemGUID'         => 'c1ee369b-ec27-4ab6-8c27-2e15e62793d3',
                    'islemHash'         => 'N1/W7/GcbuT3UVwVM9Q5C/rmoKg=',
                    'bankResult'        => 'N-status/Challenge authentication via ACS: https://3ds-acs.test.modirum.com/mdpayacs/wkwLCHgiNwZCiVZp/creq;token=338863271.17354  0',
                    'dc'                => null,
                    'dcURL'             => 'https://test-dmz.param.com.tr/turkpos.ws/service_turkpos_test.asmx',
                ],
                'paymentResponse'     => [],
                'expected'            => [
                    'amount'        => 1000.01,
                    'order_id'      => '20241229C152',
                    'payment_model' => '3d',
                    'status'        => 'declined',
                ],
                'is3DSuccess'         => false,
                'isSuccess'           => false,
            ],
            '3d_auth_success_payment_fail' => [
                'order'               => [
                    'currency' => 'TRY',
                    'amount'   => 1.01,
                ],
                'txType'              => 'pay',
                'gatewayResponseData' => [
                    'md'       => '444676:B1748AA7FF30A96AADFECC19670A3038C1419A842DD221D2408708A84FE9D811:4011:##190100000',
                    'mdStatus' => '1',

                ],
                'paymentResponse'     => [
                    'TP_WMD_PayResponse' => [
                        'TP_WMD_PayResult' => [
                            'Sonuc'          => '-100',
                            'Sonuc_Ack'      => 'Hesap bulunamadı.',
                            'Bank_Sonuc_Kod' => '-1',
                            'Komisyon_Oran'  => '0',
                        ],
                    ],
                ],
                'expected'            => [
                    'amount'        => 10.01,
                    'error_message' => 'Hesap bulunamadı.',
                    'status'        => 'declined',
                ],
                'is3DSuccess'         => false,
                'isSuccess'           => false,
            ],
            'success'                      => [
                'order'               => [
                    'currency' => 'TRY',
                    'amount'   => 1.01,
                ],
                'txType'              => 'pay',
                'gatewayResponseData' => [
                    'md'       => '444676:13FDE30917BF65D853787DB838390849D73151A10FC8C1192AC72660F2464521:3473:##190100000',
                    'mdStatus' => '1',
                    'dcURL'    => 'https://test-dmz.param.com.tr/turkpos.ws/service_turkpos_test.asmx',
                ],
                'paymentResponse'     => [
                    'TP_WMD_PayResponse' => [
                        'TP_WMD_PayResult' => [
                            'Sonuc' => '1',

                        ],
                    ],
                ],
                'expected'            => [
                    'amount'           => 10.01,
                    'md_status'        => '1',
                    'order_id'         => '202412292160',
                    'payment_model'    => '3d',
                    'proc_return_code' => 1,
                    'ref_ret_num'      => '436419200463',
                    'status'           => 'approved',
                ],
                'is3DSuccess'         => true,
                'isSuccess'           => true,
            ],
        ];
    }

    public static function make3DPaymentDataForeignCurrencyProvider(): array
    {
        return [
            'success_foreign_currency' => [
                'order'               => [
                    'currency'    => 'EUR',
                    'amount'      => 1.01,
                    'installment' => 0,
                ],
                'txType'              => 'pay',
                'gatewayResponseData' => [
                    'TURKPOS_RETVAL_Islem_ID'          => '25B4E0BAAD1F3FC05D46F5B4',
                    'TURKPOS_RETVAL_Sonuc'             => '1',
                    'TURKPOS_RETVAL_Hash'              => 'LrFgOcE6S8HzNF4tzvtORAh3C20=',
                    'TURKPOS_RETVAL_Islem_GUID'        => '597b2fc9-df6d-40d7-861a-c4f5d0e94ed3',
                    'TURKPOS_RETVAL_SanalPOS_Islem_ID' => '6021842602',
                ],
                'expected'            => [
                    'amount' => 10.01,
                    'status' => 'approved',
                ],
                'isSuccess'           => true,
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

    public static function historyRequestDataProvider(): array
    {
        $txTime = new DateTimeImmutable();

        return [
            [
                'order' => [
                    'start_date' => $txTime->modify('-23 hour'),
                    'end_date'   => $txTime,
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
                'paymentModel'           => PosInterface::MODEL_3D_PAY_HOSTING,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => false,
                'create_without_card'    => false,
                'expectedExceptionClass' => LogicException::class,
                'expectedExceptionMsg'   => 'Mews\Pos\Gateway\ParamPos ödeme altyapıda [pay] işlem tipi [3d, 3d_pay, regular] ödeme model(ler) desteklemektedir. Sağlanan ödeme model: [3d_pay_hosting].',
            ],
            '3d_pay_without_card'       => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_PAY,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => false,
                'create_with_card'       => false,
                'expectedExceptionClass' => LogicException::class,
                'expectedExceptionMsg'   => 'Bu ödeme modeli için kart bilgileri zorunlu!',
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

    public static function threeDFormDataFailResponseProvider(): iterable
    {
        yield 'bad_request' => [
            'order'               => [
                'id'          => 'order222',
                'amount'      => 1000.25,
                'installment' => 0,
                'currency'    => 'TRY',
                'ip'          => '127.0.0.1',
                'success_url' => 'https://domain.com/success',
                'fail_url'    => 'https://domain.com/fail',
            ],
            'paymentModel'        => PosInterface::MODEL_3D_SECURE,
            'txType'              => PosInterface::TX_TYPE_PAY_AUTH,
            'requestData'         => [
                'soap:Body' => [
                    'TP_WMD_UCD' => [
                        '@xmlns'     => 'https://turkpos.com.tr/',
                        'Islem_ID'   => 'rand',
                        'Islem_Hash' => 'jsLYSB3lJ81leFgDLw4D8PbXURs=',
                    ],
                ],
            ],
            'decodedResponseData' => [
                "soap:Fault" => [
                    "faultcode"   => "soap:Client",
                    "faultstring" => "Unable to handle request without a valid action parameter. Please supply a valid soap action.",
                    "detail"      => "",
                ],
            ],
        ];

        yield 'order_already_exist' => [
            'order'               => [
                'id'          => 'order222',
                'amount'      => 1000.25,
                'installment' => 0,
                'currency'    => 'TRY',
                'ip'          => '127.0.0.1',
                'success_url' => 'https://domain.com/success',
                'fail_url'    => 'https://domain.com/fail',
            ],
            'paymentModel'        => PosInterface::MODEL_3D_SECURE,
            'txType'              => PosInterface::TX_TYPE_PAY_AUTH,
            'requestData'         => [
                'soap:Body' => [
                    'TP_WMD_UCD' => [
                        '@xmlns'     => 'https://turkpos.com.tr/',
                        'Islem_ID'   => 'rand',
                        'Islem_Hash' => 'jsLYSB3lJ81leFgDLw4D8PbXURs=',
                    ],
                ],
            ],
            'decodedResponseData' => [
                "TP_WMD_UCDResponse" => [
                    "TP_WMD_UCDResult" => [
                        "Islem_ID"        => "0",
                        "Sonuc"           => "-400",
                        "Sonuc_Str"       => "Siparis_ID ye ait başarılı işlem mevcuttur. 124 Yeni Siparis_ID üreterek tekrar işlem deneyiniz.34/9/7",
                        "Banka_Sonuc_Kod" => "0",
                    ],
                ],
            ],
        ];

        yield 'hash_error' => [
            'order'               => [
                'id'          => 'order222',
                'amount'      => 1000.25,
                'installment' => 0,
                'currency'    => 'TRY',
                'ip'          => '127.0.0.1',
                'success_url' => 'https://domain.com/success',
                'fail_url'    => 'https://domain.com/fail',
            ],
            'paymentModel'        => PosInterface::MODEL_3D_SECURE,
            'txType'              => PosInterface::TX_TYPE_PAY_AUTH,
            'requestData'         => [
                'soap:Body' => [
                    'TP_WMD_UCD' => [
                        '@xmlns'     => 'https://turkpos.com.tr/',
                        'Islem_ID'   => 'rand',
                        'Islem_Hash' => 'jsLYSB3lJ81leFgDLw4D8PbXURs=',
                    ],
                ],
            ],
            'decodedResponseData' => [
                'TP_WMD_UCDResponse' => [
                    'TP_WMD_UCDResult' => [
                        'Islem_ID'        => '0',
                        'Sonuc'           => '-102',
                        'Sonuc_Str'       => 'İşlem Hash geçersiz. Servise gelen Islem_Hash değeri:rvA0qAGEnAGZ8sfX4vk6AdSF/kI=2',
                        'Banka_Sonuc_Kod' => '0',
                    ],
                ],
            ],
        ];
    }

    public static function threeDFormDataProvider(): iterable
    {
        yield '3d_secure' => [
            'order'               => [
                'id'          => 'order222',
                'amount'      => 1000.25,
                'installment' => 0,
                'currency'    => 'TRY',
                'ip'          => '127.0.0.1',
                'success_url' => 'https://domain.com/success',
                'fail_url'    => 'https://domain.com/fail',
            ],
            'paymentModel'        => PosInterface::MODEL_3D_SECURE,
            'txType'              => PosInterface::TX_TYPE_PAY_AUTH,
            'isWithCard'          => true,
            'requestData'         => [
                'soap:Body' => [
                    'TP_WMD_UCD' => [
                        '@xmlns'     => 'https://turkpos.com.tr/',
                        'Islem_ID'   => 'rand',
                        'Islem_Hash' => 'jsLYSB3lJ81leFgDLw4D8PbXURs=',
                    ],
                ],
            ],
            'gateway_url'         => null,
            'decodedResponseData' => [
                'TP_WMD_UCDResponse' => [
                    'TP_WMD_UCDResult' => [
                        'Islem_ID'        => '6021840768',
                        'Islem_GUID'      => 'd68ac15c-17ca-4b7d-a046-10700291b249',
                        'UCD_HTML'        => 'html-document',
                        'UCD_MD'          => 'MosNOirpqxod2A0BdoPpFNf7E/hJX2pKvt8hunrQF2RSrggeWpNj9p+XDEgRdWfGdtGMHF5A7X/uVbJTb3cCN5LGcG2JsGd69bXc7yYBGGw/VMFTcHDObj+cVR6fP2k1s531ozcBEFN1hv+fwBH80YGHP2a6xbRujYzME2iPuPgCdr7wkoSWcZvwB5M73bFow3Jx3vqkwceaPUO6dat7m5Uv1dKmbp+py3yOR0nVaFGnKTmIB4JIAIuP24hCU2MJi+hvKDf7+IJIEl5cjotiUx/J0AINoeuIGrklDAZ8JRA7pxYXpZLwc3ZX60VpWvfS7sSOdayadMBOvltQSdRrPPhJztVNmkztgUe7s3rbpdVr4Fc/KzGtPa5PZLnpkXszhOO4g+pw0A3KuFsqTdFuuu25CqBTX/aG4yZ4VO7UKfG27cTgRaObKsU+YiwOhH/VgGODvd5qrR02gOY8f9Xqtw==',
                        'Sonuc'           => '1',
                        'Sonuc_Str'       => 'İşlem Başarılı',
                        'Banka_Sonuc_Kod' => '0',
                        'Siparis_ID'      => '20241229D2FF',
                    ],
                ],
            ],
            'formData'            => 'html-document',
        ];

        yield '3d_pay' => [
            'order'               => [
                'currency' => 'TRY',
            ],
            'paymentModel'        => PosInterface::MODEL_3D_PAY,
            'txType'              => PosInterface::TX_TYPE_PAY_AUTH,
            'isWithCard'          => true,
            'requestData'         => ['request-data'],
            'gateway_url'         => null,
            'decodedResponseData' => [
                'Pos_OdemeResponse' => [
                    'Pos_OdemeResult' => [
                        'Islem_ID'        => '6021847071',
                        'UCD_URL'         => 'https://test-pos.param.com.tr/3D_Secure/AkilliKart_3DPay_PFO.aspx?rURL=TURKPOS_3D_TRAN&SID=f2771b35-f5fd-434a-a1be-ba4eea554146',
                        'Sonuc'           => '1',
                        'Sonuc_Str'       => 'İşlem Başarılı',
                        'Banka_Sonuc_Kod' => '-1',
                        'Komisyon_Oran'   => '1.01',
                    ],
                ],
            ],
            'formData'            => [
                'gateway' => 'https://test-pos.param.com.tr/3D_Secure/AkilliKart_3DPay_PFO.aspx',
                'method'  => 'GET',
                'inputs'  => [
                    'rURL' => 'TURKPOS_3D_TRAN',
                    'SID'  => 'f2771b35-f5fd-434a-a1be-ba4eea554146',
                ],
            ],
        ];
    }

    public static function make3DPayPaymentDataProvider(): array
    {
        return [
            'auth_fail' => [
                'order'               => [
                    'currency'    => 'TRY',
                    'amount'      => 1.01,
                    'installment' => 0,
                ],
                'txType'              => 'pay',
                'gatewayResponseData' => [
                    'TURKPOS_RETVAL_Islem_ID'  => 'FF0591BD887935E481743533',
                    'TURKPOS_RETVAL_Sonuc'     => '-1',
                    'TURKPOS_RETVAL_Sonuc_Str' => '3D Dogrulamasi Basarisiz. [3D Hatasi: N-status/Challenge authentication via ACS: https://3ds-acs.test.modirum.com/mdpayacs/I-yRFxWEcBOCtERD/creq;token=340262061.17373]',

                ],
                'expected'            => [
                    'error_code'    => -1,
                    'error_message' => '3D Dogrulamasi Basarisiz. [3D Hatasi: N-status/Challenge authentication via ACS: https://3ds-acs.test.modirum.com/mdpayacs/I-yRFxWEcBOCtERD/creq;token=340262061.17373]',
                    'status'        => 'declined',
                ],
                'isSuccess'           => false,
            ],
            'success'   => [
                'order'               => [
                    'currency'    => 'TRY',
                    'amount'      => 1.01,
                    'installment' => 0,
                ],
                'txType'              => 'pay',
                'gatewayResponseData' => [
                    'TURKPOS_RETVAL_Islem_ID'  => '1944A39AD0AEA92E173D665B',
                    'TURKPOS_RETVAL_Sonuc'     => '1',
                    'TURKPOS_RETVAL_Sonuc_Str' => 'Odeme Islemi Basarili',
                    'TURKPOS_RETVAL_GUID'      => '0c13d406-873b-403b-9c09-a5766840d98c',

                ],
                'expected'            => [
                    'transaction_id'   => null,
                    'transaction_type' => 'pay',
                    'masked_number'    => '581877******2285',
                    'order_id'         => '20250119BACB',
                    'proc_return_code' => 1,
                    'status'           => 'approved',
                ],
                'isSuccess'           => true,
            ],
        ];
    }

    private function createGateway(array $config, ?AbstractPosAccount $account = null): PosInterface
    {
        return new ParamPos(
            $config,
            $account ?? $this->account,
            $this->requestValueMapperMock,
            $this->requestMapperMock,
            $this->responseMapperMock,
            $this->cryptMock,
            $this->eventDispatcherMock,
            $this->httpClientStrategyMock,
            $this->loggerMock
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

        $mockMethod = $this->httpClientMock->expects(self::once())
            ->method('request')
            ->with(
                $txType,
                $paymentModel,
                $this->callback(fn (array $requestData): bool => $requestData['test-update-request-data-with-event'] === true),
                $order,
                $apiUrl,
                $account
            );
        if (isset($decodedResponse['soap:Fault'])) {
            $mockMethod->willThrowException(new RuntimeException($decodedResponse['soap:Fault']['faultstring']));
        } else {
            $mockMethod->willReturn($decodedResponse);
        }


        $this->eventDispatcherMock->expects(self::once())
            ->method('dispatch')
            ->with($this->logicalAnd(
                $this->isInstanceOf(RequestDataPreparedEvent::class),
                $this->callback(function (RequestDataPreparedEvent $dispatchedEvent) use ($requestData, $txType, $order, $paymentModel, &$updatedRequestDataPreparedEvent): bool {
                    $updatedRequestDataPreparedEvent = $dispatchedEvent;

                    return $this->pos::class === $dispatchedEvent->getGatewayClass()
                        && $txType === $dispatchedEvent->getTxType()
                        && $requestData === $dispatchedEvent->getRequestData()
                        && $order === $dispatchedEvent->getOrder()
                        && $paymentModel === $dispatchedEvent->getPaymentModel();
                })
            ))
            ->willReturnCallback(function () use (&$updatedRequestDataPreparedEvent): ?RequestDataPreparedEvent {
                $updatedRequestData                                        = $updatedRequestDataPreparedEvent->getRequestData();
                $updatedRequestData['test-update-request-data-with-event'] = true;
                $updatedRequestDataPreparedEvent->setRequestData($updatedRequestData);

                return $updatedRequestDataPreparedEvent;
            });
    }
}
