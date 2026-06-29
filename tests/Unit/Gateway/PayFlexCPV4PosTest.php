<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Gateway;

use PHPUnit\Framework\Attributes\DataProvider;
use LogicException;
use Exception;
use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Client\HttpClientStrategyInterface;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\Request\Mapper\PayFlexCPV4PosRequestDataMapper;
use Mews\Pos\DataMapper\Request\Mapper\RequestDataMapperInterface;
use Mews\Pos\DataMapper\Request\ValueMapper\PayFlexCPV4PosRequestValueMapper;
use Mews\Pos\DataMapper\Response\Mapper\PayFlexCPV4PosResponseDataMapper;
use Mews\Pos\DataMapper\Response\Mapper\ResponseDataMapperInterface;
use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Model\Account\PayFlexPosAccount;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exception\UnsupportedFormFormatException;
use Mews\Pos\Exception\UnsupportedPaymentModelException;
use Mews\Pos\Exception\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Gateway\AbstractGateway;
use Mews\Pos\Gateway\PayFlexCPV4Pos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

#[CoversClass(PayFlexCPV4Pos::class)]
#[CoversClass(AbstractGateway::class)]
class PayFlexCPV4PosTest extends TestCase
{
    private PayFlexPosAccount $account;

    /** @var PayFlexCPV4Pos */
    private PosInterface $pos;

    private array $config;

    private CreditCardInterface $card;

    private array $order = [];

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

