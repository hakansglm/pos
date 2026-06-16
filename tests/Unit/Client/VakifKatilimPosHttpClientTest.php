<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Client;

use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Client\VakifKatilimPosHttpClient;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestValueMapper\RequestValueMapperInterface;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\PosHttpClientFactory;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\Gateways\VakifKatilimPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * @covers \Mews\Pos\Client\VakifKatilimPosHttpClient
 * @covers \Mews\Pos\Client\AbstractHttpClient
 */
class VakifKatilimPosHttpClientTest extends TestCase
{
    use HttpClientTestTrait;

    private VakifKatilimPosHttpClient $client;

    /** @var LoggerInterface & MockObject */
    private LoggerInterface $logger;

    /** @var RequestValueMapperInterface & MockObject */
    private RequestValueMapperInterface $requestValueMapper;
    /**
     * @var ClientInterface& MockObject
     */
    private ClientInterface $psrClient;
    /**
     * @var RequestFactoryInterface& MockObject
     */
    private RequestFactoryInterface $requestFactory;
    /**
     * @var StreamFactoryInterface & MockObject
     */
    private StreamFactoryInterface $streamFactory;

    protected function setUp(): void
    {
        $this->logger             = $this->createMock(LoggerInterface::class);
        $crypt                    = $this->createMock(CryptInterface::class);
        $this->requestValueMapper = $this->createMock(RequestValueMapperInterface::class);
        $this->psrClient          = $this->createMock(ClientInterface::class);
        $this->requestFactory     = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory      = $this->createMock(StreamFactoryInterface::class);

        $this->client = PosHttpClientFactory::create(
            VakifKatilimPosHttpClient::class,
            'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home',
            $crypt,
            $this->requestValueMapper,
            $this->logger,
            $this->psrClient,
            $this->requestFactory,
            $this->streamFactory
        );
    }

    public function testSupports(): void
    {
        $this->assertTrue($this->client::supports(VakifKatilimPos::class, HttpClientInterface::API_NAME_PAYMENT_API));
        $this->assertTrue($this->client::supports(VakifKatilimPos::class, HttpClientInterface::API_NAME_GATEWAY_3D_API));
        $this->assertFalse($this->client::supports(AkbankPos::class, HttpClientInterface::API_NAME_PAYMENT_API));
    }

    /**
     * @dataProvider supportsTxDataProvider
     */
    public function testSupportsTx(string $txType, string $paymentModel, bool $expected): void
    {
        $this->assertSame($expected, $this->client->supportsTx($txType, $paymentModel));
    }

    public static function supportsTxDataProvider(): array
    {
        return [
            'pay_auth_3d_secure'      => [PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_3D_SECURE, true],
            '3d_form_build_3d_secure' => [PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD, PosInterface::MODEL_3D_SECURE, true],
            'unsupported'             => ['unsupported', PosInterface::MODEL_3D_SECURE, false],
        ];
    }

