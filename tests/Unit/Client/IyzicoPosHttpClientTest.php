<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Client;

use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Client\IyzicoPosHttpClient;
use Mews\Pos\Crypt\IyzicoPosCrypt;
use Mews\Pos\DataMapper\RequestValueMapper\IyzicoPosRequestValueMapper;
use Mews\Pos\Entity\Account\IyzicoPosAccount;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\PosHttpClientFactory;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\Gateways\IyzicoPos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\EncodedData;
use Mews\Pos\Serializer\SerializerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * @covers \Mews\Pos\Client\IyzicoPosHttpClient
 * @covers \Mews\Pos\Client\AbstractIyzicoPosHttpClient
 * @covers \Mews\Pos\Client\AbstractHttpClient
 */
class IyzicoPosHttpClientTest extends TestCase
{
    use HttpClientTestTrait;

    private const BASE_URL = 'https://sandbox-api.iyzipay.com';

    private IyzicoPosHttpClient $client;

    private IyzicoPosAccount $account;

    /** @var SerializerInterface & MockObject */
    private SerializerInterface $serializerMock;

    /** @var LoggerInterface & MockObject */
    private LoggerInterface $loggerMock;

    /** @var ClientInterface & MockObject */
    private ClientInterface $psrClient;

    /** @var RequestFactoryInterface & MockObject */
    private RequestFactoryInterface $requestFactory;

    /** @var StreamFactoryInterface & MockObject */
    private StreamFactoryInterface $streamFactory;

    /** @var IyzicoPosCrypt & MockObject */
    private IyzicoPosCrypt $cryptMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account        = AccountFactory::createIyzicoPosAccount('iyzico', 'api-key', 'secret-key');
        $this->serializerMock = $this->createMock(SerializerInterface::class);
        $this->loggerMock     = $this->createMock(LoggerInterface::class);
        $this->cryptMock      = $this->createMock(IyzicoPosCrypt::class);
        $this->psrClient      = $this->createMock(ClientInterface::class);
        $this->requestFactory = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory  = $this->createMock(StreamFactoryInterface::class);

        $requestValueMapper = new IyzicoPosRequestValueMapper();

