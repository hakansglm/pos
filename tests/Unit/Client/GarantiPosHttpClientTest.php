<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Client;

use Mews\Pos\Client\AbstractHttpClient;
use Mews\Pos\Client\GarantiPosHttpClient;
use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\Request\ValueMapper\RequestValueMapperInterface;
use Mews\Pos\Factory\PosHttpClientFactory;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\Gateways\GarantiPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

#[CoversClass(GarantiPosHttpClient::class)]
#[CoversClass(AbstractHttpClient::class)]
class GarantiPosHttpClientTest extends TestCase
{
    use HttpClientTestTrait;

    private GarantiPosHttpClient $client;

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
     * @var StreamFactoryInterface & MockObject
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
            GarantiPosHttpClient::class,
            'https://sanalposprovtest.garantibbva.com.tr/VPServlet',
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
        $this->assertTrue($this->client::supports(GarantiPos::class, HttpClientInterface::API_NAME_PAYMENT_API));
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

        $encodedBody = '<?xml version="1.0" encoding="UTF-8"?>
<GVPSRequest><request-data>abc</request-data></GVPSRequest>
';
        $request     = $this->prepareHttpRequest($encodedBody, []);

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
<GVPSRequest><request-data>abc</request-data></GVPSRequest>
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
                'expected'     => 'https://sanalposprovtest.garantibbva.com.tr/VPServlet',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://sanalposprovtest.garantibbva.com.tr/VPServlet',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_REFUND,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://sanalposprovtest.garantibbva.com.tr/VPServlet',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_REFUND_PARTIAL,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://sanalposprovtest.garantibbva.com.tr/VPServlet',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_HISTORY,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://sanalposprovtest.garantibbva.com.tr/VPServlet',
            ],
        ];
    }

    public static function requestDataProvider(): \Generator
    {
        yield [
            'txType'         => PosInterface::TX_TYPE_PAY_AUTH,
            'paymentModel'   => PosInterface::MODEL_3D_SECURE,
            'requestData'    => [
                'Mode'        => 'TEST',
                'Version'     => 'v0.01',
                'Transaction' => [
                    'Type'                  => 'sales',
                    'InstallmentCnt'        => '',
                    'Amount'                => 10025,
                    'CurrencyCode'          => '949',
                    'CardholderPresentCode' => '0',
                    'MotoInd'               => 'N',
                ],
            ],
            'encodedRequestData' => '<?xml version="1.0" encoding="UTF-8"?>
<GVPSRequest><Mode>TEST</Mode><Version>v0.01</Version><Transaction><Type>sales</Type><InstallmentCnt></InstallmentCnt><Amount>10025</Amount><CurrencyCode>949</CurrencyCode><CardholderPresentCode>0</CardholderPresentCode><MotoInd>N</MotoInd></Transaction></GVPSRequest>
',
            'responseContent'    => '<?xml version="1.0" encoding="UTF-8"?>
<GVPSRequest><Mode>TEST</Mode><Version>v0.01</Version><Terminal><ProvUserID>PROVAUT</ProvUserID><UserID>PROVAUT</UserID><HashData>8DD74209DEEB7D333105E1C69998A827419A3B04</HashData><ID>30691298</ID><MerchantID>7000679</MerchantID></Terminal><Customer><IPAddress>127.15.15.1</IPAddress><EmailAddress>email@example.com</EmailAddress></Customer><Order><OrderID>2020110828BC</OrderID></Order><Transaction><Type>orderinq</Type><InstallmentCnt></InstallmentCnt><Amount>100</Amount><CurrencyCode>949</CurrencyCode><CardholderPresentCode>0</CardholderPresentCode><MotoInd>N</MotoInd></Transaction></GVPSRequest>
',
            'expectedDecodedResponse' => [
                'Mode'        => 'TEST',
                'Version'     => 'v0.01',
                'Terminal'    => [
                    'ProvUserID' => 'PROVAUT',
                    'UserID'     => 'PROVAUT',
                    'HashData'   => '8DD74209DEEB7D333105E1C69998A827419A3B04',
                    'ID'         => '30691298',
                    'MerchantID' => '7000679',
                ],
                'Customer'    => [
                    'IPAddress'    => '127.15.15.1',
                    'EmailAddress' => 'email@example.com',
                ],
                'Order'       => [
                    'OrderID' => '2020110828BC',
                ],
                'Transaction' => [
                    'Type'                  => 'orderinq',
                    'InstallmentCnt'        => '',
                    'Amount'                => '100',
                    'CurrencyCode'          => '949',
                    'CardholderPresentCode' => '0',
                    'MotoInd'               => 'N',
                ],
            ],
            'order'          => ['id' => 123],
            'expectedApiUrl' => 'https://sanalposprovtest.garantibbva.com.tr/VPServlet',
        ];
    }
}
