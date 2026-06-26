<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Gateway;

use RuntimeException;
use DateTimeImmutable;
use LogicException;
use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Client\HttpClientStrategyInterface;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\Request\Mapper\RequestDataMapperInterface;
use Mews\Pos\DataMapper\Request\ValueMapper\PayTrPosRequestValueMapper;
use Mews\Pos\DataMapper\Response\Mapper\ResponseDataMapperInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exception\HashMismatchException;
use Mews\Pos\Exception\UnsupportedPaymentModelException;
use Mews\Pos\Exception\NotImplementedException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Gateway\AbstractGateway;
use Mews\Pos\Gateway\PayTrPos;
use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Model\Account\PayTrPosAccount;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

#[CoversClass(PayTrPos::class)]
#[CoversClass(AbstractGateway::class)]
class PayTrPosTest extends TestCase
{
    private array $config;

    private CreditCardInterface $card;

    private PayTrPosAccount $account;

    /** @var PayTrPos */
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

    private PayTrPosRequestValueMapper $requestValueMapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'name'              => 'PayTR',
            'class'             => PayTrPos::class,
            'gateway_endpoints' => [
                'payment_api'     => 'https://www.paytr.com',
                'gateway_3d'      => 'https://www.paytr.com/odeme',
                'gateway_3d_host' => 'https://www.paytr.com/odeme/guvenli',
            ],
        ];

        $this->account = AccountFactory::createPayTrPosAccount(
            'paytr',
            '123456',
            'wWwU8buJp6jo1r25',
            'YEUaNcdHXqyt7hjt',
        );

        $this->requestValueMapper     = new PayTrPosRequestValueMapper();
        $this->requestMapperMock      = $this->createMock(RequestDataMapperInterface::class);
        $this->responseMapperMock     = $this->createMock(ResponseDataMapperInterface::class);
        $this->cryptMock              = $this->createMock(CryptInterface::class);
        $this->httpClientStrategyMock = $this->createMock(HttpClientStrategyInterface::class);
        $this->httpClientMock         = $this->createMock(HttpClientInterface::class);
        $this->loggerMock             = $this->createMock(LoggerInterface::class);
        $this->eventDispatcherMock    = $this->createMock(EventDispatcherInterface::class);

        $this->pos = $this->createGateway($this->config, $this->account);

        $this->card = CreditCardFactory::create('4355084355084358', '30', '12', '000', 'John Doe');
    }

    public function testInit(): void
    {
        $this->assertSame($this->config, $this->pos->getConfig());
        $this->assertSame($this->account, $this->pos->getAccount());
        $this->assertInstanceOf(PayTrPosAccount::class, $this->pos->getAccount());
        $this->assertSame($this->account, $this->pos->getAccount());
        $this->assertFalse($this->pos->isTestMode());
        $this->assertSame($this->cryptMock, $this->pos->getCrypt());
    }

    #[TestWith([PosInterface::MODEL_3D_PAY, 'gateway_3d'])]
    #[TestWith([PosInterface::MODEL_3D_HOST, 'gateway_3d_host'])]
    public function testGet3DGatewayURLDefault(string $paymentModel, string $expected): void
    {
        $this->assertSame(
            $this->config['gateway_endpoints'][$expected],
            $this->pos->get3DGatewayURL($paymentModel)
        );
    }

    public function testMake3DPaymentThrowsUnsupported(): void
    {
        $this->expectException(UnsupportedPaymentModelException::class);
        $this->pos->make3DPayment([], [], PosInterface::TX_TYPE_PAY_AUTH);
    }

    /**
     * For 3DPay, 3DHost, when count($gatewayResponseData) <= 1, no hash check is performed.
     * A single fail_message key is returned by PayTR on 3D auth failure at the payment page.
     */
    #[TestWith([PosInterface::MODEL_3D_PAY, 'map3DPayResponseData'])]
    #[TestWith([PosInterface::MODEL_3D_HOST, 'map3DHostResponseData'])]
    public function testMake3DPaymentSingleElementNoHashCheck(string $paymentModel, string $mapperMethod): void
    {
        $txType              = PosInterface::TX_TYPE_PAY_AUTH;
        $gatewayResponseData = ['fail_message' => '3D auth failed at payment page'];
        $order               = ['id' => 'order-1'];
        $expectedResponse    = ['status' => 'declined', 'error_message' => '3D auth failed at payment page'];

        $this->cryptMock->expects(self::never())->method('check3DHash');

        $this->responseMapperMock->expects(self::once())
            ->method($mapperMethod)
            ->with($gatewayResponseData, $txType, $order)
            ->willReturn($expectedResponse);

        $result = $this->pos->payment(
            $paymentModel,
            $order,
            $txType,
            null,
            $gatewayResponseData
        );

        $this->assertSame($expectedResponse, $result);
        $this->assertFalse($this->pos->isSuccess());
    }

    #[TestWith([PosInterface::MODEL_3D_PAY, 'map3DPayResponseData'])]
    #[TestWith([PosInterface::MODEL_3D_HOST, 'map3DHostResponseData'])]
    public function testMake3DPaymentHashCheckPasses(string $paymentModel, string $mapperMethod): void
    {
        $txType              = PosInterface::TX_TYPE_PAY_AUTH;
        $gatewayResponseData = [
            'merchant_oid' => '20260623E335',
            'status'       => 'success',
            'total_amount' => '1001',
            'hash'         => 'valid-hash',
            'currency'     => 'TL',
        ];
        $order               = ['id' => '20260623E335'];
        $expectedResponse    = ['status' => 'approved', 'order_id' => '20260623E335'];

        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->with($this->account, $gatewayResponseData)
            ->willReturn(true);

        $this->responseMapperMock->expects(self::once())
            ->method($mapperMethod)
            ->with($gatewayResponseData, $txType, $order)
            ->willReturn($expectedResponse);

        $result = $this->pos->payment(
            $paymentModel,
            $order,
            $txType,
            null,
            $gatewayResponseData
        );

        $this->assertSame($expectedResponse, $result);
    }

    #[TestWith([PosInterface::MODEL_3D_PAY, 'map3DPayResponseData'])]
    #[TestWith([PosInterface::MODEL_3D_HOST, 'map3DHostResponseData'])]
    public function testMake3DPaymentHashMismatchException(string $paymentModel, string $mapperMethod): void
    {
        $gatewayResponseData = [
            'merchant_oid' => 'order-123',
            'status'       => 'success',
            'total_amount' => '1001',
            'hash'         => 'wrong-hash',
            'currency'     => 'TL',
        ];

        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->with($this->account, $gatewayResponseData)
            ->willReturn(false);


        $this->responseMapperMock->expects(self::never())
            ->method($mapperMethod);

        $this->expectException(HashMismatchException::class);

        $this->pos->payment($paymentModel, [], PosInterface::TX_TYPE_PAY_AUTH, null, $gatewayResponseData);
    }

    #[TestWith([PosInterface::MODEL_3D_PAY, 'map3DPayResponseData'])]
    #[TestWith([PosInterface::MODEL_3D_HOST, 'map3DHostResponseData'])]
    public function testMake3DPayPaymentWithHashCheckDisabled(string $paymentModel, string $mapperMethod): void
    {
        $config = $this->config;
        $config['gateway_configs']['disable_3d_hash_check'] = true;

        $pos    = $this->createGateway($config);

        $gatewayResponseData = [
            'merchant_oid' => '20260623E335',
            'status'       => 'success',
            'total_amount' => '1001',
            'hash'         => 'any-hash',
            'currency'     => 'TL',
        ];
        $order               = ['id' => '20260623E335'];
        $expectedResponse    = ['status' => 'approved'];

        $this->cryptMock->expects(self::never())->method('check3DHash');

        $this->responseMapperMock->expects(self::once())
            ->method($mapperMethod)
            ->willReturn($expectedResponse);

        $result = $pos->payment(
            $paymentModel,
            $order,
            PosInterface::TX_TYPE_PAY_AUTH,
            null,
            $gatewayResponseData
        );

        $this->assertSame($expectedResponse, $result);
    }

    #[TestWith([PosInterface::MODEL_3D_PAY, 'map3DPayResponseData'])]
    #[TestWith([PosInterface::MODEL_3D_HOST, 'map3DHostResponseData'])]
    public function testMake3DHostPaymentSuccess(string $paymentModel, string $mapperMethod): void
    {
        $txType              = PosInterface::TX_TYPE_PAY_AUTH;
        $gatewayResponseData = [
            'merchant_oid' => '20260623E335',
            'status'       => 'success',
            'total_amount' => '1001',
            'hash'         => 'valid-hash',
            'currency'     => 'TL',
        ];
        $order               = ['id' => '20260623E335'];
        $expectedResponse    = ['status' => 'approved', 'order_id' => '20260623E335'];

        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->with($this->account, $gatewayResponseData)
            ->willReturn(true);

        $this->responseMapperMock->expects(self::once())
            ->method($mapperMethod)
            ->with($gatewayResponseData, $txType, $order)
            ->willReturn($expectedResponse);

        $result = $this->pos->payment($paymentModel, $order, $txType, null, $gatewayResponseData);

        $this->assertSame($expectedResponse, $result);
        $this->assertTrue($this->pos->isSuccess());
    }

    public function testGet3DFormDataFor3DPay(): void
    {
        $order    = ['id' => 'order-pay', 'amount' => 10.50];
        $formData = ['gateway' => 'https://www.paytr.com/odeme', 'method' => 'POST', 'inputs' => ['a' => 'b']];

        $this->requestMapperMock->expects(self::once())
            ->method('create3DFormData')
            ->with(
                $this->account,
                $order,
                PosInterface::MODEL_3D_PAY,
                PosInterface::TX_TYPE_PAY_AUTH,
                $this->config['gateway_endpoints']['gateway_3d'],
                $this->card
            )
            ->willReturn($formData);

        $actual = $this->pos->get3DFormData($order, PosInterface::MODEL_3D_PAY, PosInterface::TX_TYPE_PAY_AUTH, $this->card);

        $this->assertSame($formData, $actual);
    }

    public function testGet3DFormDataFor3DHostSuccess(): void
    {
        $paymentModel = PosInterface::MODEL_3D_HOST;
        $orderTxType = PosInterface::TX_TYPE_PAY_AUTH;
        $order         = ['id' => 'order-host', 'amount' => 10.50];
        $initReqData   = ['merchant_id' => '123456', 'merchant_oid' => 'order-host'];
        $tokenResponse = ['status' => 'success', 'token' => 'mytoken123'];
        $formData      = [
            'gateway' => 'https://www.paytr.com/odeme/guvenli/mytoken123',
            'method'  => 'GET',
            'inputs'  => [],
        ];

        $this->requestMapperMock->expects(self::once())
            ->method('create3DFormInitializeRequestData')
            ->with($this->account, $order, $paymentModel, $orderTxType)
            ->willReturn($initReqData);

        $this->configureClientResponse(
            PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD,
            $tokenResponse,
            $order,
            $paymentModel,
        );

        $this->requestMapperMock->expects(self::once())
            ->method('create3DFormData')
            ->with(
                $this->account,
                $order,
                $paymentModel,
                $orderTxType,
                $this->config['gateway_endpoints']['gateway_3d_host'],
                null,
                $tokenResponse
            )
            ->willReturn($formData);

        $actual = $this->pos->get3DFormData($order, $paymentModel, $orderTxType, null, true);

        $this->assertSame($formData, $actual);
    }

    #[TestWith([['status' => 'error', 'reason' => 'Merchant not active'], 'Merchant not active'])]
    #[TestWith([['status' => 'error'], 'PayTR iFrame token request failed'])]
    public function testGet3DFormDataFor3DHostFailedTokenRequest(array $tokenResponse, string $expectedExceptionMsg): void
    {
        $paymentModel = PosInterface::MODEL_3D_HOST;
        $orderTxType = PosInterface::TX_TYPE_PAY_AUTH;
        $order         = ['id' => 'order-fail', 'amount' => 5.0];
        $initReqData   = ['merchant_id' => '123456'];

        $this->requestMapperMock->expects(self::once())
            ->method('create3DFormInitializeRequestData')
            ->willReturn($initReqData);

        $this->configureClientResponse(
            PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD,
            $tokenResponse,
            $order,
            $paymentModel
        );

        $this->requestMapperMock->expects(self::never())->method('create3DFormData');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($expectedExceptionMsg);

        $this->pos->get3DFormData($order, $paymentModel, $orderTxType, null, true);
    }

    #[DataProvider('get3DFormDataBadInputsProvider')]
    public function testGet3DFormDataWithBadInputs(
        array  $order,
        string $paymentModel,
        string $txType,
        bool   $isWithCard,
        bool   $createWithoutCard,
        string $expectedExceptionClass,
        string $expectedExceptionMsg
    ): void {
        $card = $isWithCard ? $this->card : null;

        $this->expectException($expectedExceptionClass);
        $this->expectExceptionMessage($expectedExceptionMsg);

        $this->pos->get3DFormData($order, $paymentModel, $txType, $card, $createWithoutCard);
    }

    public function testNonSecurePayment(): void
    {
        $order       = ['id' => 'order-ns', 'amount' => 50.00];
        $txType      = PosInterface::TX_TYPE_PAY_AUTH;
        $requestData = ['merchant_id' => '123456', 'merchant_oid' => 'order-ns'];

        $this->requestMapperMock->expects(self::once())
            ->method('createNonSecurePaymentRequestData')
            ->with($this->account, $order, $txType, $this->card)
            ->willReturn($requestData);

        $decodedResponse = ['status' => 'success'];
        $this->configureClientResponse(
            $txType,
            $decodedResponse,
            $order,
            PosInterface::MODEL_NON_SECURE,
            null,
            $this->account,
        );


        $expectedResponse = ['order_id' => 'order-ns', 'status' => 'approved'];
        $this->responseMapperMock->expects(self::once())
            ->method('mapPaymentResponse')
            ->with($decodedResponse, $txType, $order)
            ->willReturn($expectedResponse);

        $result = $this->pos->payment(PosInterface::MODEL_NON_SECURE, $order, $txType, $this->card);

        $this->assertSame($expectedResponse, $result);
        $this->assertTrue($this->pos->isSuccess());
    }

    public function testCancelThrowsNotImplemented(): void
    {
        $this->requestMapperMock->expects(self::once())
            ->method('createCancelRequestData')
            ->willThrowException(new NotImplementedException());

        $this->expectException(NotImplementedException::class);
        $this->pos->cancel([]);
    }

    public function testStatusRequest(): void
    {
        $order       = ['id' => 'order-status'];
        $txType      = PosInterface::TX_TYPE_STATUS;
        $requestData = ['merchant_id' => '123456', 'merchant_oid' => 'order-status'];

        $this->requestMapperMock->expects(self::once())
            ->method('createStatusRequestData')
            ->with($this->account, $order)
            ->willReturn($requestData);

        $decodedResponse  = ['status' => 'success', 'payment_amount' => '10.00'];
        $expectedResponse = ['order_id' => 'order-status', 'status' => 'approved'];

        $this->configureClientResponse(
            $txType,
            $decodedResponse,
            $order,
            PosInterface::MODEL_NON_SECURE,
            null,
            $this->account,
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapStatusResponse')
            ->with($decodedResponse)
            ->willReturn($expectedResponse);

        $result = $this->pos->status($order);

        $this->assertSame($expectedResponse, $result);
    }

    #[DataProvider('refundRequestDataProvider')]
    public function testRefundRequest(array $order, string $txType): void
    {
        $requestData = ['merchant_id' => '123456', 'merchant_oid' => $order['id']];

        $this->requestMapperMock->expects(self::once())
            ->method('createRefundRequestData')
            ->with($this->account, $order, $txType)
            ->willReturn($requestData);

        $decodedResponse  = ['status' => 'success'];
        $expectedResponse = ['status' => 'approved'];

        $this->configureClientResponse(
            $txType,
            $decodedResponse,
            $order,
            PosInterface::MODEL_NON_SECURE,
            null,
            $this->account,
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapRefundResponse')
            ->with($decodedResponse)
            ->willReturn($expectedResponse);

        $result = $this->pos->refund($order);

        $this->assertSame($expectedResponse, $result);
    }

    public function testHistoryRequest(): void
    {
        $order       = ['start_date' => new DateTimeImmutable('2026-06-01'), 'end_date' => new DateTimeImmutable('2026-06-03')];
        $txType      = PosInterface::TX_TYPE_HISTORY;
        $requestData = ['merchant_id' => '123456', 'start_date' => '2026-06-01 00:00:00'];

        $this->requestMapperMock->expects(self::once())
            ->method('createHistoryRequestData')
            ->with($this->account, $order)
            ->willReturn($requestData);

        $decodedResponse  = ['status' => 'success', 'list' => []];
        $expectedResponse = ['status' => 'approved', 'transactions' => []];

        $this->configureClientResponse(
            $txType,
            $decodedResponse,
            $order,
            PosInterface::MODEL_NON_SECURE,
            null,
            $this->account,
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapHistoryResponse')
            ->with($decodedResponse)
            ->willReturn($expectedResponse);

        $result = $this->pos->history($order);

        $this->assertSame($expectedResponse, $result);
    }

    #[TestWith([null])]
    #[TestWith(['https://www.paytr.com/odeme/durum-sorgu'])]
    public function testCustomQueryRequest(?string $apiUrl): void
    {
        $requestData        = ['merchant_id' => '123456', 'merchant_oid' => 'order-cq'];
        $updatedRequestData = $requestData + ['paytr_token' => 'computed-token'];
        $txType             = PosInterface::TX_TYPE_CUSTOM_QUERY;

        $this->requestMapperMock->expects(self::once())
            ->method('createCustomQueryRequestData')
            ->with($this->account, $requestData)
            ->willReturn($updatedRequestData);

        $decodedResponse = ['status' => 'success'];
        $this->configureClientResponse(
            $txType,
            $decodedResponse,
            $requestData,
            PosInterface::MODEL_NON_SECURE,
            $apiUrl,
            $this->account,
        );

        $this->pos->customQuery($requestData, $apiUrl);
    }

    public static function refundRequestDataProvider(): array
    {
        return [
            'full_refund'    => [
                'order'   => ['id' => 'order-ref', 'amount' => 10.50],
                'tx_type' => PosInterface::TX_TYPE_REFUND,
            ],
            'partial_refund' => [
                'order'   => ['id' => 'order-partial', 'amount' => 5.00, 'order_amount' => 10.50],
                'tx_type' => PosInterface::TX_TYPE_REFUND_PARTIAL,
            ],
        ];
    }

    public static function get3DFormDataBadInputsProvider(): array
    {
        return [
            '3d_host_with_card'   => [
                'order'                  => ['id' => 'order-1'],
                'paymentModel'           => PosInterface::MODEL_3D_HOST,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => true,
                'createWithoutCard'      => false,
                'expectedExceptionClass' => LogicException::class,
                'expectedExceptionMsg'   => 'Kart bilgileri ile form verisi oluşturmak icin [3d_host] ödeme modeli kullanmayınız!',
            ],
            '3d_pay_without_card' => [
                'order'                  => ['id' => 'order-2'],
                'paymentModel'           => PosInterface::MODEL_3D_PAY,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => false,
                'createWithoutCard'      => false,
                'expectedExceptionClass' => LogicException::class,
                'expectedExceptionMsg'   => 'Bu ödeme modeli için kart bilgileri zorunlu!',
            ],
            'unsupported_model'   => [
                'order'                  => ['id' => 'order-3'],
                'paymentModel'           => PosInterface::MODEL_3D_SECURE,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => true,
                'createWithoutCard'      => false,
                'expectedExceptionClass' => LogicException::class,
                'expectedExceptionMsg'   => 'Mews\Pos\Gateway\PayTrPos ödeme altyapıda',
            ],
            'non_payment_tx_type' => [
                'order'                  => ['id' => 'order-4'],
                'paymentModel'           => PosInterface::MODEL_3D_HOST,
                'txType'                 => PosInterface::TX_TYPE_STATUS,
                'isWithCard'             => false,
                'createWithoutCard'      => true,
                'expectedExceptionClass' => LogicException::class,
                'expectedExceptionMsg'   => 'Hatalı işlem tipi! Desteklenen işlem tipleri:',
            ],
        ];
    }

    private function createGateway(array $config, ?AbstractPosAccount $account = null): PosInterface
    {
        return new PayTrPos(
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
        array               $decodedResponse,
        array               $order,
        string              $paymentModel,
        ?string             $apiUrl = null,
        ?AbstractPosAccount $account = null
    ): void {
        $updatedEvent = null;

        $this->httpClientStrategyMock->expects(self::once())
            ->method('getClient')
            ->with($txType, $paymentModel)
            ->willReturn($this->httpClientMock);

        $this->httpClientMock->expects(self::once())
            ->method('request')
            ->with(
                $txType,
                $paymentModel,
                $this->callback(fn (array $data): bool => ($data['test-event-marker'] ?? false) === true),
                $order,
                $apiUrl,
                $account
            )
            ->willReturn($decodedResponse);

        $this->eventDispatcherMock->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(
                function (RequestDataPreparedEvent $event) use (&$updatedEvent): RequestDataPreparedEvent {
                    $updatedEvent              = $event;
                    $data                      = $event->getRequestData();
                    $data['test-event-marker'] = true;
                    $event->setRequestData($data);

                    return $event;
                }
            );
    }
}
