<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Gateways;

use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Client\HttpClientStrategyInterface;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\KuveytPosRequestDataMapper;
use Mews\Pos\DataMapper\RequestValueMapper\KuveytPosRequestValueMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\ResponseDataMapperInterface;
use Mews\Pos\Entity\Account\BoaPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exceptions\UnsupportedFormFormatException;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Gateways\KuveytPos;
use Mews\Pos\PosInterface;
use Mews\Pos\Tests\Unit\DataMapper\ResponseDataMapper\KuveytPosResponseDataMapperTest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * @covers \Mews\Pos\Gateways\KuveytPos
 * @covers \Mews\Pos\Gateways\AbstractGateway
 */
class KuveytPosTest extends TestCase
{
    private BoaPosAccount $account;

    private array $config;

    private CreditCardInterface $card;

    private array $order;

    /** @var KuveytPos */
    private PosInterface $pos;

    /** @var KuveytPosRequestDataMapper & MockObject */
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

    private KuveytPosRequestValueMapper $requestValueMapper;

    /**
     * @return void
     *
     * @throws \Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'name'              => 'kuveyt-pos',
            'class'             => KuveytPos::class,
            'gateway_endpoints' => [
                'gateway_3d' => 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/ThreeDModelPayGate',
            ],
        ];

        $this->account = AccountFactory::createKuveytPosAccount(
            'kuveytpos',
            '496',
            'apiuser1',
            '400235',
            'Api123'
        );

        $this->order = [
            'id'          => '2020110828BC',
            'amount'      => 10.01,
            'installment' => '0',
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'http://localhost/finansbank-payfor/3d/response.php',
            'fail_url'    => 'http://localhost/finansbank-payfor/3d/response.php',
            'ip'          => '127.0.0.1',
            'lang'        => PosInterface::LANG_TR,
        ];

        $this->requestValueMapper     = new KuveytPosRequestValueMapper();
        $this->requestMapperMock      = $this->createMock(KuveytPosRequestDataMapper::class);
        $this->responseMapperMock     = $this->createMock(ResponseDataMapperInterface::class);
        $this->cryptMock              = $this->createMock(CryptInterface::class);
        $this->httpClientStrategyMock = $this->createMock(HttpClientStrategyInterface::class);
        $this->httpClientMock         = $this->createMock(HttpClientInterface::class);
        $this->loggerMock             = $this->createMock(LoggerInterface::class);
        $this->eventDispatcherMock    = $this->createMock(EventDispatcherInterface::class);

        $this->requestMapperMock->expects(self::any())
            ->method('getCrypt')
            ->willReturn($this->cryptMock);

        $this->pos = new KuveytPos(
            $this->config,
            $this->account,
            $this->requestValueMapper,
            $this->requestMapperMock,
            $this->responseMapperMock,
            $this->eventDispatcherMock,
            $this->httpClientStrategyMock,
            $this->loggerMock,
        );

        $this->card = CreditCardFactory::createForGateway(
            $this->pos,
            '4155650100416111',
            25,
            1,
            '123',
            'John Doe',
            CreditCardInterface::CARD_TYPE_VISA
        );
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
    }

    /**
     * @return void
     */
    public function testSetTestMode(): void
    {
        $this->pos->setTestMode(false);
        $this->assertFalse($this->pos->isTestMode());
        $this->pos->setTestMode(true);
        $this->assertTrue($this->pos->isTestMode());
    }

    /**
     * @return void
     */
    public function testGetCommon3DFormDataSuccessResponse(): void
    {
        $response     = 'bank-api-html-response';
        $txType       = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel = PosInterface::MODEL_3D_SECURE;
        $card         = $this->card;
        $requestData  = ['form-data'];
        $order        = $this->order;
        $this->configureClientResponse(
            PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD,
            $requestData,
            $response,
            $order,
            $paymentModel,
        );

        $this->requestMapperMock->expects(self::once())
            ->method('create3DFormInitializeRequestData')
            ->with(
                $this->pos->getAccount(),
                $this->order,
                $paymentModel,
                $txType,
                $card
            )
            ->willReturn($requestData);

        $this->requestMapperMock->expects(self::never())
            ->method('create3DFormData');

        $result = $this->pos->get3DFormData($order, $paymentModel, $txType, $card);

        $this->assertSame($response, $result);
    }

