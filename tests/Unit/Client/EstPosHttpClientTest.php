<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Client;

use Mews\Pos\Client\EstPosHttpClient;
use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestValueMapper\RequestValueMapperInterface;
use Mews\Pos\Factory\PosHttpClientFactory;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * @covers \Mews\Pos\Client\EstPosHttpClient
 * @covers \Mews\Pos\Client\AbstractHttpClient
 */
class EstPosHttpClientTest extends TestCase
{
    use HttpClientTestTrait;

    private EstPosHttpClient $client;

    /** @var LoggerInterface & MockObject */
    private LoggerInterface $logger;

    /**
     * @var ClientInterface&MockObject
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

    /**
     * @var RequestValueMapperInterface&MockObject
     */
    private RequestValueMapperInterface $requestValueMapper;

    protected function setUp(): void
    {
        $this->logger             = $this->createMock(LoggerInterface::class);
        $crypt                    = $this->createMock(CryptInterface::class);
        $this->requestValueMapper = $this->createMock(RequestValueMapperInterface::class);
        $this->psrClient          = $this->createMock(ClientInterface::class);
        $this->requestFactory     = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory      = $this->createMock(StreamFactoryInterface::class);

        $this->client = PosHttpClientFactory::create(
            EstPosHttpClient::class,
            'https://entegrasyon.asseco-see.com.tr/fim/api',
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
        $this->assertFalse($this->client::supports(AkbankPos::class, HttpClientInterface::API_NAME_PAYMENT_API));
        $this->assertTrue($this->client::supports(EstV3Pos::class, HttpClientInterface::API_NAME_PAYMENT_API));
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
        string $responseContent,
        array $expectedDecodedResponse,
        array  $order,
        string $expectedApiUrl
    ): void {
        $request         = $this->prepareHttpRequest($encodedRequestData, []);
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

        $this->assertSame($expectedDecodedResponse, $actual);
    }

    public function testRequestUndecodableResponse(): void
    {
        $txType         = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel   = PosInterface::MODEL_3D_SECURE;
        $requestData    = ['request-data' => 'abc'];
        $order          = ['id' => 123];

        $encodedBody = '<?xml version="1.0" encoding="ISO-8859-9"?>
<CC5Request><request-data>abc</request-data></CC5Request>
';

        $request         = $this->prepareHttpRequest($encodedBody, []);

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

        $encodedBody = '<?xml version="1.0" encoding="ISO-8859-9"?>
<CC5Request><request-data>abc</request-data></CC5Request>
';
        $request     = $this->prepareHttpRequest($encodedBody, []);


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
                'expected'     => 'https://entegrasyon.asseco-see.com.tr/fim/api',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://entegrasyon.asseco-see.com.tr/fim/api',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_REFUND,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://entegrasyon.asseco-see.com.tr/fim/api',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_REFUND_PARTIAL,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://entegrasyon.asseco-see.com.tr/fim/api',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_HISTORY,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://entegrasyon.asseco-see.com.tr/fim/api',
            ],
        ];
    }

    public static function requestDataProvider(): \Generator
    {
        yield [
            'txType'         => PosInterface::TX_TYPE_PAY_AUTH,
            'paymentModel'   => PosInterface::MODEL_3D_SECURE,
            'requestData'    => [
                'Name' => 'ISBANKAPI',
                'Password' => 'ISBANK07',
                'ClientId' => '700655000200',
            ],
            'encodedRequestData'    => '<?xml version="1.0" encoding="ISO-8859-9"?>
<CC5Request><Name>ISBANKAPI</Name><Password>ISBANK07</Password><ClientId>700655000200</ClientId></CC5Request>
',
            'responseContent' => '<?xml version="1.0" encoding="ISO-8859-9"?>
<CC5Response>
  <OrderId>20230910AF6A</OrderId>
  <GroupId>20230910AF6A</GroupId>
  <Response>Approved</Response>
  <AuthCode>P18552</AuthCode>
  <HostRefNum>325300733333</HostRefNum>
  <ProcReturnCode>00</ProcReturnCode>
  <TransId>23253WkfD10806</TransId>
  <ErrMsg></ErrMsg>
  <Extra>
    <SETTLEID>2589</SETTLEID>
    <TRXDATE>20230910 22:36:30</TRXDATE>
    <ERRORCODE></ERRORCODE>
    <TERMINALID>00655020</TERMINALID>
    <MERCHANTID>655000200</MERCHANTID>
    <CARDBRAND>VISA</CARDBRAND>
    <CARDISSUER>Z&#x130;RAAT BANKASI</CARDISSUER>
    <AVSAPPROVE>Y</AVSAPPROVE>
    <HOSTDATE>0910-223632</HOSTDATE>
    <AVSERRORCODEDETAIL>avshatali-avshatali-avshatali-avshatali-</AVSERRORCODEDETAIL>
    <NUMCODE>00</NUMCODE>
  </Extra>
</CC5Response>',
            'expectedDecodedResponse' => [
                'OrderId'        => '20230910AF6A',
                'GroupId'        => '20230910AF6A',
                'Response'       => 'Approved',
                'AuthCode'       => 'P18552',
                'HostRefNum'     => '325300733333',
                'ProcReturnCode' => '00',
                'TransId'        => '23253WkfD10806',
                'ErrMsg'         => '',
                'Extra'          => [
                    'SETTLEID'           => '2589',
                    'TRXDATE'            => '20230910 22:36:30',
                    'ERRORCODE'          => '',
                    'TERMINALID'         => '00655020',
                    'MERCHANTID'         => '655000200',
                    'CARDBRAND'          => 'VISA',
                    'CARDISSUER'         => 'ZİRAAT BANKASI',
                    'AVSAPPROVE'         => 'Y',
                    'HOSTDATE'           => '0910-223632',
                    'AVSERRORCODEDETAIL' => 'avshatali-avshatali-avshatali-avshatali-',
                    'NUMCODE'            => '00',
                ],
            ],
            'order'          => ['id' => 123],
            'expectedApiUrl' => 'https://entegrasyon.asseco-see.com.tr/fim/api',
        ];
    }
}