        $this->client = PosHttpClientFactory::create(
            IyzicoPosHttpClient::class,
            self::BASE_URL,
            $this->serializerMock,
            $this->cryptMock,
            $requestValueMapper,
            $this->loggerMock,
            $this->psrClient,
            $this->requestFactory,
            $this->streamFactory
        );
    }

    public function testSupports(): void
    {
        $this->assertTrue($this->client::supports(IyzicoPos::class, HttpClientInterface::API_NAME_PAYMENT_API));
        $this->assertFalse($this->client::supports(IyzicoPos::class, HttpClientInterface::API_NAME_GATEWAY_3D_API));
        $this->assertFalse($this->client::supports(AkbankPos::class, HttpClientInterface::API_NAME_PAYMENT_API));
    }

    /**
     * @dataProvider supportsTxDataProvider
     */
    public function testSupportsTx(string $txType, bool $expected): void
    {
        $this->assertSame($expected, $this->client->supportsTx($txType, PosInterface::MODEL_NON_SECURE));
    }

    /**
     * @dataProvider getApiUrlDataProvider
     */
    public function testGetApiUrl(string $txType, ?string $paymentModel, string $expected): void
    {
        $actual = $this->client->getApiURL($txType, $paymentModel);

        $this->assertSame($expected, $actual);
    }

    public function testGetApiUrlWithoutTxTypeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->client->getApiURL(null, null);
    }

    public function testConstructorRejectsNonIyzicoCrypt(): void
    {
        $this->expectException(\LogicException::class);

        $wrongCrypt = $this->createMock(\Mews\Pos\Crypt\CryptInterface::class);

        PosHttpClientFactory::create(
            IyzicoPosHttpClient::class,
            self::BASE_URL,
            $this->serializerMock,
            $wrongCrypt,
            new IyzicoPosRequestValueMapper(),
            $this->loggerMock,
            $this->psrClient,
            $this->requestFactory,
            $this->streamFactory
        );
    }

    public function testRequest(): void
    {
        $txType       = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel = PosInterface::MODEL_NON_SECURE;
        $requestData  = ['locale' => 'tr', 'price' => 100.0];
        $order        = ['id' => 'order-1'];
        $apiUrl       = self::BASE_URL.'/payment/auth';

        $jsonBody    = '{"locale":"tr","price":100}';
        $encodedData = new EncodedData($jsonBody, SerializerInterface::FORMAT_JSON);

        $this->serializerMock->expects(self::once())
            ->method('encode')
            ->with($requestData, $txType)
            ->willReturn($encodedData);

        $this->cryptMock->expects(self::once())
            ->method('generateRandomString')
            ->willReturn('rnd-value');

        $this->cryptMock->expects(self::once())
            ->method('createHash')
            ->willReturn('hash-value');

        $authHeader  = 'IYZWSv2 YXBpS2V5OmFwaS1rZXkmcmFuZG9tS2V5OnJuZC12YWx1ZSZzaWduYXR1cmU6aGFzaC12YWx1ZQ==';
        $requestMock = $this->prepareHttpRequest($jsonBody, [
            ['name' => 'Content-Type', 'value' => 'application/json'],
            ['name' => 'Authorization', 'value' => $authHeader],
        ]);

        $this->requestFactory->expects(self::once())
            ->method('createRequest')
            ->with('POST', $apiUrl)
            ->willReturn($requestMock);

        $response = $this->prepareHttpResponse('{"status":"success"}', 200);

        $this->psrClient->expects(self::once())
            ->method('sendRequest')
            ->with($requestMock)
            ->willReturn($response);

        $this->serializerMock->expects(self::once())
            ->method('decode')
            ->with('{"status":"success"}', $txType)
            ->willReturn(['status' => 'success']);

        $actual = $this->client->request($txType, $paymentModel, $requestData, $order, $apiUrl, $this->account);

        $this->assertSame(['status' => 'success'], $actual);
    }

    public function testRequestWithNonIyzicoAccountThrows(): void
    {
        $txType       = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel = PosInterface::MODEL_NON_SECURE;
        $jsonBody     = '{"data":"x"}';
        $encodedData  = new EncodedData($jsonBody, SerializerInterface::FORMAT_JSON);

        $this->serializerMock->expects(self::once())
            ->method('encode')
            ->willReturn($encodedData);

        $this->requestFactory->expects(self::never())
            ->method('createRequest');

        $wrongAccount = $this->createMock(\Mews\Pos\Entity\Account\AbstractPosAccount::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->client->request($txType, $paymentModel, ['data' => 'x'], [], null, $wrongAccount);
    }

    public function testRequestHandles204Response(): void
    {
        $txType       = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel = PosInterface::MODEL_NON_SECURE;
        $requestData  = ['key' => 'val'];
        $apiUrl       = self::BASE_URL.'/payment/auth';
        $jsonBody     = '{"key":"val"}';
        $encodedData  = new EncodedData($jsonBody, SerializerInterface::FORMAT_JSON);

        $this->serializerMock->expects(self::once())
            ->method('encode')
            ->willReturn($encodedData);

        $this->cryptMock->expects(self::once())
            ->method('generateRandomString')
            ->willReturn('rnd');

        $this->cryptMock->expects(self::once())
            ->method('createHash')
            ->willReturn('hash');

        $authHeader  = 'IYZWSv2 YXBpS2V5OmFwaS1rZXkmcmFuZG9tS2V5OnJuZCZzaWduYXR1cmU6aGFzaA==';
        $requestMock = $this->prepareHttpRequest($jsonBody, [
            ['name' => 'Content-Type', 'value' => 'application/json'],
            ['name' => 'Authorization', 'value' => $authHeader],
        ]);

        $this->requestFactory->expects(self::once())
            ->method('createRequest')
            ->with('POST', $apiUrl)
            ->willReturn($requestMock);

        $this->psrClient->expects(self::once())
            ->method('sendRequest')
            ->willReturn($this->prepareHttpResponse('', 204));

        $this->serializerMock->expects(self::never())
            ->method('decode');

        $actual = $this->client->request($txType, $paymentModel, $requestData, [], $apiUrl, $this->account);

        $this->assertSame([], $actual);
    }

    public function testRequestFailsWith500(): void
    {
        $txType       = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel = PosInterface::MODEL_NON_SECURE;
        $requestData  = ['key' => 'val'];
        $apiUrl       = self::BASE_URL.'/payment/auth';
        $jsonBody     = '{"key":"val"}';
        $encodedData  = new EncodedData($jsonBody, SerializerInterface::FORMAT_JSON);

        $this->serializerMock->expects(self::once())
            ->method('encode')
            ->willReturn($encodedData);

        $this->cryptMock->expects(self::once())
            ->method('generateRandomString')
            ->willReturn('rnd');

        $this->cryptMock->expects(self::once())
            ->method('createHash')
            ->willReturn('hash');

        $authHeader  = 'IYZWSv2 YXBpS2V5OmFwaS1rZXkmcmFuZG9tS2V5OnJuZCZzaWduYXR1cmU6aGFzaA==';
        $requestMock = $this->prepareHttpRequest($jsonBody, [
            ['name' => 'Content-Type', 'value' => 'application/json'],
            ['name' => 'Authorization', 'value' => $authHeader],
        ]);

        $this->requestFactory->expects(self::once())
            ->method('createRequest')
            ->with('POST', $apiUrl)
            ->willReturn($requestMock);

        $this->psrClient->expects(self::once())
            ->method('sendRequest')
            ->willReturn($this->prepareHttpResponse('Internal Server Error', 500));

        $this->serializerMock->expects(self::never())
            ->method('decode');

        $this->expectException(\RuntimeException::class);
        $this->client->request($txType, $paymentModel, $requestData, [], $apiUrl, $this->account);
    }

    public function testRequestFailsWith4xx(): void
    {
        $txType       = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel = PosInterface::MODEL_NON_SECURE;
        $requestData  = ['locale' => 'tr'];
        $apiUrl       = self::BASE_URL.'/payment/auth';
        $jsonBody     = '{"locale":"tr"}';
        $encodedData  = new EncodedData($jsonBody, SerializerInterface::FORMAT_JSON);

        $this->serializerMock->expects(self::once())
            ->method('encode')
            ->willReturn($encodedData);

        $this->cryptMock->expects(self::once())
            ->method('generateRandomString')
            ->willReturn('rnd');

        $this->cryptMock->expects(self::once())
            ->method('createHash')
            ->willReturn('hash');

        $authHeader  = 'IYZWSv2 YXBpS2V5OmFwaS1rZXkmcmFuZG9tS2V5OnJuZCZzaWduYXR1cmU6aGFzaA==';
        $requestMock = $this->prepareHttpRequest($jsonBody, [
            ['name' => 'Content-Type', 'value' => 'application/json'],
            ['name' => 'Authorization', 'value' => $authHeader],
        ]);

        $this->requestFactory->expects(self::once())
            ->method('createRequest')
            ->with('POST', $apiUrl)
            ->willReturn($requestMock);

        $response = $this->prepareHttpResponse('{"errorMessage":"Invalid card","status":"failure"}', 400);

        $this->psrClient->expects(self::once())
            ->method('sendRequest')
            ->willReturn($response);

        $this->serializerMock->expects(self::once())
            ->method('decode')
            ->willReturn(['errorMessage' => 'Invalid card', 'status' => 'failure']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid card');

        $this->client->request($txType, $paymentModel, $requestData, [], $apiUrl, $this->account);
    }

    public static function supportsTxDataProvider(): array
    {
        return [
            [PosInterface::TX_TYPE_PAY_AUTH, true],
            [PosInterface::TX_TYPE_PAY_PRE_AUTH, true],
            [PosInterface::TX_TYPE_PAY_POST_AUTH, true],
            [PosInterface::TX_TYPE_CANCEL, true],
            [PosInterface::TX_TYPE_REFUND, true],
            [PosInterface::TX_TYPE_STATUS, true],
            [PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD, false],
            [PosInterface::TX_TYPE_ORDER_HISTORY, false],
            [PosInterface::TX_TYPE_HISTORY, false],
        ];
    }

    public static function getApiUrlDataProvider(): array
    {
        return [
            [PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_NON_SECURE, self::BASE_URL.'/payment/auth'],
            [PosInterface::TX_TYPE_PAY_PRE_AUTH, PosInterface::MODEL_NON_SECURE, self::BASE_URL.'/payment/preauth'],
            [PosInterface::TX_TYPE_PAY_POST_AUTH, PosInterface::MODEL_NON_SECURE, self::BASE_URL.'/payment/postauth'],
            [PosInterface::TX_TYPE_CANCEL, PosInterface::MODEL_NON_SECURE, self::BASE_URL.'/payment/cancel'],
            [PosInterface::TX_TYPE_STATUS, PosInterface::MODEL_NON_SECURE, self::BASE_URL.'/payment/detail'],
            [PosInterface::TX_TYPE_REFUND, PosInterface::MODEL_NON_SECURE, self::BASE_URL.'/v2/payment/refund'],
            [PosInterface::TX_TYPE_REFUND_PARTIAL, PosInterface::MODEL_NON_SECURE, self::BASE_URL.'/v2/payment/refund'],
            [PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_3D_SECURE, self::BASE_URL.'/payment/v2/3dsecure/auth'],
        ];
    }
}
