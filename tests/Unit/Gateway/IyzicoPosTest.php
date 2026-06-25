<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Gateway;

use PHPUnit\Framework\Attributes\DataProvider;
use LogicException;
use RuntimeException;
use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Client\HttpClientStrategyInterface;
use Mews\Pos\DataMapper\Response\Mapper\ResponseDataMapperInterface;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\Request\Mapper\IyzicoPosRequestDataMapper;
use Mews\Pos\DataMapper\Request\ValueMapper\IyzicoPosRequestValueMapper;
use Mews\Pos\DataMapper\Response\Mapper\IyzicoPosResponseDataMapper;
use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Model\Account\IyzicoPosAccount;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exception\HashMismatchException;
use Mews\Pos\Exception\UnsupportedFormFormatException;
use Mews\Pos\Exception\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Gateway\AbstractGateway;
use Mews\Pos\Gateway\IyzicoPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

#[CoversClass(IyzicoPos::class)]
#[CoversClass(AbstractGateway::class)]
class IyzicoPosTest extends TestCase
{
    private IyzicoPosAccount $account;

    /** @var IyzicoPos */
    private PosInterface $pos;

    /** @var array<string, mixed> */
    private array $config;

    /** @var IyzicoPosRequestDataMapper & MockObject */
    private MockObject $requestMapperMock;

    /** @var IyzicoPosResponseDataMapper & MockObject */
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

    private IyzicoPosRequestValueMapper $requestValueMapper;

    private CreditCardInterface $card;