    private PayFlexCPV4PosRequestValueMapper $requestValueMapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'name'              => 'VakifBank-PayFlex-Common-Payment',
            'class'             => PayFlexCPV4Pos::class,
            'gateway_endpoints' => [
                'gateway_3d' => 'https://cptest.vakifbank.com.tr/CommonPayment/api/RegisterTransaction',
            ],
        ];

        $this->account = AccountFactory::createPayFlexPosAccount(
            'vakifbank-cp',
            '000000000111111',
            '3XTgER89as',
            'VP999999'
        );


        $this->order = [
            'id'          => 'order222',
            'amount'      => 100.00,
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'ip'          => '127.0.0.1',
        ];

        $this->requestValueMapper     = new PayFlexCPV4PosRequestValueMapper();
        $this->requestMapperMock      = $this->createMock(PayFlexCPV4PosRequestDataMapper::class);
        $this->responseMapperMock     = $this->createMock(PayFlexCPV4PosResponseDataMapper::class);
        $this->cryptMock              = $this->createMock(CryptInterface::class);
        $this->httpClientStrategyMock = $this->createMock(HttpClientStrategyInterface::class);
        $this->httpClientMock         = $this->createMock(HttpClientInterface::class);
        $this->loggerMock             = $this->createMock(LoggerInterface::class);
        $this->eventDispatcherMock    = $this->createMock(EventDispatcherInterface::class);

        $this->pos = new PayFlexCPV4Pos(
            $this->config,
            $this->account,
            $this->requestValueMapper,
            $this->requestMapperMock,
            $this->responseMapperMock,
            $this->cryptMock,
            $this->eventDispatcherMock,
            $this->httpClientStrategyMock,
            $this->loggerMock,
        );

        $this->card = CreditCardFactory::createForGateway($this->pos, '5555444433332222', '2021', '12', '122', 'ahmet', CreditCardInterface::CARD_TYPE_VISA);
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

    /**
     * @return void
     *
     * @throws Exception
     */
    public function testGet3DFormDataSuccess(): void
    {
        $enrollmentResponse = [
            'CommonPaymentUrl' => 'https://cptest.vakifbank.com.tr/CommonPayment/SecurePayment',
            'PaymentToken'     => 'c5e076e7bf234a339c40afc10166c06d',
            'ErrorCode'        => null,
            'ResponseMessage'  => null,
        ];
        $txType             = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel       = PosInterface::MODEL_3D_PAY;
        $requestData        = ['request-data'];
        $card               = $this->card;
        $order              = $this->order;

        $this->requestMapperMock->expects(self::once())
            ->method('create3DFormInitializeRequestData')
            ->with(
                $this->pos->getAccount(),
                $order,
                $paymentModel,
                $txType,
                $card
            )
            ->willReturn($requestData);

        $this->configureClientResponse(
            PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD,
            $requestData,
            $enrollmentResponse,
            $order,
            $paymentModel,
        );

        $this->requestMapperMock->expects(self::once())
            ->method('create3DFormData')
            ->with(
                null,
                [],
                null,
                null,
                null,
                null,
                $enrollmentResponse
            )
            ->willReturn(['3d-form-data']);

        $result = $this->pos->get3DFormData($order, $paymentModel, $txType, $card);

        $this->assertSame(['3d-form-data'], $result);
    }

    /**
     * @return void
     *
     * @throws Exception
     */
    public function testGet3DFormDataEnrollmentFail(): void
    {
        $txType       = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel = PosInterface::MODEL_3D_PAY;
        $card         = $this->card;
        $order        = $this->order;
        $requestData  = ['request-data'];

        $this->requestMapperMock->expects(self::once())
            ->method('create3DFormInitializeRequestData')
            ->with(
                $this->pos->getAccount(),
                $order,
                $paymentModel,
                $txType,
                $card
            )
            ->willReturn($requestData);

        $enrollmentResponse = [
            'CommonPaymentUrl' => null,
            'PaymentToken'     => null,
            'ErrorCode'        => '5007',
            'ResponseMessage'  => 'Güvenlik Numarası Hatalı',
        ];
        $this->configureClientResponse(
            PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD,
            $requestData,
            $enrollmentResponse,
            $order,
            $paymentModel,
        );

        $this->requestMapperMock->expects(self::never())
            ->method('create3DFormData');

        $this->expectException(Exception::class);

        $this->pos->get3DFormData($order, $paymentModel, $txType, $card);
    }

    #[DataProvider('threeDFormDataBadInputsProvider')]
    public function testGet3DFormDataWithBadInputs(
        array   $order,
        string  $paymentModel,
        string  $txType,
        bool    $isWithCard,
        bool    $createWithoutCard,
        string  $expectedExceptionClass,
        ?string $formFormat = null
    ): void {
        $card = $isWithCard ? $this->card : null;

        $this->expectException($expectedExceptionClass);

        $this->pos->get3DFormData($order, $paymentModel, $txType, $card, $createWithoutCard, $formFormat);
    }

    public function testMake3DPayment(): void
    {
        $txType = PosInterface::TX_TYPE_PAY_AUTH;

        $this->expectException(UnsupportedPaymentModelException::class);
        $this->pos->payment(PosInterface::MODEL_3D_SECURE, [], $txType, null, ['abc']);
    }

    #[DataProvider('make3DPayPaymentDataProvider')]
    public function testMake3DPayPayment(
        array  $order,
        string $txType,
        array  $gatewayResponseData,
        array  $paymentResponse,
        array  $expectedResponse,
        bool   $is3DSuccess,
        bool   $isSuccess
    ): void {
        if ($is3DSuccess) {
            $this->cryptMock->expects(self::never())
                ->method('check3DHash');
        }

        $this->responseMapperMock->expects(self::never())
            ->method('extractMdStatus');

        $this->responseMapperMock->expects(self::never())
            ->method('is3dAuthSuccess');

        $create3DPaymentStatusRequestData = [
            'create3DPaymentStatusRequestData',
        ];
        if ($is3DSuccess) {
            $this->requestMapperMock->expects(self::once())
                ->method('create3DPaymentStatusRequestData')
                ->with($this->account, $gatewayResponseData)
                ->willReturn($create3DPaymentStatusRequestData);

            $this->configureClientResponse(
                PosInterface::TX_TYPE_INTERNAL_3D_PAYMENT_STATUS,
                $create3DPaymentStatusRequestData,
                $paymentResponse,
                $order,
                PosInterface::MODEL_NON_SECURE
            );

            $this->responseMapperMock->expects(self::once())
                ->method('map3DPayResponseData')
                ->with($gatewayResponseData, $txType, $order)
                ->willReturn($expectedResponse);
        } else {
            $this->responseMapperMock->expects(self::once())
                ->method('map3DPayResponseData')
                ->with($gatewayResponseData, $txType, $order)
                ->willReturn($expectedResponse);
            $this->requestMapperMock->expects(self::never())
                ->method('create3DPaymentRequestData');
            $this->eventDispatcherMock->expects(self::never())
                ->method('dispatch');
        }

        $result = $this->pos->payment(PosInterface::MODEL_3D_PAY, $order, $txType, null, $gatewayResponseData);

        $this->assertSame($expectedResponse, $result);
        $this->assertSame($isSuccess, $this->pos->isSuccess());
    }

    #[DataProvider('make3DPayPaymentDataProvider')]
    public function testMake3DHostPayment(
        array  $order,
        string $txType,
        array  $gatewayResponseData,
        array  $paymentResponse,
        array  $expectedResponse,
        bool   $is3DSuccess,
        bool   $isSuccess
    ): void {
        if ($is3DSuccess) {
            $this->cryptMock->expects(self::never())
                ->method('check3DHash');
        }

        $this->responseMapperMock->expects(self::never())
            ->method('extractMdStatus');

        $this->responseMapperMock->expects(self::never())
            ->method('is3dAuthSuccess');

        $create3DPaymentStatusRequestData = [
            'create3DPaymentStatusRequestData',
        ];
        if ($is3DSuccess) {
            $this->requestMapperMock->expects(self::once())
                ->method('create3DPaymentStatusRequestData')
                ->with($this->account, $gatewayResponseData)
                ->willReturn($create3DPaymentStatusRequestData);

            $this->configureClientResponse(
                PosInterface::TX_TYPE_INTERNAL_3D_PAYMENT_STATUS,
                $create3DPaymentStatusRequestData,
                $paymentResponse,
                $order,
                PosInterface::MODEL_NON_SECURE
            );

            $this->responseMapperMock->expects(self::once())
                ->method('map3DHostResponseData')
                ->with($gatewayResponseData, $txType, $order)
                ->willReturn($expectedResponse);
        } else {
            $this->responseMapperMock->expects(self::once())
                ->method('map3DHostResponseData')
                ->with($gatewayResponseData, $txType, $order)
                ->willReturn($expectedResponse);
            $this->requestMapperMock->expects(self::never())
                ->method('create3DPaymentRequestData');
            $this->eventDispatcherMock->expects(self::never())
                ->method('dispatch');
        }

        $result = $this->pos->payment(PosInterface::MODEL_3D_HOST, $order, $txType, null, $gatewayResponseData);

        $this->assertSame($expectedResponse, $result);
        $this->assertSame($isSuccess, $this->pos->isSuccess());
    }

    public function testMakeRegularPayment(): void
    {
        $this->expectException(UnsupportedPaymentModelException::class);
        $this->pos->makeRegularPayment($this->order, $this->card, PosInterface::TX_TYPE_PAY_AUTH);
    }

    public function testMakeRegularPostAuthPayment(): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->pos->payment(PosInterface::MODEL_NON_SECURE, [], PosInterface::TX_TYPE_PAY_POST_AUTH);
    }

    public function testStatusRequest(): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->pos->status([]);
    }

    public function testCancelRequest(): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->pos->cancel([]);
    }

    public function testRefundRequest(): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->pos->refund([]);
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

    public static function customQueryRequestDataProvider(): array
    {
        return [
            [
                'requestData' => [
                    'id' => '2020110828BC',
                ],
                'api_url'     => 'https://cptest.vakifbank.com.tr/CommonPayment/SecurePayment/xxxx',
            ],
            [
                'requestData' => [
                    'id' => '2020110828BC',
                ],
                'api_url'     => null,
            ],
        ];
    }

    public static function make3DPayPaymentDataProvider(): array
    {
        return [
            'auth_fail' => [
                'order'           => [],
                'txType'          => PosInterface::TX_TYPE_STATUS,
                'request'         => [
                    'Rc'            => '2053',
                    'Message'       => 'VeRes status is E Message : Directory server communication error',
                    'PaymentToken'  => '68244b7e3dfd4b3ebea1afbe0185b9ed',
                    'TransactionId' => '0cb6a57715144178a014afbe0185b9ed',
                    'MaskedPan'     => '49384601****4205',
                ],
                'paymentResponse' => [],
                'expected'        => [
                    'order_id'         => null,
                    'transaction_id'   => '0cb6a57715144178a014afbe0185b9ed',
                    'transaction_type' => 'pay',
                    'status'           => 'declined',
                    'error_code'       => '2053',
                    'error_message'    => 'VeRes status is E Message : Directory server communication error',
                ],
                'is3DSuccess'     => false,
                'isSuccess'       => false,
            ],
            'success'   => [
                'order'           => [],
                'txType'          => PosInterface::TX_TYPE_STATUS,
                'request'         => [
                    'SuccessUrl'      => 'http://localhost/vakifbank-cp/3d-host/response.php',
                    'FailUrl'         => 'http://localhost/vakifbank-cp/3d-host/response.php',
                    'RequestLanguage' => 'tr-TR',
                    'Extract'         => null,
                    'CardHoldersName' => 'Jo* Do*',
                ],
                'paymentResponse' => [
                    'SuccessUrl'      => 'http://localhost/vakifbank-cp/3d-host/response.php',
                    'FailUrl'         => 'http://localhost/vakifbank-cp/3d-host/response.php',
                    'RequestLanguage' => 'tr-TR',
                    'Extract'         => null,
                    'CardHoldersName' => 'Jo* Do*',
                ],
                'expected'        => [
                    'order_id'         => '2023030913ED',
                    'transaction_id'   => '3ee068d5b5a747ada65dafc0016d5887',
                    'transaction_type' => 'pay',
                    'status'           => 'approved',
                ],
                'is3DSuccess'     => true,
                'isSuccess'       => true,
            ],
        ];
    }

    public static function threeDFormDataBadInputsProvider(): array
    {
        return [
            '3d_pay_without_card'       => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_PAY,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => false,
                'create_without_card'    => false,
                'expectedExceptionClass' => LogicException::class,
            ],
            'unsupported_payment_model' => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_SECURE,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => false,
                'create_without_card'    => true,
                'expectedExceptionClass' => LogicException::class,
            ],
            'unsupported_form_format'   => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_PAY,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => true,
                'create_without_card'    => false,
                'expectedExceptionClass' => UnsupportedFormFormatException::class,
                'formFormat'             => PosInterface::FORM_FORMAT_HTML,
            ],
        ];
    }

    private function configureClientResponse(
        string              $txType,
        array               $requestData,
        array               $decodedResponse,
        array               $order,
        string              $paymentModel,
        ?string             $apiUrl = null,
        ?AbstractPosAccount $account = null,
        ?string             $clientTxType = null
    ): void {
        $updatedRequestDataPreparedEvent = null;

        $clientTxType ??= $txType;

        $this->httpClientStrategyMock->expects(self::once())
            ->method('getClient')
            ->with($clientTxType, $paymentModel)
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
