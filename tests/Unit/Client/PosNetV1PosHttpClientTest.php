<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Client;

use Mews\Pos\Client\AbstractHttpClient;
use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Client\PosNetV1PosHttpClient;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\Request\ValueMapper\RequestValueMapperInterface;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\PosHttpClientFactory;
use Mews\Pos\Gateways\PosNetPos;
use Mews\Pos\Gateways\PosNetV1Pos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

#[CoversClass(PosNetV1PosHttpClient::class)]
#[CoversClass(AbstractHttpClient::class)]
class PosNetV1PosHttpClientTest extends TestCase
{
    use HttpClientTestTrait;

    private PosNetV1PosHttpClient $client;

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
            PosNetV1PosHttpClient::class,
            'https://epostest.albarakaturk.com.tr/ALBMerchantService/MerchantJSONAPI.svc',
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
    public function testGetApiUrl(string $txType, string $paymentModel, string $apiUri, string $expected): void
    {
        $this->requestValueMapper->expects($this->once())
            ->method('mapTxType')
            ->with($txType)
            ->willReturn($apiUri);

        $actual = $this->client->getApiURL($txType, $paymentModel);

        $this->assertSame($expected, $actual);
    }

    public function testGetApiUrlException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->client->getApiURL();
    }

    public function testSupports(): void
    {
        $this->assertTrue($this->client::supports(PosNetV1Pos::class, HttpClientInterface::API_NAME_PAYMENT_API));
        $this->assertFalse($this->client::supports(PosNetPos::class, HttpClientInterface::API_NAME_PAYMENT_API));
    }

    /**
     * @dataProvider supportsTxDataProvider
     */
    public function testSupportsTx(string $txType, string $paymentModel): void
    {
        $this->requestValueMapper->expects($this->once())
            ->method('mapTxType')
            ->with($txType)
            ->willReturn('Sale');

        $this->assertTrue($this->client->supportsTx($txType, $paymentModel));
    }

    public function testSupportsCustomQuery(): void
    {
        $this->requestValueMapper->expects($this->never())
            ->method('mapTxType');

        $this->assertTrue($this->client->supportsTx(PosInterface::TX_TYPE_CUSTOM_QUERY, PosInterface::MODEL_NON_SECURE));
    }

    /**
     * @dataProvider supportsTxWithUnsupportedTxDataProvider
     */
    public function testSupportsTxWithUnsupportedTx(string $txType, string $paymentModel): void
    {
        $this->requestValueMapper->expects($this->once())
            ->method('mapTxType')
            ->with($txType)
            ->willThrowException(new UnsupportedTransactionTypeException());

        $this->assertFalse($this->client->supportsTx($txType, $paymentModel));
    }

    public static function supportsTxDataProvider(): array
    {
        return [
            'supported_tx_type'  => [
                'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel'       => PosInterface::MODEL_3D_SECURE,
            ],
            'unsupported_tx_type' => [
                'txType'             => 'unsupported',
                'paymentModel'       => PosInterface::MODEL_3D_SECURE,
            ],
        ];
    }

    public static function supportsTxWithUnsupportedTxDataProvider(): array
    {
        return [
            'supported_tx_type'  => [
                'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel'       => PosInterface::MODEL_3D_SECURE,
            ],
            'unsupported_tx_type' => [
                'txType'             => 'unsupported',
                'paymentModel'       => PosInterface::MODEL_3D_SECURE,
            ],
        ];
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
                'value' => 'application/json',
            ],
        ]);

        $decodedResponse = ['decoded' => 'response'];
        $responseContent = '{"decoded":"response"}';
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

    public function testRequestBadRequest(): void
    {
        $txType         = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel   = PosInterface::MODEL_3D_SECURE;
        $requestData    = ['request-data' => 'abc'];
        $order          = ['id' => 123];

        $request     = $this->prepareHttpRequest('{"request-data":"abc"}', [
            [
                'name'  => 'Content-Type',
                'value' => 'application/json',
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

        $request     = $this->prepareHttpRequest('{"request-data":"abc"}', [
            [
                'name'  => 'Content-Type',
                'value' => 'application/json',
            ],
        ]);

        $responseContent = 'not-valid-json';
        $response        = $this->prepareHttpResponse($responseContent, 400);

        $this->requestFactory->expects($this->once())
            ->method('createRequest')
            ->willReturn($request);

        $this->psrClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($response);

        $this->expectException(NotEncodableValueException::class);
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
        $this->requestValueMapper->expects(self::once())
            ->method('mapTxType')
            ->with(PosInterface::TX_TYPE_PAY_POST_AUTH)
            ->willThrowException(new UnsupportedTransactionTypeException());

        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->client->request(
            PosInterface::TX_TYPE_PAY_POST_AUTH,
            PosInterface::MODEL_3D_SECURE,
            ['request-data'],
            ['id' => 123]
        );
    }

    public static function getApiUrlDataProvider(): array
    {
        return [
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'apiUri'       => 'Sale',
                'expected'     => 'https://epostest.albarakaturk.com.tr/ALBMerchantService/MerchantJSONAPI.svc/Sale',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_CANCEL,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'apiUri'       => 'Reverse',
                'expected'     => 'https://epostest.albarakaturk.com.tr/ALBMerchantService/MerchantJSONAPI.svc/Reverse',
            ],
        ];
    }

    public static function requestDataProvider(): \Generator
    {
        yield [
            'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
            'paymentModel'       => PosInterface::MODEL_3D_SECURE,
            'requestData'        => ['request-data'],
            'encodedRequestData' => '["request-data"]',
            'order'              => ['id' => 123],
            'expectedApiUrl'     => 'https://epostest.albarakaturk.com.tr/ALBMerchantService/MerchantJSONAPI.svc/Sale',
        ];
    }
}
