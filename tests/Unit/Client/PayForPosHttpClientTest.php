<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Client;

use Mews\Pos\Client\AbstractHttpClient;
use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Client\PayForPosHttpClient;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestValueMapper\RequestValueMapperInterface;
use Mews\Pos\Factory\PosHttpClientFactory;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\Gateways\PayForPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

#[CoversClass(PayForPosHttpClient::class)]
#[CoversClass(AbstractHttpClient::class)]
class PayForPosHttpClientTest extends TestCase
{
    use HttpClientTestTrait;

    private PayForPosHttpClient $client;

    /** @var LoggerInterface & MockObject */
    private MockObject $logger;

    /**
     * @var ClientInterface&MockObject
     */
    private MockObject $psrClient;

    /**
     * @var RequestFactoryInterface& MockObject
     */
    private MockObject $requestFactory;

    /**
     * @var StreamFactoryInterface&MockObject
     */
    private MockObject $streamFactory;

    /**
     * @var RequestValueMapperInterface&MockObject
     */
    private MockObject $requestValueMapper;

    protected function setUp(): void
    {
        $this->logger             = $this->createMock(LoggerInterface::class);
        $crypt                    = $this->createMock(CryptInterface::class);
        $this->requestValueMapper = $this->createMock(RequestValueMapperInterface::class);
        $this->psrClient          = $this->createMock(ClientInterface::class);
        $this->requestFactory     = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory      = $this->createMock(StreamFactoryInterface::class);

        $this->client = PosHttpClientFactory::create(
            PayForPosHttpClient::class,
            'https://vpostest.qnbfinansbank.com/Gateway/XMLGate.aspx',
            $crypt,
            $this->requestValueMapper,
            $this->logger,
            $this->psrClient,
            $this->requestFactory,
            $this->streamFactory
        );
    }

    /**
     * @dataProvider getApiUrlDataProvider
     */
    public function testGetApiUrl(string $txType, string $paymentModel, string $expected): void
    {
        $actual = $this->client->getApiURL($txType, $paymentModel);

        $this->assertSame($expected, $actual);
    }

    public function testSupports(): void
    {
        $this->assertTrue($this->client::supports(PayForPos::class, HttpClientInterface::API_NAME_PAYMENT_API));
        $this->assertFalse($this->client::supports(AkbankPos::class, HttpClientInterface::API_NAME_PAYMENT_API));
    }

    public function testSupportsTx(): void
    {
        $this->assertTrue($this->client->supportsTx(PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_3D_SECURE));
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
        $decodedResponse = ['result' => 'success'];
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

        $actual = $this->client->request(
            $txType,
            $paymentModel,
            $requestData,
            $order,
            $expectedApiUrl
        );

        $this->assertSame($decodedResponse, $actual);
    }

    public function testRequestUndecodableResponse(): void
    {
        $txType         = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel   = PosInterface::MODEL_3D_SECURE;
        $requestData    = ['request-data' => 'abc'];
        $order          = ['id' => 123];

        $encodedBody = '<?xml version="1.0" encoding="UTF-8"?>
<PayforRequest><request-data>abc</request-data></PayforRequest>
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

    public function testRequestBadRequest(): void
    {
        $txType         = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel   = PosInterface::MODEL_3D_SECURE;
        $requestData    = ['request-data' => 'abc'];
        $order          = ['id' => 123];

        $encodedBody = '<?xml version="1.0" encoding="UTF-8"?>
<PayforRequest><request-data>abc</request-data></PayforRequest>
';
        $request     = $this->prepareHttpRequest($encodedBody, [
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

    public static function getApiUrlDataProvider(): array
    {
        return [
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'expected'     => 'https://vpostest.qnbfinansbank.com/Gateway/XMLGate.aspx',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://vpostest.qnbfinansbank.com/Gateway/XMLGate.aspx',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_REFUND,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://vpostest.qnbfinansbank.com/Gateway/XMLGate.aspx',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_STATUS,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://vpostest.qnbfinansbank.com/Gateway/XMLGate.aspx',
            ],
        ];
    }

    public static function requestDataProvider(): \Generator
    {
        yield [
            'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
            'paymentModel'       => PosInterface::MODEL_3D_SECURE,
            'requestData'        => ['request-data'],
            'encodedRequestData' => '<?xml version="1.0" encoding="UTF-8"?>
<PayforRequest><item key="0">request-data</item></PayforRequest>
',
            'order'              => ['id' => 123],
            'expectedApiUrl'     => 'https://vpostest.qnbfinansbank.com/Gateway/XMLGate.aspx',
        ];
    }
}
