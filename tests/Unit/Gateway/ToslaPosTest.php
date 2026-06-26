<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Gateway;

use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use LogicException;
use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Client\HttpClientStrategyInterface;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\Request\Mapper\ToslaPosRequestDataMapper;
use Mews\Pos\DataMapper\Request\ValueMapper\ToslaPosRequestValueMapper;
use Mews\Pos\DataMapper\Response\Mapper\ResponseDataMapperInterface;
use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Model\Account\ToslaPosAccount;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exception\HashMismatchException;
use Mews\Pos\Exception\UnsupportedFormFormatException;
use Mews\Pos\Exception\UnsupportedPaymentModelException;
use Mews\Pos\Exception\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Gateway\AbstractGateway;
use Mews\Pos\Gateway\ToslaPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

#[CoversClass(ToslaPos::class)]
#[CoversClass(AbstractGateway::class)]
class ToslaPosTest extends TestCase
{
    public array $config;

    public CreditCardInterface $card;

    private ToslaPosAccount $account;

    /** @var ToslaPos */
    private PosInterface $pos;

    /** @var ToslaPosRequestDataMapper & MockObject */
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

    private ToslaPosRequestValueMapper $requestValueMapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'name'              => 'AKBANK T.A.S.',
            'class'             => ToslaPos::class,
            'gateway_endpoints' => [
                'gateway_3d'      => 'https://ent.akodepos.com/api/Payment/ProcessCardForm',
                'gateway_3d_host' => 'https://ent.akodepos.com/api/Payment/threeDSecure',
            ],
        ];

        $this->account = AccountFactory::createToslaPosAccount(
            'tosla',
            '1000000494',
            'POS_ENT_Test_001',
            'POS_ENT_Test_001!*!*',
        );

        $this->requestValueMapper     = new ToslaPosRequestValueMapper();
        $this->requestMapperMock      = $this->createMock(ToslaPosRequestDataMapper::class);
        $this->responseMapperMock     = $this->createMock(ResponseDataMapperInterface::class);
        $this->cryptMock              = $this->createMock(CryptInterface::class);
        $this->httpClientStrategyMock = $this->createMock(HttpClientStrategyInterface::class);
        $this->httpClientMock         = $this->createMock(HttpClientInterface::class);
        $this->loggerMock             = $this->createMock(LoggerInterface::class);
        $this->eventDispatcherMock    = $this->createMock(EventDispatcherInterface::class);

        $this->pos = $this->createGateway($this->config);

        $this->card = CreditCardFactory::createForGateway($this->pos, '5555444433332222', '21', '12', '122', 'ahmet', CreditCardInterface::CARD_TYPE_VISA);
    }

    public function testInit(): void
    {
        $this->assertSame($this->config, $this->pos->getConfig());
        $this->assertSame($this->account, $this->pos->getAccount());
        $this->assertFalse($this->pos->isTestMode());
        $this->assertSame($this->cryptMock, $this->pos->getCrypt());
    }

    public function testGet3DGatewayURL(): void
    {
        $actual = $this->pos->get3DGatewayURL();

        $this->assertSame(
            $this->config['gateway_endpoints']['gateway_3d'],
            $actual
        );
    }

    public function testGet3DHostGatewayURL(): void
    {
        $sessionId = 'A2A6E942BD2AE4A68BC42FE99D1BC917D67AFF54AB2BA44EBA675843744187708';
        $actual    = $this->pos->get3DGatewayURL(PosInterface::MODEL_3D_HOST, $sessionId);

        $this->assertSame(
            $this->config['gateway_endpoints']['gateway_3d_host'].'/'.$sessionId,
            $actual
        );
    }

    #[DataProvider('make3DPayPaymentDataProvider')]
    public function testMake3DPayPayment(
        array  $order,
        string $txType,
        array  $gatewayResponseData,
        array  $expectedResponse,
        bool   $is3DSuccess,
        bool   $isSuccess
    ): void {
        if ($is3DSuccess) {
            $this->cryptMock->expects(self::once())
                ->method('check3DHash')
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

        $this->responseMapperMock->expects(self::once())
            ->method('map3DPayResponseData')
            ->willReturn($expectedResponse);

        $result = $this->pos->payment(PosInterface::MODEL_3D_PAY, $order, $txType, null, $gatewayResponseData);

        $this->assertSame($expectedResponse, $result);
        $this->assertSame($isSuccess, $this->pos->isSuccess());
    }

    #[DataProvider('make3DPayPaymentWithoutHashCheckDataProvider')]
    public function testMake3DPayPaymentWithoutHashCheck(
        array  $order,
        string $txType,
        array  $gatewayResponseData,
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
            'ClientId'       => '1000000494',
            'OrderId'        => '202312034E91',
            'MdStatus'       => '1',
            'HashParameters' => 'ClientId,ApiUser,OrderId,MdStatus,BankResponseCode,BankResponseMessage,RequestStatus',
            'Hash'           => 'CgibjWkLpfx+Cz6cVlbH1ViSW74ouKACVOW0Vrt2SfqPMt+V3hfIx/4LnOgcInFhPci/qcnIMgdN0RptHSmFOg==',
        ];
        $txType              = PosInterface::TX_TYPE_PAY_AUTH;

        $this->responseMapperMock->expects(self::once())
            ->method('is3dAuthSuccess')
            ->willReturn(true);

        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->with($this->account, $gatewayResponseData)
            ->willReturn(false);

        $this->responseMapperMock->expects(self::never())
            ->method('map3DPayResponseData');

        $this->expectException(HashMismatchException::class);
        $this->pos->payment(PosInterface::MODEL_3D_PAY, [], $txType, null, $gatewayResponseData);
    }

    #[DataProvider('make3DPayPaymentDataProvider')]
    public function testMake3DHostPayment(
        array  $order,
        string $txType,
        array  $gatewayResponseData,
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

        $this->responseMapperMock->expects(self::once())
            ->method('map3DHostResponseData')
            ->willReturn($expectedResponse);

        $result = $this->pos->payment(PosInterface::MODEL_3D_HOST, $order, $txType, null, $gatewayResponseData);

        $this->assertSame($expectedResponse, $result);
        $this->assertSame($isSuccess, $this->pos->isSuccess());
    }

    #[DataProvider('make3DPayPaymentWithoutHashCheckDataProvider')]
    public function testMake3DHostPaymentWithoutHashCheck(
        array  $order,
        string $txType,
        array  $gatewayResponseData,
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

        $this->responseMapperMock->expects(self::once())
            ->method('map3DHostResponseData')
            ->willReturn($expectedResponse);

        $result = $pos->payment(PosInterface::MODEL_3D_HOST, $order, $txType, null, $gatewayResponseData);

        $this->assertSame($expectedResponse, $result);
        $this->assertSame($isSuccess, $pos->isSuccess());
    }

    public function testMake3DHostPaymentHashMismatchException(): void
    {
        $gatewayResponseData = [
            'ClientId'       => '1000000494',
            'OrderId'        => '202312034E91',
            'MdStatus'       => '1',
            'HashParameters' => 'ClientId,ApiUser,OrderId,MdStatus,BankResponseCode,BankResponseMessage,RequestStatus',
            'Hash'           => 'CgibjWkLpfx+Cz6cVlbH1ViSW74ouKACVOW0Vrt2SfqPMt+V3hfIx/4LnOgcInFhPci/qcnIMgdN0RptHSmFOg==',
        ];
        $txType              = PosInterface::TX_TYPE_PAY_AUTH;

        $this->responseMapperMock->expects(self::once())
            ->method('is3dAuthSuccess')
            ->willReturn(true);

        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->with($this->account, $gatewayResponseData)
            ->willReturn(false);

        $this->responseMapperMock->expects(self::never())
            ->method('map3DHostResponseData');

        $this->expectException(HashMismatchException::class);
        $this->pos->payment(PosInterface::MODEL_3D_HOST, [], $txType, null, $gatewayResponseData);
    }

    #[DataProvider('make3DPayPaymentDataProvider')]
    public function testMake3DPayment(array $order, string $txType, array $gatewayResponseData): void
    {
        $this->expectException(UnsupportedPaymentModelException::class);
        $this->pos->payment(PosInterface::MODEL_3D_SECURE, $order, $txType, $this->card, $gatewayResponseData);
    }

    #[DataProvider('threeDFormDataProvider')]
    public function testGet3DFormData(
        array  $order,
        string $paymentModel,
        string $txType,
        bool   $isWithCard,
        array  $requestData,
        array  $decodedResponseData,
        array  $formData,
        string $gatewayUrl
    ): void {
        $card = $isWithCard ? $this->card : null;
        $this->requestMapperMock->expects(self::once())
            ->method('create3DFormInitializeRequestData')
            ->with($this->pos->getAccount(), $order)
            ->willReturn($requestData);

        $this->configureClientResponse(
            $txType,
            $requestData,
            $decodedResponseData,
            $order,
            $paymentModel,
        );

        $this->requestMapperMock->expects(self::once())
            ->method('create3DFormData')
            ->with(
                $this->pos->getAccount(),
                $decodedResponseData,
                $paymentModel,
                $txType,
                $gatewayUrl,
                $card
            )
            ->willReturn($formData);

        $actual = $this->pos->get3DFormData($order, $paymentModel, $txType, $card, !$isWithCard);

        $this->assertSame($actual, $formData);
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

    #[DataProvider('registerFailResponseDataProvider')]
    public function testGet3DFormDataRegisterPaymentFail(array $response): void
    {
        $txType      = PosInterface::TX_TYPE_PAY_AUTH;
        $requestData = ['request-data'];
        $order       = ['order'];
        $this->requestMapperMock->expects(self::once())
            ->method('create3DFormInitializeRequestData')
            ->with($this->pos->getAccount(), $order)
            ->willReturn($requestData);

        $this->configureClientResponse(
            $txType,
            $requestData,
            $response,
            $order,
            PosInterface::MODEL_3D_PAY
        );

        $this->requestMapperMock->expects(self::never())
            ->method('create3DFormData');

        $this->expectException(RuntimeException::class);
        $this->pos->get3DFormData($order, PosInterface::MODEL_3D_PAY, $txType, $this->card);
    }

    #[DataProvider('statusDataProvider')]
    public function testStatus(
        array  $order,
        array  $requestData,
        array  $decodedResponse,
        array  $mappedResponse,
        bool   $isSuccess
    ): void {
        $account = $this->pos->getAccount();
        $txType  = PosInterface::TX_TYPE_STATUS;

        $this->requestMapperMock->expects(self::once())
            ->method('createStatusRequestData')
            ->with($account, $order)
            ->willReturn($requestData);

        $this->responseMapperMock->expects(self::once())
            ->method('mapStatusResponse')
            ->with($decodedResponse)
            ->willReturn($mappedResponse);

        $this->configureClientResponse(
            $txType,
            $requestData,
            $decodedResponse,
            $order,
            PosInterface::MODEL_NON_SECURE,
            null,
            $account
        );

        $result = $this->pos->status($order);

        $this->assertSame($isSuccess, $this->pos->isSuccess());
        $this->assertSame($result, $mappedResponse);
    }


    #[DataProvider('cancelDataProvider')]
    public function testCancel(
        array  $order,
        array  $requestData,
        array  $decodedResponse,
        array  $mappedResponse,
        bool   $isSuccess
    ): void {
        $this->requestMapperMock->expects(self::once())
            ->method('createCancelRequestData')
            ->with($this->pos->getAccount(), $order)
            ->willReturn($requestData);

        $this->responseMapperMock->expects(self::once())
            ->method('mapCancelResponse')
            ->with($decodedResponse)
            ->willReturn($mappedResponse);

        $this->configureClientResponse(
            PosInterface::TX_TYPE_CANCEL,
            $requestData,
            $decodedResponse,
            $order,
            PosInterface::MODEL_NON_SECURE,
            null,
            $this->account
        );

        $result = $this->pos->cancel($order);

        $this->assertSame($isSuccess, $this->pos->isSuccess());
        $this->assertSame($result, $mappedResponse);
    }

    #[DataProvider('refundDataProvider')]
    public function testRefund(
        array  $order,
        string $txType,
        array  $requestData,
        array  $decodedResponse,
        array  $mappedResponse,
        bool   $isSuccess
    ): void {
        $this->requestMapperMock->expects(self::once())
            ->method('createRefundRequestData')
            ->with($this->pos->getAccount(), $order, $txType)
            ->willReturn($requestData);

        $this->responseMapperMock->expects(self::once())
            ->method('mapRefundResponse')
            ->with($decodedResponse)
            ->willReturn($mappedResponse);

        $this->configureClientResponse(
            PosInterface::TX_TYPE_REFUND,
            $requestData,
            $decodedResponse,
            $order,
            PosInterface::MODEL_NON_SECURE,
            null,
            $this->account
        );

        $result = $this->pos->refund($order);

        $this->assertSame($isSuccess, $this->pos->isSuccess());
        $this->assertSame($result, $mappedResponse);
    }

    public function testHistoryRequest(): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->pos->history([]);
    }

    #[DataProvider('orderHistoryDataProvider')]
    public function testOrderHistory(
        array  $order,
        array  $requestData,
        array  $decodedResponse,
        array  $mappedResponse,
        bool   $isSuccess
    ): void {
        $account = $this->pos->getAccount();
        $txType  = PosInterface::TX_TYPE_ORDER_HISTORY;

        $this->requestMapperMock->expects(self::once())
            ->method('createOrderHistoryRequestData')
            ->with($account, $order)
            ->willReturn($requestData);

        $this->responseMapperMock->expects(self::once())
            ->method('mapOrderHistoryResponse')
            ->with($decodedResponse)
            ->willReturn($mappedResponse);

        $this->configureClientResponse(
            $txType,
            $requestData,
            $decodedResponse,
            $order,
            PosInterface::MODEL_NON_SECURE,
            null,
            $this->account
        );

        $result = $this->pos->orderHistory($order);

        $this->assertSame($isSuccess, $this->pos->isSuccess());
        $this->assertSame($result, $mappedResponse);
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
            $account
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
                'api_url'     => 'https://prepentegrasyon.tosla.com/api/Payment/GetCommissionAndInstallmentInfo',
            ],
            [
                'requestData' => [
                    'id' => '2020110828BC',
                ],
                'api_url'     => null,
            ],
        ];
    }

    public static function statusDataProvider(): iterable
    {
        $decodedResponse = ['BankResponseCode' => '00', 'OrderId' => '202401199AAA'];
        yield [
            'order'               => ['id' => 'id-12'],
            'requestData'         => ['clientId' => '1000000494', 'orderId' => 'id-12'],
            'decodedResponseData' => $decodedResponse,
            'mappedResponse'      => ['status' => 'approved'],
            'isSuccess'           => true,
        ];
    }

    public static function cancelDataProvider(): iterable
    {
        $decodedResponse = ['BankResponseCode' => '00', 'OrderId' => '202312058278'];
        yield [
            'order'               => ['id' => 'id-12'],
            'requestData'         => ['clientId' => '1000000494', 'orderId' => 'id-12'],
            'decodedResponseData' => $decodedResponse,
            'mappedResponse'      => ['status' => 'approved'],
            'isSuccess'           => true,
        ];
    }

    public static function refundDataProvider(): iterable
    {
        $decodedResponse = ['BankResponseCode' => '00', 'OrderId' => '202312051B4E'];
        yield [
            'order'               => ['id' => 'id-12', 'amount' => 1.02],
            'txType'              => PosInterface::TX_TYPE_REFUND,
            'requestData'         => ['clientId' => '1000000494', 'orderId' => 'id-12', 'amount' => 102],
            'decodedResponseData' => $decodedResponse,
            'mappedResponse'      => ['status' => 'approved'],
            'isSuccess'           => true,
        ];
    }

    public static function orderHistoryDataProvider(): iterable
    {
        $decodedResponse = ['Count' => 1, 'Code' => 0, 'Transactions' => [['OrderId' => '20231209C3AE']]];
        yield [
            'order'               => ['id' => '2020110828BC'],
            'requestData'         => ['clientId' => '1000000494', 'orderId' => '2020110828BC'],
            'decodedResponseData' => $decodedResponse,
            'mappedResponse'      => ['status' => 'approved'],
            'isSuccess'           => true,
        ];
    }

    public static function threeDFormDataProvider(): iterable
    {
        yield '3d_pay' => [
            'order'               => [
                'id'          => 'order222',
                'amount'      => 100.25,
                'installment' => 0,
                'currency'    => 'TRY',
                'success_url' => 'https://domain.com/success',
                'time_span'   => new \DateTimeImmutable('2023-12-09 21:47:08.000000', new \DateTimeZone('UTC')),
            ],
            'paymentModel'        => PosInterface::MODEL_3D_PAY,
            'txType'              => PosInterface::TX_TYPE_PAY_AUTH,
            'isWithCard'          => true,
            'requestData'         => [
                'clientId'    => '1000000494',
                'apiUser'     => 'POS_ENT_Test_001',
                'callbackUrl' => 'https://domain.com/success',
                'hash'        => '+XGO1qv+6W7nXZwSsYMaRrWXhi+99jffLvExGsFDodYyNadOG7OQKsygzly5ESDoNIS19oD2U+hSkVeT6UTAFA==',
            ],
            'decodedResponseData' => [
                'ThreeDSessionId' => 'PA49E341381C94587AB4CB196DAC10DC02E509578520E4471A3EEE2BB4830AE4F',
                'TransactionId'   => '2000000000032439',
                'Code'            => 0,
                'Message'         => 'Başarılı',
            ],
            'formData'            => [
                'gateway' => 'https://ent.akodepos.com/api/Payment/ProcessCardForm',
                'method'  => 'POST',
                'inputs'  => [
                    'ThreeDSessionId' => 'P6D383818909442128AB50AB1EC7A4B83080874341688447DA74B90150C8857F2',
                    'CardHolderName'  => 'ahmet',
                    'CardNo'          => '5555444433332222',
                    'ExpireDate'      => '01/22',
                    'Cvv'             => '123',
                ],
            ],
            'gateway_url'         => 'https://ent.akodepos.com/api/Payment/ProcessCardForm',
        ];

        yield '3d_host' => [
            'order'               => [
                'id'          => 'order222',
                'amount'      => 100.25,
                'installment' => 0,
                'currency'    => 'TRY',
                'success_url' => 'https://domain.com/success',
                'time_span'   => new \DateTimeImmutable('2023-12-09 21:47:08.000000', new \DateTimeZone('UTC')),
            ],
            'paymentModel'        => PosInterface::MODEL_3D_HOST,
            'txType'              => PosInterface::TX_TYPE_PAY_AUTH,
            'isWithCard'          => false,
            'requestData'         => [
                'clientId'         => '1000000494',
                'apiUser'          => 'POS_ENT_Test_001',
                'rnd'              => 'rand',
                'timeSpan'         => '20231209214708',
                'hash'             => '+XGO1qv+6W7nXZwSsYMaRrWXhi+99jffLvExGsFDodYyNadOG7OQKsygzly5ESDoNIS19oD2U+hSkVeT6UTAFA==',
            ],
            'decodedResponseData' => [
                'ThreeDSessionId' => 'PA49E341381C94587AB4CB196DAC10DC02E509578520E4471A3EEE2BB4830AE4F',
                'TransactionId'   => '2000000000032439',
                'Code'            => 0,
                'Message'         => 'Başarılı',
            ],
            'formData'            => [
                'gateway' => 'https://ent.akodepos.com/api/Payment/ProcessCardForm',
                'method'  => 'POST',
                'inputs'  => [
                    'ThreeDSessionId' => 'P6D383818909442128AB50AB1EC7A4B83080874341688447DA74B90150C8857F2',
                    'CardHolderName'  => 'ahmet',
                    'CardNo'          => '5555444433332222',
                    'ExpireDate'      => '01/22',
                    'Cvv'             => '123',
                ],
            ],
            'gateway_url'         => 'https://ent.akodepos.com/api/Payment/threeDSecure/PA49E341381C94587AB4CB196DAC10DC02E509578520E4471A3EEE2BB4830AE4F',
        ];
    }


    public static function make3DPayPaymentDataProvider(): array
    {
        return [
            'auth_fail' => [
                'order'       => [
                    'currency' => 'TRY',
                    'amount'   => 1.01,
                ],
                'txType'      => 'pay',
                'request'     => [
                    'ClientId'       => '1000000494',
                    'OrderId'        => '20231203E148',
                    'MdStatus'       => '0',
                    'HashParameters' => 'ClientId,ApiUser,OrderId,MdStatus,BankResponseCode,BankResponseMessage,RequestStatus',
                    'Hash'           => 'C7Vbcr3adDhlWEr9vT9oFHikjrjEiv5DSBORu0YnOATkF/YirOziwouAGk8vqB29oeyPBnlFgBih7bLN9YWweQ==',
                ],
                'expected'    => [
                    'tx_status'        => 'ERROR',
                    'md_error_message' => null,
                    'order_id'         => '20231203E148',
                    'proc_return_code' => 'MD:0',
                    'status'           => 'declined',
                ],
                'is3DSuccess' => false,
                'isSuccess'   => false,
            ],
            'success'   => [
                'order'       => [
                    'currency' => 'TRY',
                    'amount'   => 1.01,
                ],
                'txType'      => 'pay',
                'request'     => [
                    'ClientId'       => '1000000494',
                    'OrderId'        => '202312034E91',
                    'MdStatus'       => '1',
                    'HashParameters' => 'ClientId,ApiUser,OrderId,MdStatus,BankResponseCode,BankResponseMessage,RequestStatus',
                    'Hash'           => 'CgibjWkLpfx+Cz6cVlbH1ViSW74ouKACVOW0Vrt2SfqPMt+V3hfIx/4LnOgcInFhPci/qcnIMgdN0RptHSmFOg==',
                ],
                'expected'    => [
                    'md_status'        => '1',
                    'tx_status'        => 'PAYMENT_COMPLETED',
                    'md_error_message' => null,
                    'order_id'         => '202312034E91',
                    'proc_return_code' => '00',
                    'status'           => 'approved',
                ],
                'is3DSuccess' => true,
                'isSuccess'   => true,
            ],
        ];
    }

    public static function make3DPayPaymentWithoutHashCheckDataProvider(): array
    {
        return [
            'success' => [
                'order'       => [
                    'currency' => 'TRY',
                    'amount'   => 1.01,
                ],
                'txType'      => 'pay',
                'request'     => [
                    'ClientId'       => '1000000494',
                    'OrderId'        => '202312034E91',
                    'MdStatus'       => '1',
                    'HashParameters' => 'ClientId,ApiUser,OrderId,MdStatus,BankResponseCode,BankResponseMessage,RequestStatus',
                    'Hash'           => 'CgibjWkLpfx+Cz6cVlbH1ViSW74ouKACVOW0Vrt2SfqPMt+V3hfIx/4LnOgcInFhPci/qcnIMgdN0RptHSmFOg==',
                ],
                'expected'    => [
                    'md_status'        => '1',
                    'tx_status'        => 'PAYMENT_COMPLETED',
                    'md_error_message' => null,
                    'order_id'         => '202312034E91',
                    'proc_return_code' => '00',
                    'status'           => 'approved',
                ],
                'is3DSuccess' => true,
                'isSuccess'   => true,
            ],
        ];
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
            $account
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
            $account
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
            $account
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
            $account
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
            $account
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapRefundResponse')
            ->with($decodedResponse)
            ->willReturn(['result']);

        $this->pos->refund($order);
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

    public static function registerFailResponseDataProvider(): array
    {
        return [
            'merchant_not_found' => [
                'response' => [
                    'Code'            => 202,
                    'Message'         => 'Üye İşyeri Kullanıcısı Bulunamadı',
                    'ThreeDSessionId' => null,
                    'TransactionId'   => null,
                ],
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
                'expectedExceptionMsg'   => 'Bu ödeme modeli için kart bilgileri zorunlu!',
            ],
            'unsupported_payment_model' => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_SECURE,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => false,
                'create_without_card'    => false,
                'expectedExceptionClass' => LogicException::class,
                'expectedExceptionMsg'   => 'Mews\Pos\Gateway\ToslaPos ödeme altyapıda [pay] işlem tipi [3d_pay, 3d_host, regular] ödeme model(ler) desteklemektedir. Sağlanan ödeme model: [3d]',
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
                'paymentModel'           => PosInterface::MODEL_3D_HOST,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => false,
                'create_without_card'    => false,
                'expectedExceptionClass' => UnsupportedFormFormatException::class,
                'expectedExceptionMsg'   => 'Unsupported 3D form format!',
                'formFormat'             => PosInterface::FORM_FORMAT_HTML,
            ],
        ];
    }

    private function createGateway(array $config, ?AbstractPosAccount $account = null): PosInterface
    {
        return new ToslaPos(
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
