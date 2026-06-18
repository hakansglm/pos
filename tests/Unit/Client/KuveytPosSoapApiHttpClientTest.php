<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Client;

use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Client\KuveytPosSoapApiHttpClient;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestValueMapper\RequestValueMapperInterface;
use Mews\Pos\Factory\PosHttpClientFactory;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * @covers \Mews\Pos\Client\KuveytPosSoapApiHttpClient
 * @covers \Mews\Pos\Client\AbstractHttpClient
 */
class KuveytPosSoapApiHttpClientTest extends TestCase
{
    use HttpClientTestTrait;

    private KuveytPosSoapApiHttpClient $client;

    /** @var RequestValueMapperInterface&MockObject */
    private MockObject $requestValueMapper;

    /** @var StreamFactoryInterface&MockObject */
    private MockObject $streamFactory;

    /** @var LoggerInterface&MockObject */
    private MockObject $logger;

    /** @var KuveytPosSoapApiHttpClient&MockObject */
    private MockObject $psrClient;

    /** @var RequestFactoryInterface&MockObject */
    private MockObject $requestFactory;

    protected function setUp(): void
    {
        $this->logger             = $this->createMock(LoggerInterface::class);
        $crypt                    = $this->createMock(CryptInterface::class);
        $this->requestValueMapper = $this->createMock(RequestValueMapperInterface::class);
        $this->psrClient          = $this->createMock(ClientInterface::class);
        $this->requestFactory     = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory      = $this->createMock(StreamFactoryInterface::class);


        $this->client = PosHttpClientFactory::create(
            KuveytPosSoapApiHttpClient::class,
            'https://boatest.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc/Basic',
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
        $this->assertFalse($this->client::supports(AkbankPos::class, HttpClientInterface::API_NAME_PAYMENT_API));
    }

    public function testSupportsTx(): void
    {
        $this->assertTrue($this->client->supportsTx(PosInterface::TX_TYPE_STATUS, PosInterface::MODEL_NON_SECURE));
        $this->assertFalse($this->client->supportsTx(PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_NON_SECURE));
    }

    /**
     * @dataProvider getApiUrlDataProvider
     */
    public function testGetApiUrl(string $txType, string $paymentModel, string $expected): void
    {
        $actual = $this->client->getApiURL($txType, $paymentModel);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider requestDataProvider
     */
    public function testRequestCreatesCorrectSoapRequest(
        string $txType,
        string $paymentModel,
        array  $requestData,
        array  $order,
        string $expectedApiUrl
    ): void {
        $requestData    = ['foo' => 'bar'];
        $order          = ['id' => 123];
        $encodedBody    = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ser="http://boa.net/BOA.Integration.VirtualPos/Service"><soapenv:Body><ser:foo>bar</ser:foo></soapenv:Body></soapenv:Envelope>';

        $request  = $this->prepareHttpRequest($encodedBody, [
            [
                'name'  => 'Content-Type',
                'value' => 'text/xml; charset=UTF-8',
            ],
            [
                'name'  => 'SOAPAction',
                'value' => 'http://boa.net/BOA.Integration.VirtualPos/Service/IVirtualPosService/CancelV4',
            ],
        ]);

        $responseXml = '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body><result>ok</result></s:Body></s:Envelope>';
        $response    = $this->prepareHttpResponse($responseXml, 200);

        $this->requestValueMapper->expects($this->once())
            ->method('mapTxType')
            ->with($txType)
            ->willReturn('CancelV4');

        $this->requestFactory->expects($this->once())
            ->method('createRequest')
            ->with('POST', $expectedApiUrl)
            ->willReturn($request);

        $this->psrClient->expects($this->once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response);

        $actual = $this->client->request($txType, $paymentModel, $requestData, $order, $expectedApiUrl);

        $this->assertSame(['result' => 'ok'], $actual);
    }

    /**
     * @dataProvider failResponseDataProvider
     */
    public function testCheckFailResponseThrowsExceptionOnSoapFault(string $responseXml, int $httpStatusCode, string $expectedExpMsg): void
    {
        $paymentModel   = PosInterface::MODEL_NON_SECURE;
        $txType         = PosInterface::TX_TYPE_CANCEL;
        $requestData    = ['foo' => 'bar'];
        $order          = ['id' => 123];
        $expectedApiUrl = 'https://boatest.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc/Basic';

        $encodedBody = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ser="http://boa.net/BOA.Integration.VirtualPos/Service"><soapenv:Body><ser:foo>bar</ser:foo></soapenv:Body></soapenv:Envelope>';

        $request  = $this->prepareHttpRequest($encodedBody, [
            [
                'name'  => 'Content-Type',
                'value' => 'text/xml; charset=UTF-8',
            ],
            [
                'name'  => 'SOAPAction',
                'value' => 'http://boa.net/BOA.Integration.VirtualPos/Service/IVirtualPosService/CancelV4',
            ],
        ]);
        $response = $this->prepareHttpResponse($responseXml, $httpStatusCode);

        $this->requestValueMapper->expects($this->once())
            ->method('mapTxType')
            ->willReturn('CancelV4');

        $this->requestFactory->expects($this->once())
            ->method('createRequest')
            ->willReturn($request);

        $this->psrClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($response);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($expectedExpMsg);
        $this->expectExceptionCode($httpStatusCode);
        $this->client->request($txType, $paymentModel, $requestData, $order, $expectedApiUrl);
    }


    public static function getApiUrlDataProvider(): array
    {
        return [
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'expected'     => 'https://boatest.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc/Basic',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boatest.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc/Basic',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_REFUND,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boatest.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc/Basic',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_REFUND_PARTIAL,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boatest.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc/Basic',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_CANCEL,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boatest.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc/Basic',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_STATUS,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boatest.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc/Basic',
            ],
        ];
    }


    public static function requestDataProvider(): \Generator
    {
        yield [
            'txType'         => PosInterface::TX_TYPE_PAY_AUTH,
            'paymentModel'   => PosInterface::MODEL_3D_SECURE,
            'requestData'    => ['foo' => 'bar'],
            'order'          => ['id' => 123],
            'expectedApiUrl' => 'https://boatest.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc/Basic',
        ];
    }

    public static function failResponseDataProvider(): array
    {
        return [
            [
                'responseXml'    => '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body><s:Fault><faultstring xml:lang="tr">Some SOAP Fault</faultstring></s:Fault></s:Body></s:Envelope>',
                'statusCode' => 400,
                'expectedExpMsg' => 'Some SOAP Fault',
            ],
            [
                'responseXml'    => '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body><s:Fault><other>bla</other></s:Fault></s:Body></s:Envelope>',
                'statusCode' => 400,
                'expectedExpMsg' => 'Bankaya istek başarısız!',
            ],
            [
                'responseXml'    => '',
                'statusCode' => 200,
                'expectedExpMsg' => 'Bankaya istek başarısız!',
            ],
        ];
    }
}