    /**
     * @dataProvider threeDFormDataBadInputsProvider
     */
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
     * @dataProvider make3DPaymentDataProvider
     */
    public function testMake3DPayment(
        array  $order,
        string $txType,
        array  $gatewayResponseData,
        array  $decodedRequest,
        array  $paymentResponse,
        array  $expectedResponse,
        bool   $is3DSuccess,
        bool   $isSuccess
    ): void {
        $this->cryptMock->expects(self::never())
            ->method('check3DHash');

        $this->responseMapperMock->expects(self::once())
            ->method('extractMdStatus')
            ->with($decodedRequest)
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
                ->with($this->account, $order, $txType, $decodedRequest)
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
                ->with($decodedRequest, $paymentResponse, $txType, $order)
                ->willReturn($expectedResponse);
        } else {
            $this->responseMapperMock->expects(self::once())
                ->method('map3DPaymentData')
                ->with($decodedRequest, null, $txType, $order)
                ->willReturn($expectedResponse);

            $this->requestMapperMock->expects(self::never())
                ->method('create3DPaymentRequestData');
        }

        $result = $this->pos->payment(PosInterface::MODEL_3D_SECURE, $order, $txType, null, $gatewayResponseData);

        $this->assertSame($expectedResponse, $result);
        $this->assertSame($isSuccess, $this->pos->isSuccess());
    }

    public function testMake3DPaymentException(): void
    {
        $txType = PosInterface::TX_TYPE_PAY_AUTH;

        $this->cryptMock->expects(self::never())
            ->method('check3DHash');

        $this->responseMapperMock->expects(self::never())
            ->method('extractMdStatus');

        $this->responseMapperMock->expects(self::never())
            ->method('is3dAuthSuccess');


        $this->responseMapperMock->expects(self::never())
            ->method('map3DPaymentData');

        $this->requestMapperMock->expects(self::never())
            ->method('create3DPaymentRequestData');

        $this->expectException(\LogicException::class);
        $this->pos->payment(PosInterface::MODEL_3D_SECURE, [], $txType, null, ['abc']);
    }

    /**
     * @dataProvider makeRegularPaymentDataProvider
     */
    public function testMakeRegularPayment(array $order, string $txType): void
    {
        $account     = $this->pos->getAccount();
        $card        = $this->card;
        $requestData = ['createNonSecurePaymentRequestData'];
        $this->requestMapperMock->expects(self::once())
            ->method('createNonSecurePaymentRequestData')
            ->with($account, $order, $txType, $card)
            ->willReturn($requestData);

        $paymentResponse = ['paymentResponse'];

        $this->configureClientResponse(
            $txType,
            $requestData,
            $paymentResponse,
            $order,
            PosInterface::MODEL_NON_SECURE
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapPaymentResponse')
            ->with($paymentResponse, $txType, $order)
            ->willReturn(['result']);

        $this->pos->payment(PosInterface::MODEL_NON_SECURE, $order, $txType, $card);
    }

    public function testMakeRegularPostAuthPayment(): void
    {
        $this->expectException(UnsupportedPaymentModelException::class);
        $this->pos->payment(PosInterface::MODEL_NON_SECURE, [], PosInterface::TX_TYPE_PAY_POST_AUTH);
    }

    /**
     * @dataProvider statusDataProvider
     */
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
            $account,
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapStatusResponse')
            ->with($bankResponse)
            ->willReturn($expectedData);

        $result = $this->pos->status($order);

        $this->assertSame($expectedData, $result);
        $this->assertSame($isSuccess, $this->pos->isSuccess());
    }
    /**
     * @dataProvider cancelDataProvider
     */
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
            $account,
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapCancelResponse')
            ->with($bankResponse)
            ->willReturn($expectedData);

        $result = $this->pos->cancel($order);

        $this->assertSame($expectedData, $result);
        $this->assertSame($isSuccess, $this->pos->isSuccess());
    }

    /**
     * @dataProvider refundDataProvider
     */
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
            $account,
        );

        $this->responseMapperMock->expects(self::once())
            ->method('mapRefundResponse')
            ->with($bankResponse)
            ->willReturn($expectedData);

        $result = $this->pos->refund($order);

        $this->assertSame($expectedData, $result);
        $this->assertSame($isSuccess, $this->pos->isSuccess());
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

    public function testCustomQueryRequest(): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->pos->customQuery([]);
    }

    public static function make3DPaymentDataProvider(): array
    {
        return [
            'auth_fail'                    => [
                'order'           => KuveytPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_fail']['order'],
                'txType'          => KuveytPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_fail']['txType'],
                'request'         => ['AuthenticationResponse' => '%3C%3Fxml+version%3D%221.0%22%3F%3E%0A%3CVPosTransaction+xmlns%3Axsi%3D%22http%3A%2F%2Fwww.w3.org%2F2001%2FXMLSchema-instance%22+xmlns%3Axsd%3D%22http%3A%2F%2Fwww.w3.org%2F2001%2FXMLSchema%22%3E%3CIsEnrolled%3Etrue%3C%2FIsEnrolled%3E%3CIsVirtual%3Efalse%3C%2FIsVirtual%3E%3CResponseCode%3EHashDataError%3C%2FResponseCode%3E%3CResponseMessage%3E%26%23x15E%3Bifrelenen+veriler+%28Hashdata%29+uyu%26%23x15F%3Bmamaktad%26%23x131%3Br.%3C%2FResponseMessage%3E%3COrderId%3E0%3C%2FOrderId%3E%3CTransactionTime%3E0001-01-01T00%3A00%3A00%3C%2FTransactionTime%3E%3CMerchantOrderId%3E2020110828BC%3C%2FMerchantOrderId%3E%3CReferenceId%3E9b8e2326a9df44c2b2aac0b98b11f0a4%3C%2FReferenceId%3E%3CBusinessKey%3E0%3C%2FBusinessKey%3E%3C%2FVPosTransaction%3E%0A'],
                'decodedRequest'  => KuveytPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_fail']['threeDResponseData'],
                'paymentResponse' => KuveytPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_fail']['paymentData'],
                'expected'        => KuveytPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_fail']['expectedData'],
                'is3DSuccess'     => false,
                'isSuccess'       => false,
            ],
            '3d_auth_success_payment_fail' => [
                'order'           => KuveytPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_success_payment_fail_1']['order'],
                'txType'          => KuveytPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_success_payment_fail_1']['txType'],
                'request'         => ['AuthenticationResponse' => '%3C%3Fxml+version%3D%221.0%22%3F%3E%0A%3CVPosTransaction+xmlns%3Axsi%3D%22http%3A%2F%2Fwww.w3.org%2F2001%2FXMLSchema-instance%22+xmlns%3Axsd%3D%22http%3A%2F%2Fwww.w3.org%2F2001%2FXMLSchema%22%3E%3CVPosMessage%3E%3COrderId%3E86483278%3C%2FOrderId%3E%3COkUrl%3Ehttps%3A%2F%2Fwww.example.com%2Ftestodeme%3C%2FOkUrl%3E%3CFailUrl%3Ehttps%3A%2F%2Fwww.example.com%2Ftestodeme%3C%2FFailUrl%3E%3CMerchantId%3E48544%3C%2FMerchantId%3E%3CSubMerchantId%3E0%3C%2FSubMerchantId%3E%3CCustomerId%3E123456%3C%2FCustomerId%3E%3CUserName%3Efapapi%3C%2FUserName%3E%3CHashPassword%3EHiorgg24rNeRdHUvMCg%2F%2FmOJn4U%3D%3C%2FHashPassword%3E%3CCardNumber%3E5124%2A%2A%2A%2A%2A%2A%2A%2A1609%3C%2FCardNumber%3E%3CBatchID%3E1576%3C%2FBatchID%3E%3CInstallmentCount%3E0%3C%2FInstallmentCount%3E%3CAmount%3E10%3C%2FAmount%3E%3CCancelAmount%3E0%3C%2FCancelAmount%3E%3CMerchantOrderId%3EMP-15%3C%2FMerchantOrderId%3E%3CFECAmount%3E0%3C%2FFECAmount%3E%3CCurrencyCode%3E949%3C%2FCurrencyCode%3E%3CQeryId%3E0%3C%2FQeryId%3E%3CDebtId%3E0%3C%2FDebtId%3E%3CSurchargeAmount%3E0%3C%2FSurchargeAmount%3E%3CSGKDebtAmount%3E0%3C%2FSGKDebtAmount%3E%3CTransactionSecurity%3E3%3C%2FTransactionSecurity%3E%3CDeferringCount+xsi%3Anil%3D%22true%22%3E%3C%2FDeferringCount%3E%3CInstallmentMaturityCommisionFlag%3E0%3C%2FInstallmentMaturityCommisionFlag%3E%3CPaymentId+xsi%3Anil%3D%22true%22%3E%3C%2FPaymentId%3E%3COrderPOSTransactionId+xsi%3Anil%3D%22true%22%3E%3C%2FOrderPOSTransactionId%3E%3CTranDate+xsi%3Anil%3D%22true%22%3E%3C%2FTranDate%3E%3CTransactionUserId+xsi%3Anil%3D%22true%22%3E%3C%2FTransactionUserId%3E%3C%2FVPosMessage%3E%3CIsEnrolled%3Etrue%3C%2FIsEnrolled%3E%3CIsVirtual%3Efalse%3C%2FIsVirtual%3E%3CResponseCode%3E00%3C%2FResponseCode%3E%3CResponseMessage%3EKart+do%26%23x11F%3Bruland%26%23x131%3B.%3C%2FResponseMessage%3E%3COrderId%3E86483278%3C%2FOrderId%3E%3CTransactionTime%3E0001-01-01T00%3A00%3A00%3C%2FTransactionTime%3E%3CMerchantOrderId%3EMP-15%3C%2FMerchantOrderId%3E%3CHashData%3EmOw0JGvy1JVWqDDmFyaDTvKz9Fk%3D%3C%2FHashData%3E%3CMD%3EktSVkYJHcHSYM1ibA%2FnM6nObr8WpWdcw34ziyRQRLv06g7UR2r5LrpLeNvwfBwPz%3C%2FMD%3E%3CBusinessKey%3E202208456498416947%3C%2FBusinessKey%3E%3C%2FVPosTransaction%3E%0A'],
                'decodedRequest'  => KuveytPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_success_payment_fail_1']['threeDResponseData'],
                'paymentResponse' => KuveytPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_success_payment_fail_1']['paymentData'],
                'expected'        => KuveytPosResponseDataMapperTest::threeDPaymentDataProvider()['3d_auth_success_payment_fail_1']['expectedData'],
                'is3DSuccess'     => true,
                'isSuccess'       => false,
            ],
            'success'                      => [
                'order'           => KuveytPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['order'],
                'txType'          => KuveytPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['txType'],
                'request'         => ['AuthenticationResponse' => '%3C%3Fxml+version%3D%221.0%22%3F%3E%0A%3CVPosTransaction%3E%3CVPosMessage%3E%3CAPIVersion%3E1.0.0%3C%2FAPIVersion%3E%3COkUrl%3Ehttp%3A%2F%2Flocalhost%3A44785%2FHome%2FSuccess%3C%2FOkUrl%3E%3CFailUrl%3Ehttp%3A%2F%2Flocalhost%3A44785%2FHome%2FFail%3C%2FFailUrl%3E%3CHashData%3ElYJYMi%2FgVO9MWr32Pshaa%2FzAbSHY%3D%3C%2FHashData%3E%3CMerchantId%3E80%3C%2FMerchantId%3E%3CSubMerchantId%3E0%3C%2FSubMerchantId%3E%3CCustomerId%3E400235%3C%2FCustomerId%3E%3CUserName%3Eapiuser%3C%2FUserName%3E%3CCardNumber%3E5124%2A%2A%2A%2A%2A%2A%2A%2A1609%3C%2FCardNumber%3E%3CCardHolderName%3Eafafa%3C%2FCardHolderName%3E%3CCardType%3EMasterCard%3C%2FCardType%3E%3CBatchID%3E0%3C%2FBatchID%3E%3CTransactionType%3ESale%3C%2FTransactionType%3E%3CInstallmentCount%3E0%3C%2FInstallmentCount%3E%3CAmount%3E100%3C%2FAmount%3E%3CDisplayAmount%3E100%3C%2FDisplayAmount%3E%3CMerchantOrderId%3EOrder+123%3C%2FMerchantOrderId%3E%3CFECAmount%3E0%3C%2FFECAmount%3E%3CCurrencyCode%3E0949%3C%2FCurrencyCode%3E%3CQeryId%3E0%3C%2FQeryId%3E%3CDebtId%3E0%3C%2FDebtId%3E%3CSurchargeAmount%3E0%3C%2FSurchargeAmount%3E%3CSGKDebtAmount%3E0%3C%2FSGKDebtAmount%3E%3CTransactionSecurity%3E3%3C%2FTransactionSecurity%3E%3CTransactionSide%3EAuto%3C%2FTransactionSide%3E%3CEntryGateMethod%3EVPOS_ThreeDModelPayGate%3C%2FEntryGateMethod%3E%3C%2FVPosMessage%3E%3CIsEnrolled%3Etrue%3C%2FIsEnrolled%3E%3CIsVirtual%3Efalse%3C%2FIsVirtual%3E%3COrderId%3E0%3C%2FOrderId%3E%3CTransactionTime%3E0001-01-01T00%3A00%3A00%3C%2FTransactionTime%3E%3CResponseCode%3E00%3C%2FResponseCode%3E%3CResponseMessage%3EHATATA%3C%2FResponseMessage%3E%3CMD%3E67YtBfBRTZ0XBKnAHi8c%2FA%3D%3D%3C%2FMD%3E%3CAuthenticationPacket%3EWYGDgSIrSHDtYwF%2FWEN%2BnfwX63sppA%3D%3C%2FAuthenticationPacket%3E%3CACSURL%3Ehttps%3A%2F%2Facs.bkm.com.tr%2Fmdpayacs%2Fpareq%3C%2FACSURL%3E%3C%2FVPosTransaction%3E%0A'],
                'decodedRequest'  => KuveytPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['threeDResponseData'],
                'paymentResponse' => KuveytPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['paymentData'],
                'expected'        => KuveytPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['expectedData'],
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
                'expectedExceptionClass' => \LogicException::class,
                'expectedExceptionMsg'   => 'Bu ödeme modeli için kart bilgileri zorunlu!',
            ],
            'unsupported_payment_model' => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_HOST,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => false,
                'create_without_card'    => true,
                'expectedExceptionClass' => \LogicException::class,
                'expectedExceptionMsg'   => 'Mews\Pos\Gateways\KuveytPos ödeme altyapıda [pay] işlem tipi [regular, 3d] ödeme model(ler) desteklemektedir. Sağlanan ödeme model: [3d_host].',
            ],
            'unsupported_tx'            => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_SECURE,
                'txType'                 => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                'isWithCard'             => false,
                'create_without_card'    => true,
                'expectedExceptionClass' => \LogicException::class,
                'expectedExceptionMsg'   => 'Hatalı işlem tipi! Desteklenen işlem tipleri: [pay]',
            ],
            'non_payment_tx_type'       => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_SECURE,
                'txType'                 => PosInterface::TX_TYPE_STATUS,
                'isWithCard'             => false,
                'create_with_card'       => false,
                'expectedExceptionClass' => \LogicException::class,
                'expectedExceptionMsg'   => 'Hatalı işlem tipi! Desteklenen işlem tipleri: [pay]',
            ],
            'post_auth_tx_type'         => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_PAY,
                'txType'                 => PosInterface::TX_TYPE_PAY_POST_AUTH,
                'isWithCard'             => true,
                'create_with_card'       => false,
                'expectedExceptionClass' => \LogicException::class,
                'expectedExceptionMsg'   => 'Hatalı işlem tipi! Desteklenen işlem tipleri: [pay]',
            ],
            'unsupported_form_format'   => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_SECURE,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => true,
                'create_without_card'    => false,
                'expectedExceptionClass' => UnsupportedFormFormatException::class,
                'expectedExceptionMsg'   => 'Unsupported 3D form format!',
                'formFormat'             => PosInterface::FORM_FORMAT_ARRAY,
            ],
        ];
    }

    public static function statusDataProvider(): iterable
    {
        $testData = iterator_to_array(KuveytPosResponseDataMapperTest::statusTestDataProvider());
        yield [
            'bank_response' => $testData['fail1']['responseData'],
            'expected_data' => $testData['fail1']['expectedData'],
            'isSuccess'     => false,
        ];
        yield [
            'bank_response' => $testData['success1']['responseData'],
            'expected_data' => $testData['success1']['expectedData'],
            'isSuccess'     => true,
        ];
    }

    public static function cancelDataProvider(): array
    {
        $testData = iterator_to_array(KuveytPosResponseDataMapperTest::cancelTestDataProvider());

        return [
            'fail_1'    => [
                'bank_response' => $testData['fail1']['responseData'],
                'expected_data' => $testData['fail1']['expectedData'],
                'isSuccess'     => false,
            ],
            'success_1' => [
                'bank_response' => $testData['success1']['responseData'],
                'expected_data' => $testData['success1']['expectedData'],
                'isSuccess'     => true,
            ],
        ];
    }

    public static function refundDataProvider(): array
    {
        $testData = iterator_to_array(KuveytPosResponseDataMapperTest::refundTestDataProvider());

        return [
            'fail_1'    => [
                'bank_response' => $testData['fail1']['responseData'],
                'expected_data' => $testData['fail1']['expectedData'],
                'isSuccess'     => false,
            ],
            'success_1' => [
                'bank_response' => $testData['success1']['responseData'],
                'expected_data' => $testData['success1']['expectedData'],
                'isSuccess'     => true,
            ],
        ];
    }

    private function configureClientResponse(
        string  $txType,
        array   $requestData,
        $decodedResponse,
        array   $order,
        string  $paymentModel,
        ?string $clientTxType = null
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
                $this->callback(function (array $requestData) {
                    return $requestData['test-update-request-data-with-event'] === true;
                }),
                $order
            )->willReturn($decodedResponse);

        $this->eventDispatcherMock->expects(self::once())
            ->method('dispatch')
            ->with($this->logicalAnd(
                $this->isInstanceOf(RequestDataPreparedEvent::class),
                $this->callback(
                    function (RequestDataPreparedEvent $dispatchedEvent) use ($requestData, $txType, $order, $paymentModel, &$updatedRequestDataPreparedEvent): bool {
                        $updatedRequestDataPreparedEvent = $dispatchedEvent;

                        return get_class($this->pos) === $dispatchedEvent->getGatewayClass()
                            && $txType === $dispatchedEvent->getTxType()
                            && $requestData === $dispatchedEvent->getRequestData()
                            && $order === $dispatchedEvent->getOrder()
                            && $paymentModel === $dispatchedEvent->getPaymentModel();
                    }
                )
            ))
            ->willReturnCallback(function () use (&$updatedRequestDataPreparedEvent): ?\Mews\Pos\Event\RequestDataPreparedEvent {
                $updatedRequestData                                        = $updatedRequestDataPreparedEvent->getRequestData();
                $updatedRequestData['test-update-request-data-with-event'] = true;
                $updatedRequestDataPreparedEvent->setRequestData($updatedRequestData);

                return $updatedRequestDataPreparedEvent;
            });
    }
}
