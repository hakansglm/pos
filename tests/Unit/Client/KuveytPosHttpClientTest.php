<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Client;

use Mews\Pos\Client\AbstractHttpClient;
use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Client\KuveytPosHttpClient;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\Request\ValueMapper\RequestValueMapperInterface;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\PosHttpClientFactory;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\Gateways\KuveytPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

#[CoversClass(KuveytPosHttpClient::class)]
#[CoversClass(AbstractHttpClient::class)]
class KuveytPosHttpClientTest extends TestCase
{
    use HttpClientTestTrait;

    private KuveytPosHttpClient $client;

    /** @var LoggerInterface & MockObject */
    private MockObject $logger;

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
        $crypt                    = $this->createMock(CryptInterface::class);
        $this->requestValueMapper = $this->createMock(RequestValueMapperInterface::class);
        $this->psrClient          = $this->createMock(ClientInterface::class);
        $this->requestFactory     = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory      = $this->createMock(StreamFactoryInterface::class);


        $this->client = PosHttpClientFactory::create(
            KuveytPosHttpClient::class,
            'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home',
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
        $this->assertFalse($this->client::supports(KuveytPos::class, HttpClientInterface::API_NAME_QUERY_API));
        $this->assertTrue($this->client::supports(KuveytPos::class, HttpClientInterface::API_NAME_GATEWAY_3D_API));
        $this->assertTrue($this->client::supports(KuveytPos::class, HttpClientInterface::API_NAME_PAYMENT_API));
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
            'pay_auth_3d_secure'       => [PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_3D_SECURE, true],
            '3d_form_build_3d_secure'  => [PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD, PosInterface::MODEL_3D_SECURE, true],
            'pay_pre_auth_3d_secure'   => [PosInterface::TX_TYPE_PAY_PRE_AUTH, PosInterface::MODEL_3D_SECURE, false],
            'pay_auth_non_secure'      => [PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_NON_SECURE, true],
        ];
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
     * @dataProvider getApiUrlDataFailProvider
     */
    public function testGetApiUrlUnsupportedTxType(?string $txType, ?string $paymentModel, string $expectedException): void
    {
        $this->expectException($expectedException);
        $this->client->getApiURL($txType, $paymentModel);
    }

    public function testRequestFor3DFormBuild(): void
    {
        $txType       = PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD;
        $paymentModel = PosInterface::MODEL_3D_SECURE;
        $requestData  = ['request-data'];
        $order        = ['id' => 123];
        $apiUrl       = 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/ThreeDModelPayGate';

        $encodedBody = '<?xml version="1.0" encoding="ISO-8859-1"?>
<KuveytTurkVPosMessage><item key="0">request-data</item></KuveytTurkVPosMessage>
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
        $decodedResponse = ['result' => 'success'];
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

        $encodedBody = '<?xml version="1.0" encoding="ISO-8859-1"?>
<KuveytTurkVPosMessage><request-data>abc</request-data></KuveytTurkVPosMessage>
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

        $encodedBody = '<?xml version="1.0" encoding="ISO-8859-1"?>
<KuveytTurkVPosMessage><request-data>abc</request-data></KuveytTurkVPosMessage>
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

    public function testRequestApiUrlNotFound(): void
    {
        $this->psrClient->expects($this->never())
            ->method('sendRequest');

        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->client->request(
            PosInterface::TX_TYPE_PAY_POST_AUTH,
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
<KuveytTurkVPosMessage><item key="0">request-data</item></KuveytTurkVPosMessage>
',
            'order'              => ['id' => 123],
            'expectedApiUrl'     => 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/ThreeDModelProvisionGate',
        ];
    }

    public static function getApiUrlDataProvider(): array
    {
        return [
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'expected'     => 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/ThreeDModelProvisionGate',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/Non3DPayGate',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'expected'     => 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/ThreeDModelPayGate',
            ],
        ];
    }

    public static function getApiUrlDataFailProvider(): array
    {
        return [
            [
                'txType'          => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel'    => PosInterface::MODEL_3D_PAY,
                'exception_class' => UnsupportedTransactionTypeException::class,
            ],
            [
                'txType'          => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                'paymentModel'    => PosInterface::MODEL_NON_SECURE,
                'exception_class' => UnsupportedTransactionTypeException::class,
            ],
            [
                'txType'          => null,
                'paymentModel'    => null,
                'exception_class' => \InvalidArgumentException::class,
            ],
            [
                'txType'          => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel'    => null,
                'exception_class' => \InvalidArgumentException::class,
            ],
            [
                'txType'          => null,
                'paymentModel'    => PosInterface::MODEL_3D_PAY,
                'exception_class' => \InvalidArgumentException::class,
            ],
        ];
    }
}
