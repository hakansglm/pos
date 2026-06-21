<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Client;

use InvalidArgumentException;
use Mews\Pos\Client\AbstractHttpClient;
use Mews\Pos\Client\AkbankPosHttpClient;
use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\Request\ValueMapper\RequestValueMapperInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Factory\PosHttpClientFactory;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\Gateways\AssecoPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

#[CoversClass(AkbankPosHttpClient::class)]
#[CoversClass(AbstractHttpClient::class)]
class AkbankPosHttpClientTest extends TestCase
{
    use HttpClientTestTrait;

    private AkbankPosHttpClient $client;

    /** @var LoggerInterface & MockObject */
    private MockObject $logger;

    /** @var AbstractPosAccount & MockObject */
    private MockObject $account;

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
        $this->account            = $this->createMock(AbstractPosAccount::class);
        $this->logger             = $this->createMock(LoggerInterface::class);
        $this->crypt              = $this->createMock(CryptInterface::class);
        $this->requestValueMapper = $this->createMock(RequestValueMapperInterface::class);
        $this->psrClient          = $this->createMock(ClientInterface::class);
        $this->requestFactory     = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory      = $this->createMock(StreamFactoryInterface::class);

        $this->client = PosHttpClientFactory::create(
            AkbankPosHttpClient::class,
            'https://apipre.akbank.com/api/v1/payment/virtualpos',
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
        $this->assertTrue($this->client::supports(AkbankPos::class, HttpClientInterface::API_NAME_PAYMENT_API));
        $this->assertTrue($this->client::supports(AkbankPos::class, HttpClientInterface::API_NAME_PAYMENT_API));
        $this->assertFalse($this->client::supports(AkbankPos::class, HttpClientInterface::API_NAME_QUERY_API));
        $this->assertFalse($this->client::supports(AssecoPos::class, HttpClientInterface::API_NAME_PAYMENT_API));
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
     * @dataProvider getApiUrlExceptionDataProvider
     */
    public function testGetApiUrlException(?string $txType, string $exceptionClass): void
    {
        $this->expectException($exceptionClass);
        $this->client->getApiURL($txType);
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
        $responseContent = '{"decoded":"response"}';
        $request         = $this->prepareHttpRequest($encodedRequestData, [
            [
                'name'  => 'Content-Type',
                'value' => 'application/json',
            ],
            [
                'name'  => 'auth-hash',
                'value' => 'hash123',
            ],
        ]);

        $response = $this->prepareHttpResponse($responseContent, 200);

        $this->requestFactory->expects($this->once())
            ->method('createRequest')
            ->with('POST', $expectedApiUrl)
            ->willReturn($request);

        $this->account->expects($this->once())
            ->method('getStoreKey')
            ->willReturn('store-key123');

        $this->crypt->expects($this->once())
            ->method('hashString')
            ->with($encodedRequestData, 'store-key123')
            ->willReturn('hash123');

        $this->psrClient->expects($this->once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response);

        $actual = $this->client->request(
            $txType,
            $paymentModel,
            $requestData,
            $order,
            $expectedApiUrl,
            $this->account,
        );

        $this->assertSame(['decoded' => 'response'], $actual);
    }

    public function testRequestBadRequest(): void
    {
        $txType         = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel   = PosInterface::MODEL_3D_SECURE;
        $requestData    = ['a' => 'b'];
        $order          = ['id' => 123];
        $expectedApiUrl = 'https://apipre.akbank.com/api/v1/payment/virtualpos/transaction/process';

        $encodedBody     = '{"a":"b"}';
        $responseContent = '{"message":"Error message","code":222}';
        $request         = $this->prepareHttpRequest($encodedBody, [
            [
                'name'  => 'Content-Type',
                'value' => 'application/json',
            ],
            [
                'name'  => 'auth-hash',
                'value' => 'hash123',
            ],
        ]);

        $response = $this->prepareHttpResponse($responseContent, 400);

        $this->requestFactory->expects($this->once())
            ->method('createRequest')
            ->with('POST', $expectedApiUrl)
            ->willReturn($request);

        $this->account->expects($this->once())
            ->method('getStoreKey')
            ->willReturn('store-key123');

        $this->crypt->expects($this->once())
            ->method('hashString')
            ->with($encodedBody, 'store-key123')
            ->willReturn('hash123');

        $this->psrClient->expects($this->once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Error message');
        $this->expectExceptionCode(222);
        $this->client->request(
            $txType,
            $paymentModel,
            $requestData,
            $order,
            $expectedApiUrl,
            $this->account,
        );
    }

    public function testRequestUndecodableResponse(): void
    {
        $txType       = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel = PosInterface::MODEL_3D_SECURE;
        $requestData  = ['request-data' => 'abc'];
        $order        = ['id' => 123];

        $encodedBody     = '{"request-data":"abc"}';
        $responseContent = 'not-valid-json';
        $request         = $this->prepareHttpRequest($encodedBody, [
            [
                'name'  => 'Content-Type',
                'value' => 'application/json',
            ],
            [
                'name'  => 'auth-hash',
                'value' => 'hash123',
            ],
        ]);

        $response = $this->prepareHttpResponse($responseContent, 400);

        $this->requestFactory->expects($this->once())
            ->method('createRequest')
            ->willReturn($request);

        $this->account->expects($this->once())
            ->method('getStoreKey')
            ->willReturn('store-key123');

        $this->crypt->expects($this->once())
            ->method('hashString')
            ->willReturn('hash123');

        $this->psrClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($response);

        $this->expectException(NotEncodableValueException::class);
        $this->client->request(
            $txType,
            $paymentModel,
            $requestData,
            $order,
            null,
            $this->account,
        );
    }

    public function testRequestWithoutAccount(): void
    {
        $txType         = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel   = PosInterface::MODEL_3D_SECURE;
        $requestData    = ['request-data'];
        $order          = ['id' => 123];
        $expectedApiUrl = 'https://apipre.akbank.com/api/v1/payment/virtualpos/transaction/process';

        $this->psrClient->expects($this->never())
            ->method('sendRequest');

        $this->expectException(InvalidArgumentException::class);
        $this->client->request(
            $txType,
            $paymentModel,
            $requestData,
            $order,
            $expectedApiUrl,
        );
    }

    public static function requestDataProvider(): \Generator
    {
        yield [
            'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
            'paymentModel'       => PosInterface::MODEL_3D_SECURE,
            'requestData'        => ['a' => 'b'],
            'encodedRequestData' => '{"a":"b"}',
            'order'              => ['id' => 123],
            'expectedApiUrl'     => 'https://apipre.akbank.com/api/v1/payment/virtualpos/transaction/process',
        ];
    }

    public static function getApiUrlDataProvider(): array
    {
        return [
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'expected'     => 'https://apipre.akbank.com/api/v1/payment/virtualpos/transaction/process',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://apipre.akbank.com/api/v1/payment/virtualpos/transaction/process',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_REFUND,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://apipre.akbank.com/api/v1/payment/virtualpos/transaction/process',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_REFUND_PARTIAL,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://apipre.akbank.com/api/v1/payment/virtualpos/transaction/process',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_HISTORY,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://apipre.akbank.com/api/v1/payment/virtualpos/portal/report/transaction',
            ],
        ];
    }

    public static function getApiUrlExceptionDataProvider(): array
    {
        return [
            [
                'txType'          => null,
                'exception_class' => \InvalidArgumentException::class,
            ],
        ];
    }
}
