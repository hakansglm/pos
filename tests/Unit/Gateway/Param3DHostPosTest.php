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
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\Request\Mapper\Param3DHostPosRequestDataMapper;
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
use Mews\Pos\Gateway\AbstractGateway;
use Mews\Pos\Gateway\Param3DHostPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

#[CoversClass(Param3DHostPos::class)]
#[CoversClass(AbstractGateway::class)]
class Param3DHostPosTest extends TestCase
{
    private ParamPosAccount $account;

    private array $config;

    /** @var Param3DHostPos */
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'name'              => 'param-pos',
            'class'             => Param3DHostPos::class,
            'gateway_endpoints' => [
                'gateway_3d_host' => 'https://test-pos.param.com.tr/default.aspx',
            ],
        ];

        $this->account = AccountFactory::createParamPosAccount(
            'param-3d-host-pos',
            10738,
            'Test',
            'Test',
            '0c13d406-873b-403b-9c09-a5766840d98c'
        );

        $this->requestValueMapperMock = $this->createMock(ParamPosRequestValueMapper::class);
        $this->requestMapperMock      = $this->createMock(Param3DHostPosRequestDataMapper::class);
        $this->responseMapperMock     = $this->createMock(ResponseDataMapperInterface::class);
        $this->cryptMock              = $this->createMock(CryptInterface::class);
        $this->httpClientStrategyMock = $this->createMock(HttpClientStrategyInterface::class);
        $this->httpClientMock         = $this->createMock(HttpClientInterface::class);
        $this->loggerMock             = $this->createMock(LoggerInterface::class);
        $this->eventDispatcherMock    = $this->createMock(EventDispatcherInterface::class);

        $this->pos = $this->createGateway($this->config);
    }

    /**
     * @return void
     */
    public function testInit(): void
    {
        $this->requestValueMapperMock->expects(self::once())
            ->method('getCurrencyMappings')
            ->willReturn([PosInterface::CURRENCY_TRY => '1000']);
        $this->assertSame($this->config, $this->pos->getConfig());
        $this->assertSame($this->account, $this->pos->getAccount());
        $this->assertSame([PosInterface::CURRENCY_TRY], $this->pos->getCurrencies());
        $this->assertFalse($this->pos->isTestMode());
        $this->assertSame($this->cryptMock, $this->pos->getCrypt());
    }

    #[DataProvider('threeDFormDataProvider')]
    public function testGet3DFormData(
        array   $order,
        string  $txType,
        array   $requestData,
        ?string $gatewayUrl,
        array   $decodedResponseData,
        array   $formData
    ): void {
        $paymentModel = PosInterface::MODEL_3D_HOST;
        $this->requestMapperMock->expects(self::once())
            ->method('create3DFormInitializeRequestData')
            ->with($this->pos->getAccount(), $order)
            ->willReturn($requestData);

        $this->configureClientResponse(
            $txType,
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

        $actual = $this->pos->get3DFormData($order, $paymentModel, $txType);

        $this->assertSame($actual, $formData);
    }

    #[DataProvider('threeDFormDataBadInputsProvider')]
    public function testGet3DFormDataWithBadInputs(
        array   $order,
        string  $paymentModel,
        string  $txType,
        bool    $isWithCard,
        string  $expectedExceptionClass,
        string  $expectedExceptionMsg,
        ?string $formFormat = null
    ): void {
        $card = $isWithCard ? $this->createMock(CreditCardInterface::class) : null;
        $this->expectException($expectedExceptionClass);
        $this->expectExceptionMessage($expectedExceptionMsg);

        $this->pos->get3DFormData($order, $paymentModel, $txType, $card, false, $formFormat);
    }

    public function testMake3DPayment(): void
    {
        $txType = PosInterface::TX_TYPE_PAY_AUTH;

        $this->expectException(UnsupportedPaymentModelException::class);
        $this->pos->payment(PosInterface::MODEL_3D_SECURE, [], $txType, null, ['abc']);
    }

    public function testMake3DPayPayment(): void
    {
        $txType = PosInterface::TX_TYPE_PAY_AUTH;

        $this->expectException(UnsupportedPaymentModelException::class);
        $this->pos->payment(PosInterface::MODEL_3D_PAY, [], $txType, null, ['abc']);
    }

    public function testMake3DHostPaymentHashMismatchException(): void
    {
        $txType              = PosInterface::TX_TYPE_PAY_AUTH;
        $gatewayResponseData = [
            'TURKPOS_RETVAL_Islem_GUID'        => '77f11031-cce8-4131-bf95-142303732608',
            'TURKPOS_RETVAL_SanalPOS_Islem_ID' => '6021847062',
        ];

        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->with($this->account, $gatewayResponseData)
            ->willReturn(false);

        $this->expectException(HashMismatchException::class);

        $this->pos->payment(PosInterface::MODEL_3D_HOST, [], $txType, null, $gatewayResponseData);
    }

    public function testMake3DHostPayment(): void
    {
        $gatewayResponseData = [
            'TURKPOS_RETVAL_Hash'              => 'LOpkL9J8vne8E2j0A0HKOhUWGhI=',
            'TURKPOS_RETVAL_Islem_GUID'        => '77f11031-cce8-4131-bf95-142303732608',
            'TURKPOS_RETVAL_SanalPOS_Islem_ID' => '6021847062',
        ];

        $this->cryptMock->expects(self::once())
            ->method('check3DHash')
            ->with($this->account, $gatewayResponseData)
            ->willReturn(true);

        $order  = ['id' => '123'];
        $txType = PosInterface::TX_TYPE_PAY_AUTH;

        $this->responseMapperMock->expects(self::once())
            ->method('map3DHostResponseData')
            ->with($gatewayResponseData, $txType, $order)
            ->willReturn(['status' => 'approved']);

        $pos = $this->pos;

        $result = $pos->payment(PosInterface::MODEL_3D_HOST, $order, $txType, null, $gatewayResponseData);

        $this->assertSame(['status' => 'approved'], $result);
        $this->assertTrue($pos->isSuccess());
    }

    /**
     * @return void
     */
    public function testMake3DHostPaymentWithoutHashCheck(): void
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

        $gatewayResponseData = [
            'TURKPOS_RETVAL_Hash'              => 'LOpkL9J8vne8E2j0A0HKOhUWGhI=',
            'TURKPOS_RETVAL_Islem_GUID'        => '77f11031-cce8-4131-bf95-142303732608',
            'TURKPOS_RETVAL_SanalPOS_Islem_ID' => '6021847062',
        ];

        $order  = ['id' => '123'];
        $txType = PosInterface::TX_TYPE_PAY_AUTH;

        $this->responseMapperMock->expects(self::once())
            ->method('map3DHostResponseData')
            ->with($gatewayResponseData, $txType, $order)
            ->willReturn(['status' => 'approved']);

        $result = $pos->payment(PosInterface::MODEL_3D_HOST, $order, $txType, null, $gatewayResponseData);

        $this->assertSame(['status' => 'approved'], $result);
        $this->assertTrue($pos->isSuccess());
    }

    public function testMakeRegularPayment(): void
    {
        $txType = PosInterface::TX_TYPE_PAY_AUTH;
        $this->expectException(UnsupportedPaymentModelException::class);

        $this->pos->payment(PosInterface::MODEL_NON_SECURE, [], $txType, $this->createMock(CreditCardInterface::class), ['abc']);
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

    public function testCustomQueryRequest(): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->pos->customQuery([]);
    }

    public static function threeDFormDataBadInputsProvider(): array
    {
        return [
            'unsupported_payment_model' => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_SECURE,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => true,
                'expectedExceptionClass' => LogicException::class,
                'expectedExceptionMsg'   => 'Mews\Pos\Gateway\Param3DHostPos ödeme altyapıda [pay] işlem tipi [3d_host] ödeme model(ler) desteklemektedir. Sağlanan ödeme model: [3d].',
            ],
            'unsupported_form_format'   => [
                'order'                  => ['id' => '2020110828BC'],
                'paymentModel'           => PosInterface::MODEL_3D_HOST,
                'txType'                 => PosInterface::TX_TYPE_PAY_AUTH,
                'isWithCard'             => false,
                'expectedExceptionClass' => UnsupportedFormFormatException::class,
                'expectedExceptionMsg'   => 'Unsupported 3D form format!',
                'formFormat'             => PosInterface::FORM_FORMAT_HTML,
            ],
        ];
    }

    public static function threeDFormDataProvider(): iterable
    {
        yield '3d_host' => [
            'order'               => [],
            'txType'              => PosInterface::TX_TYPE_PAY_AUTH,
            'requestData'         => ['request-data'],
            'gateway_url'         => 'https://test-pos.param.com.tr/default.aspx',
            'decodedResponseData' => [
                'TO_Pre_Encrypting_OOSResponse' => [
                    'TO_Pre_Encrypting_OOSResult' => 'JHnDLmT5yierHIqsHNRU2SR7HLxOpi8o7Eb/oVSiIf35v+Z1uzteqid4wop8SAuykWNFElYyAxGWcIGvTxmhSljuLTcJ3xDMkS3O0jUboNpl5ad6roy/92lDftpV535KmpbxMxStRa+qGT7Tk4BdEIf+Jobr2o1Yl1+ZakWZ+parsTgnodyWl432Hsv2FUNLhuU7H6folMwleaZFPYdFZ+bO1T95opw5pnDWcFkrIuPfAmVRg4cg+al22FQSN/58AXxWBb8jEPrqn+/ojZ+WqncGvw+NB/Mtv9iCDuF+SNQqRig2dRILzWYwcvNxzj/OxcYuNuvO8wYI/iF1kNBBNtaExIunWZyj1tntGeb7UUaDmHD4LmSMUMpgZGugRfUpxm8WL/EE+PnUkLXE7SOG3g==',
                ],
            ],
            'formData'            => [
                'gateway' => 'https://test-pos.param.com.tr/to.ws/Service_Odeme.asmx',
                'method'  => 'GET',
                'inputs'  => [
                    's' => 'JHnDLmT5yierHIqsHNRU2SR7HLxOpi8o7Eb/oVSiIf35v+Z1uzteqid4wop8SAuykWNFElYyAxGWcIGvTxmhSljuLTcJ3xDMkS3O0jUboNpl5ad6roy/92lDftpV535KmpbxMxStRa+qGT7Tk4BdEIf+Jobr2o1Yl1+ZakWZ+parsTgnodyWl432Hsv2FUNLhuU7H6folMwleaZFPYdFZ+bO1T95opw5pnDWcFkrIuPfAmVRg4cg+al22FQSN/58AXxWBb8jEPrqn+/ojZ+WqncGvw+NB/Mtv9iCDuF+SNQqRig2dRILzWYwcvNxzj/OxcYuNuvO8wYI/iF1kNBBNtaExIunWZyj1tntGeb7UUaDmHD4LmSMUMpgZGugRfUpxm8WL/EE+PnUkLXE7SOG3g==',
                ],
            ],
        ];
    }

    private function createGateway(array $config, ?AbstractPosAccount $account = null): PosInterface
    {
        return new Param3DHostPos(
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