    private array $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'name'              => 'Iyzico',
            'class'             => IyzicoPos::class,
            'gateway_endpoints' => [
                'payment_api' => 'https://sandbox-api.iyzipay.com',
                'query_api'   => 'https://sandbox-api.iyzipay.com/v2/reporting/payment',
            ],
        ];

        $this->account = AccountFactory::createIyzicoPosAccount(
            'iyzico',
            'sandbox-apiKey',
            'sandbox-secretKey'
        );

        $this->order = [
            'id'          => 'order-001',
            'amount'      => 100.0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://example.com/success',
            'fail_url'    => 'https://example.com/fail',
        ];

        $this->requestValueMapper     = new IyzicoPosRequestValueMapper();
        $this->requestMapperMock      = $this->createMock(IyzicoPosRequestDataMapper::class);
        $this->responseMapperMock     = $this->createMock(IyzicoPosResponseDataMapper::class);
        $this->cryptMock              = $this->createMock(CryptInterface::class);
        $this->httpClientStrategyMock = $this->createMock(HttpClientStrategyInterface::class);
        $this->httpClientMock         = $this->createMock(HttpClientInterface::class);
        $this->loggerMock             = $this->createMock(LoggerInterface::class);
        $this->eventDispatcherMock    = $this->createMock(EventDispatcherInterface::class);

        $this->requestMapperMock->expects(self::any())
            ->method('getCrypt')
            ->willReturn($this->cryptMock);

        $this->pos = $this->createGateway($this->config);

        $this->card = CreditCardFactory::createForGateway(
            $this->pos,
            '5555444433332222',
            '26',
            '12',
            '123',
            'John Doe',
            CreditCardInterface::CARD_TYPE_VISA
        );
    }

    public function testInit(): void
    {
        $this->assertCount(count($this->requestValueMapper->getCurrencyMappings()), $this->pos->getCurrencies());
        $this->assertSame($this->config, $this->pos->getConfig());
        $this->assertSame($this->account, $this->pos->getAccount());
        $this->assertFalse($this->pos->isTestMode());
    }

    #[DataProvider('isSuccessDataProvider')]
    public function testIsSuccess(array $mappedResponse, bool $expected): void
    {
        $txType      = PosInterface::TX_TYPE_PAY_AUTH;
        $requestData = ['req'];

        $this->requestMapperMock->expects(self::once())
            ->method('createNonSecurePaymentRequestData')
            ->willReturn($requestData);

        $this->configureClientResponse(
            $txType,
            [],
            $this->order,
            PosInterface::MODEL_NON_SECURE,
            null,
            $this->account
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapPaymentResponse')
            ->willReturn($mappedResponse);

        $this->pos->payment(PosInterface::MODEL_NON_SECURE, $this->order, $txType, $this->card);

        $this->assertSame($expected, $this->pos->isSuccess());
    }

    #[DataProvider('get3DGatewayURLDataProvider')]
    public function testGet3DGatewayURL(array $endpoints, ?string $paymentModel, string $expected): void
    {
        $pos    = $this->createGateway(['name' => 'Iyzico', 'class' => IyzicoPos::class, 'gateway_endpoints' => $endpoints]);
        $actual = null !== $paymentModel ? $pos->get3DGatewayURL($paymentModel) : $pos->get3DGatewayURL();

        $this->assertSame($expected, $actual);
    }

    public function testGetCardTypeMapping(): void
    {
        $this->assertSame([], $this->pos->getCardTypeMapping());
    }

    public function testGetLanguages(): void
    {
        $this->assertSame(['tr', 'en'], $this->pos->getLanguages());
    }

    public function testIsSupportedTransactionTrue(): void
    {
        $this->assertTrue(IyzicoPos::isSupportedTransaction(PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_3D_SECURE));
        $this->assertTrue(IyzicoPos::isSupportedTransaction(PosInterface::TX_TYPE_CANCEL, PosInterface::MODEL_NON_SECURE));
    }

    public function testIsSupportedTransactionFalse(): void
    {
        $this->assertFalse(IyzicoPos::isSupportedTransaction(PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_3D_PAY));
        $this->assertFalse(IyzicoPos::isSupportedTransaction('unsupported_type', PosInterface::MODEL_NON_SECURE));
    }

    public function testPaymentMissingCardThrows(): void
    {
        $this->expectException(LogicException::class);
        $this->pos->payment(PosInterface::MODEL_NON_SECURE, $this->order, PosInterface::TX_TYPE_PAY_AUTH);
    }

    public function testMakeRegularPostPayment(): void
    {
        $txType       = PosInterface::TX_TYPE_PAY_POST_AUTH;
        $requestData  = ['post-auth-request'];
        $bankResponse = ['status' => 'success'];
        $expected     = ['mapped-post-auth'];

        $this->requestMapperMock->expects(self::once())
            ->method('createNonSecurePostAuthPaymentRequestData')
            ->with($this->account, $this->order)
            ->willReturn($requestData);

        $this->configureClientResponse(
            $txType,
            $bankResponse,
            $this->order,
            PosInterface::MODEL_NON_SECURE,
            null,
            $this->account
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapPaymentResponse')
            ->with($bankResponse, $txType, $this->order)
            ->willReturn($expected);

        $result = $this->pos->payment($txType, $this->order, $txType);

        $this->assertSame($expected, $result);
        $this->assertSame($expected, $this->pos->getResponse());
    }

    public function testCustomQuery(): void
    {
        $txType       = PosInterface::TX_TYPE_CUSTOM_QUERY;
        $requestData  = ['custom' => 'data'];
        $prepared     = ['custom' => 'data'];
        $bankResponse = ['result' => 'ok'];

        $this->requestMapperMock->expects(self::once())
            ->method('createCustomQueryRequestData')
            ->with($this->account, $requestData)
            ->willReturn($prepared);

        $this->configureClientResponse(
            $txType,
            $bankResponse,
            $requestData,
            PosInterface::MODEL_NON_SECURE,
            null,
            $this->account
        );

        $result = $this->pos->customQuery($requestData);

        $this->assertSame($bankResponse, $result);
        $this->assertSame($bankResponse, $this->pos->getResponse());
    }

    public function testMake3DPayPaymentThrows(): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->pos->payment(PosInterface::MODEL_3D_PAY, [], PosInterface::TX_TYPE_PAY_AUTH, null, ['token' => 'x']);
    }

    public function testGet3DFormDataArrayFormatWith3DSecureThrows(): void
    {
        $this->expectException(UnsupportedFormFormatException::class);
        $this->pos->get3DFormData(
            $this->order,
            PosInterface::MODEL_3D_SECURE,
            PosInterface::TX_TYPE_PAY_AUTH,
            $this->card,
            false,
            PosInterface::FORM_FORMAT_ARRAY
        );
    }

    public function testGet3DFormDataFailedInitResponse(): void
    {
        $txType       = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel = PosInterface::MODEL_3D_SECURE;
        $initResponse = ['status' => 'failure', 'errorMessage' => '3D init failed', 'errorCode' => '404'];

        $this->requestMapperMock->expects(self::once())
            ->method('create3DFormInitializeRequestData')
            ->willReturn(['request-data']);

        $this->configureClientResponse(
            PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD,
            $initResponse,
            $this->order,
            $paymentModel,
            null,
            $this->account
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('3D init failed');

        $this->pos->get3DFormData(
            $this->order,
            $paymentModel,
            $txType,
            $this->card
        );
    }

    public function testGet3DFormData3DSecureReturnsHtml(): void
    {
        $txType       = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel = PosInterface::MODEL_3D_SECURE;
        $htmlContent  = '<html lang="tr">3D Secure Form</html>';
        $initResponse = [
            'status'             => IyzicoPosResponseDataMapper::PROCEDURE_SUCCESS_CODE,
            'threeDSHtmlContent' => base64_encode($htmlContent),
        ];

        $this->requestMapperMock->expects(self::once())
            ->method('create3DFormInitializeRequestData')
            ->willReturn(['request-data']);

        $this->configureClientResponse(
            PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD,
            $initResponse,
            $this->order,
            $paymentModel,
            null,
            $this->account
        );

        $result = $this->pos->get3DFormData(
            $this->order,
            $paymentModel,
            $txType,
            $this->card
        );

        $this->assertSame($htmlContent, $result);
    }

    public function testGet3DFormData3DHostReturnsHtmlByDefault(): void
    {
        $txType          = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel    = PosInterface::MODEL_3D_HOST;
        $checkoutContent = '<script>checkout</script>';
        $initResponse    = [
            'status'              => IyzicoPosResponseDataMapper::PROCEDURE_SUCCESS_CODE,
            'checkoutFormContent' => $checkoutContent,
            'paymentPageUrl'      => 'https://checkout.iyzipay.com/pay/xxx',
        ];

        $this->requestMapperMock->expects(self::once())
            ->method('create3DFormInitializeRequestData')
            ->willReturn(['request-data']);

        $this->configureClientResponse(
            PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD,
            $initResponse,
            $this->order,
            $paymentModel,
            null,
            $this->account
        );

        $result = $this->pos->get3DFormData(
            $this->order,
            $paymentModel,
            $txType,
            null,
            true
        );

        $this->assertSame($checkoutContent, $result);
    }

    public function testGet3DFormData3DHostReturnsArray(): void
    {
        $txType       = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel = PosInterface::MODEL_3D_HOST;
        $initResponse = [
            'status'              => IyzicoPosResponseDataMapper::PROCEDURE_SUCCESS_CODE,
            'checkoutFormContent' => '<script/>',
            'paymentPageUrl'      => 'https://checkout.iyzipay.com/pay/xxx',
        ];

        $this->requestMapperMock->expects(self::once())
            ->method('create3DFormInitializeRequestData')
            ->willReturn(['request-data']);

        $this->configureClientResponse(
            PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD,
            $initResponse,
            $this->order,
            $paymentModel,
            null,
            $this->account
        );

        $result = $this->pos->get3DFormData(
            $this->order,
            $paymentModel,
            $txType,
            null,
            true,
            PosInterface::FORM_FORMAT_ARRAY
        );

        $this->assertIsArray($result);
        $this->assertSame('https://checkout.iyzipay.com/pay/xxx', $result['gateway']);
        $this->assertSame('GET', $result['method']);
        $this->assertSame([], $result['inputs']);
    }

    public function testMake3DPaymentWith3DAuthFailure(): void
    {
        $gatewayResponseData = ['mdStatus' => '0', 'status' => 'failure'];
        $txType              = PosInterface::TX_TYPE_PAY_AUTH;
        $expectedResponse    = ['status' => 'declined'];

        $this->responseMapperMock->expects(self::once())
            ->method('extractMdStatus')
            ->with($gatewayResponseData)
            ->willReturn('0');

        $this->responseMapperMock->expects(self::once())
            ->method('is3dAuthSuccess')
            ->with('0')
            ->willReturn(false);

        $this->responseMapperMock->expects(self::once())
            ->method('map3DPaymentData')
            ->with($gatewayResponseData, null, $txType, $this->order)
            ->willReturn($expectedResponse);

        $this->requestMapperMock->expects(self::never())
            ->method('create3DPaymentRequestData');

        $this->eventDispatcherMock->expects(self::never())
            ->method('dispatch');

        $result = $this->pos->payment(PosInterface::MODEL_3D_SECURE, $this->order, $txType, null, $gatewayResponseData);

        $this->assertSame($expectedResponse, $result);
    }

    public function testMake3DPaymentHashMismatchThrows(): void
    {
        $gatewayResponseData = ['mdStatus' => '1', 'status' => 'success'];

        $this->responseMapperMock->expects(self::once())
            ->method('extractMdStatus')
            ->willReturn('1');

        $this->responseMapperMock->expects(self::once())
            ->method('is3dAuthSuccess')
            ->willReturn(true);

        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->with($this->account, $gatewayResponseData)
            ->willReturn(false);

        $this->expectException(HashMismatchException::class);
        $this->pos->payment(PosInterface::MODEL_3D_SECURE, $this->order, PosInterface::TX_TYPE_PAY_AUTH, null, $gatewayResponseData);
    }

    public function testMake3DPaymentSuccess(): void
    {
        $txType              = PosInterface::TX_TYPE_PAY_AUTH;
        $gatewayResponseData = ['mdStatus' => '1', 'conversationId' => 'conv-1', 'paymentId' => 'pay-1'];
        $provisionRequest    = ['create3DPaymentRequestData'];
        $provisionResponse   = ['paymentId' => 'pay-1', 'status' => 'success'];
        $expectedResponse    = ['status' => 'approved'];

        $this->responseMapperMock->expects(self::once())
            ->method('extractMdStatus')
            ->with($gatewayResponseData)
            ->willReturn('1');

        $this->responseMapperMock->expects(self::once())
            ->method('is3dAuthSuccess')
            ->with('1')
            ->willReturn(true);

        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->with($this->account, $gatewayResponseData)
            ->willReturn(true);

        $this->requestMapperMock->expects(self::once())
            ->method('create3DPaymentRequestData')
            ->with($this->account, $this->order, $txType, $gatewayResponseData)
            ->willReturn($provisionRequest);

        $this->configureClientResponse(
            $txType,
            $provisionResponse,
            $this->order,
            PosInterface::MODEL_3D_SECURE,
            null,
            $this->account
        );

        $this->responseMapperMock->expects(self::once())
            ->method('map3DPaymentData')
            ->with($gatewayResponseData, $provisionResponse, $txType, $this->order)
            ->willReturn($expectedResponse);

        $result = $this->pos->payment(PosInterface::MODEL_3D_SECURE, $this->order, $txType, null, $gatewayResponseData);

        $this->assertSame($expectedResponse, $result);
    }

    public function testMake3DPaymentWithoutHashCheck(): void
    {
        $config                    = $this->config;
        $config['gateway_configs'] = ['disable_3d_hash_check' => true];
        $pos                       = $this->createGateway($config);

        $txType              = PosInterface::TX_TYPE_PAY_AUTH;
        $gatewayResponseData = ['mdStatus' => '1'];
        $provisionRequest    = ['provision-data'];
        $provisionResponse   = ['status' => 'success'];
        $expectedResponse    = ['status' => 'approved'];

        $this->responseMapperMock->expects(self::once())
            ->method('extractMdStatus')
            ->willReturn('1');

        $this->responseMapperMock->expects(self::once())
            ->method('is3dAuthSuccess')
            ->willReturn(true);

        $this->cryptMock->expects(self::never())
            ->method('check3DHash');

        $this->requestMapperMock->expects(self::once())
            ->method('create3DPaymentRequestData')
            ->willReturn($provisionRequest);

        $this->configureClientResponse(
            $txType,
            $provisionResponse,
            $this->order,
            PosInterface::MODEL_3D_SECURE,
            null,
            $this->account
        );

        $this->responseMapperMock->expects(self::once())
            ->method('map3DPaymentData')
            ->willReturn($expectedResponse);

        $result = $pos->payment(PosInterface::MODEL_3D_SECURE, $this->order, $txType, null, $gatewayResponseData);

        $this->assertSame($expectedResponse, $result);
    }

    public function testMake3DHostPayment(): void
    {
        $txType              = PosInterface::TX_TYPE_STATUS;
        $paymentModel        = PosInterface::MODEL_3D_HOST;
        $gatewayResponseData = ['token' => 'tok-123'];
        $statusRequest       = ['host-status-request'];
        $statusResponse      = ['status' => 'success', 'paymentId' => 'p-1'];
        $expectedResponse    = ['status' => 'approved'];

        $this->requestMapperMock->expects(self::once())
            ->method('create3DHostPaymentStatusRequestData')
            ->with($gatewayResponseData, $this->order)
            ->willReturn($statusRequest);

        $this->configureClientResponse(
            PosInterface::TX_TYPE_INTERNAL_3D_PAYMENT_STATUS,
            $statusResponse,
            $this->order,
            $paymentModel,
            null,
            $this->account
        );

        $this->responseMapperMock->expects(self::once())
            ->method('map3DHostResponseData')
            ->with($statusResponse, $txType, $this->order)
            ->willReturn($expectedResponse);

        $result = $this->pos->payment(PosInterface::MODEL_3D_HOST, $this->order, $txType, null, $gatewayResponseData);

        $this->assertSame($expectedResponse, $result);
    }

    #[DataProvider('nonSecurePaymentDataProvider')]
    public function testMakeRegularPayment(array $order, string $txType): void
    {
        $requestData     = ['non-secure-request'];
        $decodedResponse = ['payment-response'];
        $expectedResult  = ['result'];

        $this->requestMapperMock->expects(self::once())
            ->method('createNonSecurePaymentRequestData')
            ->with($this->account, $order, $txType, $this->card)
            ->willReturn($requestData);

        $this->configureClientResponse(
            $txType,
            $decodedResponse,
            $order,
            PosInterface::MODEL_NON_SECURE,
            null,
            $this->account
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapPaymentResponse')
            ->with($decodedResponse, $txType, $order)
            ->willReturn($expectedResult);

        $this->pos->payment(PosInterface::MODEL_NON_SECURE, $order, $txType, $this->card);
    }

    public function testStatus(): void
    {
        $txType       = PosInterface::TX_TYPE_STATUS;
        $requestData  = ['status-request'];
        $bankResponse = ['status' => 'success'];
        $expected     = ['mapped-status'];

        $this->requestMapperMock->expects(self::once())
            ->method('createStatusRequestData')
            ->with($this->account, $this->order)
            ->willReturn($requestData);

        $this->configureClientResponse(
            $txType,
            $bankResponse,
            $this->order,
            PosInterface::MODEL_NON_SECURE,
            null,
            $this->account
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapStatusResponse')
            ->with($bankResponse)
            ->willReturn($expected);

        $result = $this->pos->status($this->order);

        $this->assertSame($expected, $result);
    }

    public function testCancel(): void
    {
        $txType       = PosInterface::TX_TYPE_CANCEL;
        $requestData  = ['cancel-request'];
        $bankResponse = ['cancelled' => true];
        $expected     = ['mapped-cancel'];

        $this->requestMapperMock->expects(self::once())
            ->method('createCancelRequestData')
            ->with($this->account, $this->order)
            ->willReturn($requestData);

        $this->configureClientResponse(
            $txType,
            $bankResponse,
            $this->order,
            PosInterface::MODEL_NON_SECURE,
            null,
            $this->account
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapCancelResponse')
            ->with($bankResponse)
            ->willReturn($expected);

        $result = $this->pos->cancel($this->order);

        $this->assertSame($expected, $result);
    }

    public function testRefund(): void
    {
        $txType       = PosInterface::TX_TYPE_REFUND;
        $requestData  = ['refund-request'];
        $bankResponse = ['refunded' => true];
        $expected     = ['mapped-refund'];

        $this->requestMapperMock->expects(self::once())
            ->method('createRefundRequestData')
            ->with($this->account, $this->order, $txType)
            ->willReturn($requestData);

        $this->configureClientResponse(
            $txType,
            $bankResponse,
            $this->order,
            PosInterface::MODEL_NON_SECURE,
            null,
            $this->account
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapRefundResponse')
            ->with($bankResponse)
            ->willReturn($expected);

        $result = $this->pos->refund($this->order);

        $this->assertSame($expected, $result);
    }

    public function testHistory(): void
    {
        $txType       = PosInterface::TX_TYPE_HISTORY;
        $requestData  = ['history-request'];
        $bankResponse = ['items' => []];
        $expected     = ['mapped-history'];

        $this->requestMapperMock->expects(self::once())
            ->method('createHistoryRequestData')
            ->with($this->account, $this->order)
            ->willReturn($requestData);

        $this->configureClientResponse(
            $txType,
            $bankResponse,
            $this->order,
            PosInterface::MODEL_NON_SECURE,
            null,
            $this->account
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapHistoryResponse')
            ->with($bankResponse)
            ->willReturn($expected);

        $result = $this->pos->history($this->order);

        $this->assertSame($expected, $result);
    }

    public function testOrderHistory(): void
    {
        $txType       = PosInterface::TX_TYPE_ORDER_HISTORY;
        $requestData  = ['order-history-request'];
        $bankResponse = ['items' => []];
        $expected     = ['mapped-order-history'];

        $this->requestMapperMock->expects(self::once())
            ->method('createOrderHistoryRequestData')
            ->with($this->account, $this->order)
            ->willReturn($requestData);

        $this->configureClientResponse(
            $txType,
            $bankResponse,
            $this->order,
            PosInterface::MODEL_NON_SECURE,
            null,
            $this->account
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapOrderHistoryResponse')
            ->with($bankResponse)
            ->willReturn($expected);

        $result = $this->pos->orderHistory($this->order);

        $this->assertSame($expected, $result);
    }

    public static function nonSecurePaymentDataProvider(): array
    {
        return [
            [['id' => 'order-1', 'amount' => 100.0], PosInterface::TX_TYPE_PAY_AUTH],
            [['id' => 'order-2', 'amount' => 200.0], PosInterface::TX_TYPE_PAY_PRE_AUTH],
        ];
    }

    public static function get3DGatewayURLDataProvider(): array
    {
        $defaultEndpoints = [
            'payment_api' => 'https://sandbox-api.iyzipay.com',
            'gateway_3d'  => 'https://sandbox-api.iyzipay.com',
            'query_api'   => 'https://sandbox-api.iyzipay.com/v2/reporting/payment',
        ];
        $endpointsWithHost = array_merge($defaultEndpoints, ['gateway_3d_host' => 'https://host.iyzipay.com']);

        return [
            'default_no_model'             => [
                'endpoints'    => $defaultEndpoints,
                'paymentModel' => null,
                'expected'     => 'https://sandbox-api.iyzipay.com',
            ],
            '3d_host_with_host_endpoint'   => [
                'endpoints'    => $endpointsWithHost,
                'paymentModel' => PosInterface::MODEL_3D_HOST,
                'expected'     => 'https://host.iyzipay.com',
            ],
            '3d_secure_with_host_endpoint' => [
                'endpoints'    => $endpointsWithHost,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'expected'     => 'https://sandbox-api.iyzipay.com',
            ],
        ];
    }

    public static function isSuccessDataProvider(): array
    {
        return [
            'approved'  => [['status' => ResponseDataMapperInterface::TX_APPROVED], true],
            'declined'  => [['status' => ResponseDataMapperInterface::TX_DECLINED], false],
            'no_status' => [[], false],
        ];
    }

    private function createGateway(array $config, ?AbstractPosAccount $account = null): PosInterface
    {
        return new IyzicoPos(
            $config,
            $account ?? $this->account,
            $this->requestValueMapper,
            $this->requestMapperMock,
            $this->responseMapperMock,
            $this->eventDispatcherMock,
            $this->httpClientStrategyMock,
            $this->loggerMock
        );
    }

    private function configureClientResponse(
        string              $apiRequestTxType,
        array               $decodedResponse,
        array               $order,
        string              $paymentModel,
        ?string             $apiUrl = null,
        ?AbstractPosAccount $account = null
    ): void {
        $updatedEvent = null;

        $this->httpClientStrategyMock->expects(self::once())
            ->method('getClient')
            ->with($apiRequestTxType, $paymentModel)
            ->willReturn($this->httpClientMock);

        $this->httpClientMock->expects(self::once())
            ->method('request')
            ->with(
                $apiRequestTxType,
                $paymentModel,
                $this->callback(fn (array $data): bool => ($data['test-event-marker'] ?? false) === true),
                $order,
                $apiUrl,
                $account
            )
            ->willReturn($decodedResponse);

        $this->eventDispatcherMock->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(function (RequestDataPreparedEvent $event) use (&$updatedEvent): RequestDataPreparedEvent {
                $updatedEvent              = $event;
                $data                      = $event->getRequestData();
                $data['test-event-marker'] = true;
                $event->setRequestData($data);

                return $event;
            });
    }
}
