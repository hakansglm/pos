<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Client;

use Mews\Pos\Client\AbstractHttpClient;
use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Client\ParamPosHttpClient;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\Request\ValueMapper\RequestValueMapperInterface;
use Mews\Pos\Factory\PosHttpClientFactory;
use Mews\Pos\Gateway\AkbankPos;
use Mews\Pos\Gateway\Param3DHostPos;
use Mews\Pos\Gateway\ParamPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

#[CoversClass(ParamPosHttpClient::class)]
#[CoversClass(AbstractHttpClient::class)]
class ParamPosHttpClientTest extends TestCase
{
    use HttpClientTestTrait;

    private ParamPosHttpClient $client;

    /** @var LoggerInterface & MockObject */
    private MockObject $logger;

    /** @var CryptInterface & MockObject */
    private MockObject $crypt;

    /** @var RequestValueMapperInterface & MockObject */
    private MockObject $requestValueMapper;

    /**
     * @var ClientInterface& MockObject
     */
    private MockObject $psrClient;

    /**
     * @var RequestFactoryInterface& MockObject
     */
    private MockObject $requestFactory;

    /**
     * @var StreamFactoryInterface & MockObject
     */
    private MockObject $streamFactory;

    protected function setUp(): void
    {
        $this->logger             = $this->createMock(LoggerInterface::class);
        $this->crypt              = $this->createMock(CryptInterface::class);
        $this->requestValueMapper = $this->createMock(RequestValueMapperInterface::class);
        $this->psrClient          = $this->createMock(ClientInterface::class);
        $this->requestFactory     = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory      = $this->createMock(StreamFactoryInterface::class);


        $this->client = PosHttpClientFactory::create(
            ParamPosHttpClient::class,
            'https://test-dmz.param.com.tr/turkpos.ws/service_turkpos_test.asmx',
            $this->crypt,
            $this->requestValueMapper,
            $this->logger,
            $this->psrClient,
            $this->requestFactory,
            $this->streamFactory
        );
    }

    public function testSupports(): void
    {
        $this->assertTrue($this->client::supports(ParamPos::class, HttpClientInterface::API_NAME_PAYMENT_API));
        $this->assertTrue($this->client::supports(Param3DHostPos::class, HttpClientInterface::API_NAME_PAYMENT_API));
        $this->assertFalse($this->client::supports(AkbankPos::class, HttpClientInterface::API_NAME_PAYMENT_API));
    }

    public function testSupportsTx(): void
    {
        $this->assertTrue($this->client->supportsTx(PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_3D_SECURE));
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
                'value' => 'text/xml',
            ],
        ]);

        $responseXml = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><result>ok</result></soap:Body></soap:Envelope>';
        $response    = $this->prepareHttpResponse($responseXml, 200);

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

    public function testRequestUndecodableResponse(): void
    {
        $txType      = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel = PosInterface::MODEL_3D_SECURE;
        $requestData = ['request-data' => 'abc'];
        $order       = ['id' => 123];

        $encodedBody = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><request-data>abc</request-data></soap:Envelope>
';
        $request     = $this->prepareHttpRequest($encodedBody, [
            [
                'name'  => 'Content-Type',
                'value' => 'text/xml',
            ],
        ]);

        $response = $this->prepareHttpResponse('not-valid-xml', 400);

        $this->requestFactory->expects($this->once())
            ->method('createRequest')
            ->willReturn($request);

        $this->psrClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($response);

        $this->expectException(NotEncodableValueException::class);
        $this->client->request($txType, $paymentModel, $requestData, $order);
    }

    /**
     * @dataProvider failResponseDataProvider
     */
    public function testRequestBadRequest(string $responseXml, string $expectedExpMsg): void
    {
        $txType      = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel = PosInterface::MODEL_3D_SECURE;
        $requestData = ['request-data' => 'abc'];
        $order       = ['id' => 123];
        $expectedApiUrl = 'https://test-dmz.param.com.tr/turkpos.ws/service_turkpos_test.asmx';

        $encodedBody = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><request-data>abc</request-data></soap:Envelope>
';
        $request     = $this->prepareHttpRequest($encodedBody, [
            [
                'name'  => 'Content-Type',
                'value' => 'text/xml',
            ],
        ]);

        $response = $this->prepareHttpResponse($responseXml, 200);

        $this->requestFactory->expects($this->once())
            ->method('createRequest')
            ->with('POST', $expectedApiUrl)
            ->willReturn($request);

        $this->psrClient->expects($this->once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($expectedExpMsg);

        $this->client->request($txType, $paymentModel, $requestData, $order, $expectedApiUrl);
    }

    public static function getApiUrlDataProvider(): array
    {
        return [
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'expected'     => 'https://test-dmz.param.com.tr/turkpos.ws/service_turkpos_test.asmx',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://test-dmz.param.com.tr/turkpos.ws/service_turkpos_test.asmx',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_REFUND,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://test-dmz.param.com.tr/turkpos.ws/service_turkpos_test.asmx',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_REFUND_PARTIAL,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://test-dmz.param.com.tr/turkpos.ws/service_turkpos_test.asmx',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_CANCEL,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://test-dmz.param.com.tr/turkpos.ws/service_turkpos_test.asmx',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_STATUS,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://test-dmz.param.com.tr/turkpos.ws/service_turkpos_test.asmx',
            ],
        ];
    }

    public static function requestDataProvider(): \Generator
    {
        yield [
            'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
            'paymentModel'       => PosInterface::MODEL_3D_SECURE,
            'requestData'        => ['request-data' => 'abc'],
            'encodedRequestData' => '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><request-data>abc</request-data></soap:Envelope>
',
            'order'              => ['id' => 123],
            'expectedApiUrl'     => 'https://test-dmz.param.com.tr/turkpos.ws/service_turkpos_test.asmx',
        ];
    }

    public static function failResponseDataProvider(): array
    {
        return [
            [
                'responseXml'    => '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><soap:Fault><faultstring>Error message</faultstring></soap:Fault></soap:Body></soap:Envelope>',
                'expectedExpMsg' => 'Error message',
            ],
            [
                'responseXml'    => '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><soap:Fault><other_key>bla</other_key></soap:Fault></soap:Body></soap:Envelope>',
                'expectedExpMsg' => 'Bankaya istek başarısız!',
            ],
        ];
    }
}
