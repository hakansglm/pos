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
use Mews\Pos\DataMapper\Request\ValueMapper\PosNetV1PosRequestValueMapper;
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
use Mews\Pos\Gateway\PosNetV1Pos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

#[CoversClass(PosNetV1Pos::class)]
#[CoversClass(AbstractGateway::class)]
class PosNetV1PosTest extends TestCase
{
    private PosNetPosAccount $account;

    private array $config;

    private CreditCardInterface $card;

    /** @var PosNetV1Pos */
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

    private PosNetV1PosRequestValueMapper $requestValueMapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'name'              => 'Albaraka',
            'class'             => PosNetV1Pos::class,
            'gateway_endpoints' => [
                'gateway_3d' => 'https://epostest.albarakaturk.com.tr/ALBSecurePaymentUI/SecureProcess/SecureVerification.aspx',
            ],
        ];

        $this->account = AccountFactory::createPosNetPosAccount(
            'albaraka',
            '6700950031',
            '67540050',
            '1010028724242434',
            PosInterface::MODEL_3D_SECURE,
            '10,10,10,10,10,10,10,10'
        );

        $this->requestValueMapper     = new PosNetV1PosRequestValueMapper();
        $this->requestMapperMock      = $this->createMock(RequestDataMapperInterface::class);
        $this->responseMapperMock     = $this->createMock(ResponseDataMapperInterface::class);
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
        $this->assertSame($this->config, $this->pos->getConfig());
        $this->assertSame($this->account, $this->pos->getAccount());
        $this->assertFalse($this->pos->isTestMode());
        $this->assertSame($this->cryptMock, $this->pos->getCrypt());
    }

    #[TestWith([true])]
    public function testGet3DFormData(
        bool $isWithCard
    ): void {
        $card         = $isWithCard ? $this->card : null;
        $paymentModel = $isWithCard ? PosInterface::MODEL_3D_SECURE : PosInterface::MODEL_3D_HOST;
        $order        = ['id' => '124'];
        $txType       = PosInterface::TX_TYPE_PAY_AUTH;

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

    public function testMake3DPaymentHashMismatchException(): void
    {
        $txType              = PosInterface::TX_TYPE_PAY_AUTH;
        $gatewayResponseData = [
            'SecureTransactionId' => '1010028947569644',
            'Mac'                 => 'r21kMm4nMqvJakjq47Jl+3fk2xrFPrDoTJFQGxkgkfk=',
            'MacParams'           => 'ECI:CAVV:MdStatus:MdErrorMessage:MD:SecureTransactionId',
            'CurrencyCode'        => '949',
            'InstalmentCode'      => '0',
            'VtfCode'             => '',
            'PointAmount'         => '',
        ];

        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->with($this->account, $gatewayResponseData)
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
            $this->account
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

    public static function customQueryRequestDataProvider(): array
    {
        return [
            [
                'requestData' => [
                    'id' => '2020110828BC',
                ],
                'api_url'     => 'https://epostest.albarakaturk.com.tr/ALBMerchantService/MerchantJSONAPI.svc/xxx',
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
                'order'           => [
                    'id' => '20230622A1C9',
                ],
                'txType'          => PosInterface::TX_TYPE_PAY_AUTH,
                'request'         => [
                    'CCPrefix'  => '450634',
                    'TranType'  => 'Sale',
                    'Mac'       => 'ltpqSazdMf67AjmWF0WQ5pOU78F+kjrfkyz7ex+ZvNg=',
                    'MacParams' => 'ECI:CAVV:MdStatus:MdErrorMessage:MD:SecureTransactionId',
                ],
                'paymentResponse' => [],
                'expected'        => [
                    'masked_number'    => '450634',
                    'md_status'        => '0',
                    'md_error_message' => 'Not authenticated',
                    'order_id'         => '20230622A1C9',
                    'remote_order_id'  => '0000000020230622A1C9',
                    'status'           => 'declined',
                ],
                'is3DSuccess'     => false,
                'isSuccess'       => false,
            ],
            '3d_auth_success_payment_fail' => [
                'order'           => [
                    'id' => '20230622A1C9',
                ],
                'txType'          => PosInterface::TX_TYPE_PAY_AUTH,
                'request'         => [
                    'CCPrefix'       => '450634',
                    'TranType'       => 'Sale',
                    'Amount'         => '101',
                    'MdErrorMessage' => 'Y-status/Challenge authentication via ACS: https://certemvacs.bkm.com.tr/acs/creq',
                    'MdStatus'       => '1',
                    'Mac'            => 'aw2jry3dZbmDMvIfuyx3sixxY50ysnRhaR3kOXHLJRw=',
                    'MacParams'      => 'ECI:CAVV:MdStatus:MdErrorMessage:MD:SecureTransactionId',
                ],
                'paymentResponse' => [
                    'ServiceResponseData' => [
                        'ResponseCode'        => '0148',
                        'ResponseDescription' => 'INVALID MID TID IP. Hatalı IP:92.38.180.61',
                    ],
                ],
                'expected'        => [
                    'error_code'       => '0148',
                    'error_message'    => 'INVALID MID TID IP. Hatalı IP:92.38.180.61',
                    'order_id'         => '202306226A90',
                    'remote_order_id'  => '00000000202306226A90',
                    'proc_return_code' => '0148',
                    'status'           => 'declined',
                ],
                'is3DSuccess'     => true,
                'isSuccess'       => false,
            ],
            'success'                      => [
                'order'           => [
                    'id' => '20230622A1C9',
                ],
                'txType'          => PosInterface::TX_TYPE_PAY_AUTH,
                'request'         => [
                    'CCPrefix'   => '540061',
                    'TranType'   => 'Sale',
                    'Amount'     => '175',
                    'OrderId'    => 'ALA_0000080603153823',
                    'MerchantId' => '6700950031',
                    'Mac'        => 'r21kMm4nMqvJakjq47Jl+3fk2xrFPrDoTJFQGxkgkfk=',
                    'MacParams'  => 'ECI:CAVV:MdStatus:MdErrorMessage:MD:SecureTransactionId',
                ],
                'paymentResponse' => [
                    'ServiceResponseData' => [
                        'ResponseCode'        => '00',
                        'ResponseDescription' => 'Onaylandı',
                    ],
                    'AuthCode'            => '449324',
                    'ReferenceCode'       => '159044932490000231',
                    'PointDataList'       => [
                        [
                            'PointType'     => 'EarnedPoint',
                            'Point'         => 1000,
                            'PointTLAmount' => 500,
                        ],
                    ],

                ],
                'expected'        => [
                    'proc_return_code' => '00',
                    'status'           => 'approved',
                ],
                'is3DSuccess'     => true,
                'isSuccess'       => true,
            ],
        ];
    }

    public static function make3DPaymentWithoutHashCheckDataProvider(): array
    {
        return [
            '3d_auth_success_payment_fail' => [
                'order'           => [
                    'id' => '20230622A1C9',
                ],
                'txType'          => PosInterface::TX_TYPE_PAY_AUTH,
                'request'         => [
                    'MdErrorMessage' => 'Y-status/Challenge authentication via ACS: https://certemvacs.bkm.com.tr/acs/creq',
                    'MdStatus'       => '1',
                    'Mac'            => 'aw2jry3dZbmDMvIfuyx3sixxY50ysnRhaR3kOXHLJRw=',
                    'MacParams'      => 'ECI:CAVV:MdStatus:MdErrorMessage:MD:SecureTransactionId',
                ],
                'paymentResponse' => [
                    'ServiceResponseData' => [
                        'ResponseCode'        => '0148',
                        'ResponseDescription' => 'INVALID MID TID IP. Hatalı IP:92.38.180.61',
                    ],
                ],
                'expected'        => [
                    'error_code'       => '0148',
                    'error_message'    => 'INVALID MID TID IP. Hatalı IP:92.38.180.61',
                    'order_id'         => '202306226A90',
                    'remote_order_id'  => '00000000202306226A90',
                    'proc_return_code' => '0148',
                    'status'           => 'declined',
                ],
                'is3DSuccess'     => true,
                'isSuccess'       => false,
            ],
            'success'                      => [
                'order'           => [
                    'id' => '20230622A1C9',
                ],
                'txType'          => PosInterface::TX_TYPE_PAY_AUTH,
                'request'         => [
                    'MdErrorMessage'      => 'Authenticated',
                    'MdStatus'            => '1',
                    'SecureTransactionId' => '1010028947569644',
                    'Mac'                 => 'r21kMm4nMqvJakjq47Jl+3fk2xrFPrDoTJFQGxkgkfk=',
                    'MacParams'           => 'ECI:CAVV:MdStatus:MdErrorMessage:MD:SecureTransactionId',
                ],
                'paymentResponse' => [
                    'ServiceResponseData' => [
                        'ResponseCode'        => '00',
                        'ResponseDescription' => 'Onaylandı',
                    ],
                    'AuthCode'            => '449324',
                    'ReferenceCode'       => '159044932490000231',
                    'PointDataList'       => [
                        [
                            'PointType'     => 'EarnedPoint',
                            'Point'         => 1000,
                            'PointTLAmount' => 500,
                        ],
                    ],
                ],
                'expected'        => [
                    'order_id'         => '80603153823',
                    'remote_order_id'  => 'ALA_0000080603153823',
                    'proc_return_code' => '00',
                    'status'           => 'approved',

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
                'expectedExceptionMsg'   => 'Mews\Pos\Gateway\PosNetV1Pos ödeme altyapıda [pay] işlem tipi [3d, regular] ödeme model(ler) desteklemektedir. Sağlanan ödeme model: [3d_pay].',
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
        return new PosNetV1Pos(
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
