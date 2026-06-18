<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Client;

use Mews\Pos\Client\AbstractHttpClient;
use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Client\PayFlexV4PosHttpClient;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestValueMapper\RequestValueMapperInterface;
use Mews\Pos\Factory\PosHttpClientFactory;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\Gateways\PayFlexV4Pos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

#[CoversClass(PayFlexV4PosHttpClient::class)]
#[CoversClass(AbstractHttpClient::class)]
class PayFlexV4PosHttpClientTest extends TestCase
{
    use HttpClientTestTrait;

    private PayFlexV4PosHttpClient $client;

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
            PayFlexV4PosHttpClient::class,
            'https://onlineodemetest.vakifbank.com.tr:4443/VposService/v3/Vposreq.aspx',
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
        $this->assertTrue($this->client::supports(PayFlexV4Pos::class, HttpClientInterface::API_NAME_PAYMENT_API));
        $this->assertFalse($this->client::supports(PayFlexV4Pos::class, HttpClientInterface::API_NAME_GATEWAY_3D_API));
        $this->assertFalse($this->client::supports(PayFlexV4Pos::class, HttpClientInterface::API_NAME_QUERY_API));
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
        $request = $this->prepareHttpRequest($encodedRequestData, [
            [
                'name'  => 'Content-Type',
                'value' => 'application/x-www-form-urlencoded',
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

        $actual = $this->client->request($txType, $paymentModel, $requestData, $order, $expectedApiUrl);

        $this->assertSame($decodedResponse, $actual);
    }

    public function testRequestUndecodableResponse(): void
    {
        $txType      = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel = PosInterface::MODEL_3D_SECURE;
        $requestData = ['request-data' => 'abc'];
        $order       = ['id' => 123];

        $request = $this->prepareHttpRequest('prmstr=%3CVposRequest%3E%3Crequest-data%3Eabc%3C%2Frequest-data%3E%3C%2FVposRequest%3E', [
            [
                'name'  => 'Content-Type',
                'value' => 'application/x-www-form-urlencoded',
            ],
        ]);

        $response = $this->prepareHttpResponse('not-valid-xml', 400);

        $this->requestFactory->expects($this->once())
            ->method('createRequest')
            ->willReturn($request);

        $this->psrClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($response);

        $this->expectException(\RuntimeException::class);
        $this->client->request($txType, $paymentModel, $requestData, $order);
    }

    public function testRequestBadRequest(): void
    {
        $txType      = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel = PosInterface::MODEL_3D_SECURE;
        $requestData = ['request-data' => 'abc'];
        $order       = ['id' => 123];

        $request = $this->prepareHttpRequest('prmstr=%3CVposRequest%3E%3Crequest-data%3Eabc%3C%2Frequest-data%3E%3C%2FVposRequest%3E', [
            [
                'name'  => 'Content-Type',
                'value' => 'application/x-www-form-urlencoded',
            ],
        ]);

        $response = $this->prepareHttpResponse('response-content', 500);

        $this->requestFactory->expects($this->once())
            ->method('createRequest')
            ->willReturn($request);

        $this->psrClient->expects($this->once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('İstek Başarısız!');

        $this->client->request($txType, $paymentModel, $requestData, $order);
    }

    public static function getApiUrlDataProvider(): array
    {
        return [
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_HOST,
                'expected'     => 'https://onlineodemetest.vakifbank.com.tr:4443/VposService/v3/Vposreq.aspx',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'expected'     => 'https://onlineodemetest.vakifbank.com.tr:4443/VposService/v3/Vposreq.aspx',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://onlineodemetest.vakifbank.com.tr:4443/VposService/v3/Vposreq.aspx',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_REFUND,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://onlineodemetest.vakifbank.com.tr:4443/VposService/v3/Vposreq.aspx',
            ],
        ];
    }

    public static function requestDataProvider(): \Generator
    {
        yield [
            'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
            'paymentModel'       => PosInterface::MODEL_3D_SECURE,
            'requestData'        => ['request-data' => 'abc'],
            'encodedRequestData' => 'prmstr=%3CVposRequest%3E%3Crequest-data%3Eabc%3C%2Frequest-data%3E%3C%2FVposRequest%3E',
            'order'              => ['id' => 123],
            'expectedApiUrl'     => 'https://onlineodemetest.vakifbank.com.tr:4443/VposService/v3/Vposreq.aspx',
        ];
    }
}