    /**
     * @dataProvider getApiUrlDataProvider
     */
    public function testGetApiUrl(string $txType, ?string $orderTxType, string $paymentModel, string $expected): void
    {
        $actual = $this->client->getApiURL($txType, $paymentModel, $orderTxType);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider getApiUrlDataFailProvider
     */
    public function testGetApiUrlUnsupportedTxType(
        ?string $txType,
        ?string $paymentModel,
        ?string $orderTxType,
        string $expectedException
    ): void {
        $this->expectException($expectedException);
        $this->client->getApiURL($txType, $paymentModel, $orderTxType);
    }

    public function testRequestFor3DFormBuild(): void
    {
        $txType       = PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD;
        $paymentModel = PosInterface::MODEL_3D_SECURE;
        $requestData  = ['request-data'];
        $order        = ['id' => 123];
        $apiUrl       = 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/ThreeDModelPayGate';

        $encodedBody = '<?xml version="1.0" encoding="ISO-8859-1"?>
<VPosMessageContract><item key="0">request-data</item></VPosMessageContract>
';
        $request     = $this->prepareHttpRequest($encodedBody, [
            [
                'name'  => 'Content-Type',
                'value' => 'text/xml; charset=UTF-8',
            ],
        ]);

        $responseContent = '<html>3d-form</html>';
        $response        = $this->prepareHttpResponse($responseContent, 200);

        $this->requestFactory->expects($this->once())
            ->method('createRequest')
            ->with('POST', $apiUrl)
            ->willReturn($request);

        $this->psrClient->expects($this->once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response);

        $actual = $this->client->request($txType, $paymentModel, $requestData, $order, $apiUrl);

        $this->assertSame($responseContent, $actual);
    }

    /**
     * @dataProvider requestDataProvider
     */
    public function testRequest(
        string $txType,
        string $paymentModel,
        array  $requestData,
        string $encodedRequestData,
        array  $order,
        string $expectedApiUrl
    ): void {
        $request     = $this->prepareHttpRequest($encodedRequestData, [
            [
                'name'  => 'Content-Type',
                'value' => 'text/xml; charset=UTF-8',
            ],
        ]);

        $responseContent = '<response><result>success</result></response>';
        $response        = $this->prepareHttpResponse($responseContent, 200);

        $this->requestFactory->expects($this->once())
            ->method('createRequest')
            ->with('POST', $expectedApiUrl)
            ->willReturn($request);

        $this->psrClient->expects($this->once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response);

        $decodedResponse = ['result' => 'success'];

        $actual = $this->client->request(
            $txType,
            $paymentModel,
            $requestData,
            $order,
            $expectedApiUrl
        );

        $this->assertSame(['result' => 'success'], $actual);
    }

    public function testRequestBadRequest(): void
    {
        $txType         = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel   = PosInterface::MODEL_3D_SECURE;
        $requestData    = ['request-data' => 'abc'];
        $order          = ['id' => 123];

        $encodedBody = '<?xml version="1.0" encoding="ISO-8859-1"?>
<VPosMessageContract><request-data>abc</request-data></VPosMessageContract>
';
        $request = $this->prepareHttpRequest($encodedBody, [
            [
                'name'  => 'Content-Type',
                'value' => 'text/xml; charset=UTF-8',
            ],
        ]);


        $responseContent = 'response-content';
        $response        = $this->prepareHttpResponse($responseContent, 500);


        $this->requestFactory->expects($this->once())
            ->method('createRequest')
            ->willReturn($request);

        $this->psrClient->expects($this->once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('İstek Başarısız!');

        $this->client->request(
            $txType,
            $paymentModel,
            $requestData,
            $order,
        );
    }

    public function testRequestUndecodableResponse(): void
    {
        $txType         = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel   = PosInterface::MODEL_3D_SECURE;
        $requestData    = ['request-data' => 'abc'];
        $order          = ['id' => 123];

        $encodedBody = '<?xml version="1.0" encoding="ISO-8859-1"?>
<VPosMessageContract><request-data>abc</request-data></VPosMessageContract>
';
        $request     = $this->prepareHttpRequest($encodedBody, [
            [
                'name'  => 'Content-Type',
                'value' => 'text/xml; charset=UTF-8',
            ],
        ]);
        $responseContent = 'not-valid-xml';
        $response        = $this->prepareHttpResponse($responseContent, 400);

        $this->requestFactory->expects($this->once())
            ->method('createRequest')
            ->willReturn($request);

        $this->psrClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($response);

        $this->expectException(\RuntimeException::class);
        $this->client->request(
            $txType,
            $paymentModel,
            $requestData,
            $order
        );
    }

    public function testRequestApiUrlNotFound(): void
    {
        $this->psrClient->expects($this->never())
            ->method('sendRequest');

        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->client->request(
            PosInterface::TX_TYPE_PAY_PRE_AUTH,
            PosInterface::MODEL_3D_SECURE,
            ['request-data'],
            ['id' => 123]
        );
    }

    public static function requestDataProvider(): \Generator
    {
        yield [
            'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
            'paymentModel'       => PosInterface::MODEL_3D_SECURE,
            'requestData'        => ['request-data'],
            'encodedRequestData' => '<?xml version="1.0" encoding="ISO-8859-1"?>
<VPosMessageContract><item key="0">request-data</item></VPosMessageContract>
',
            'order'              => ['id' => 123],
            'expectedApiUrl'     => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/ThreeDModelProvisionGate',
        ];
    }

    public static function getApiUrlDataProvider(): array
    {
        return [
            [
                'txType'       => PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD,
                'orderTxType'  => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'expected'     => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/ThreeDModelPayGate',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'orderTxType'  => null,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'expected'     => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/ThreeDModelProvisionGate',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                'orderTxType'  => null,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/PreAuthorizaten',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'orderTxType'  => null,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/Non3DPayGate',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_PAY_POST_AUTH,
                'orderTxType'  => null,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/PreAuthorizatenClose',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_STATUS,
                'orderTxType'  => null,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/SelectOrderByMerchantOrderId',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_ORDER_HISTORY,
                'orderTxType'  => null,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/SelectOrder',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_CANCEL,
                'orderTxType'  => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/SaleReversal',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_REFUND,
                'orderTxType'  => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/DrawBack',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_REFUND_PARTIAL,
                'orderTxType'  => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/PartialDrawBack',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_CANCEL,
                'orderTxType'  => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/PreAuthorizationReversal',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_REFUND,
                'orderTxType'  => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/PreAuthorizationDrawBack',
            ],
        ];
    }

    public static function getApiUrlDataFailProvider(): array
    {
        return [
            [
                'txType'          => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel'    => PosInterface::MODEL_3D_PAY,
                'orderTxType'     => null,
                'exception_class' => UnsupportedTransactionTypeException::class,
            ],
            [
                'txType'          => null,
                'paymentModel'    => null,
                'orderTxType'     => null,
                'exception_class' => \InvalidArgumentException::class,
            ],
            [
                'txType'          => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel'    => null,
                'orderTxType'     => null,
                'exception_class' => \InvalidArgumentException::class,
            ],
            [
                'txType'          => null,
                'paymentModel'    => PosInterface::MODEL_3D_PAY,
                'orderTxType'     => null,
                'exception_class' => \InvalidArgumentException::class,
            ],
            [
                'txType'          => 'abc',
                'paymentModel'    => PosInterface::MODEL_3D_PAY,
                'orderTxType'     => null,
                'exception_class' => UnsupportedTransactionTypeException::class,
            ],
            [
                'txType'          => PosInterface::TX_TYPE_CANCEL,
                'paymentModel'    => PosInterface::MODEL_NON_SECURE,
                'orderTxType'     => PosInterface::TX_TYPE_PAY_POST_AUTH,
                'exception_class' => UnsupportedTransactionTypeException::class,
            ],
        ];
    }
}
