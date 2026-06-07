<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Client;

use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Client\IyzicoPosQueryApiHttpClient;
use Mews\Pos\Crypt\IyzicoPosCrypt;
use Mews\Pos\DataMapper\RequestValueMapper\IyzicoPosRequestValueMapper;
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
 * @covers \Mews\Pos\Client\IyzicoPosQueryApiHttpClient
 * @covers \Mews\Pos\Client\AbstractIyzicoPosHttpClient
 * @covers \Mews\Pos\Client\AbstractHttpClient
 */
class IyzicoPosQueryApiHttpClientTest extends TestCase
{
    use HttpClientTestTrait;

    private const BASE_URL = 'https://sandbox-api.iyzipay.com/v2/reporting/payment';

    private IyzicoPosQueryApiHttpClient $client;

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

        $this->serializerMock = $this->createMock(SerializerInterface::class);
        $this->loggerMock     = $this->createMock(LoggerInterface::class);
        $this->cryptMock      = $this->createMock(IyzicoPosCrypt::class);
        $this->psrClient      = $this->createMock(ClientInterface::class);
        $this->requestFactory = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory  = $this->createMock(StreamFactoryInterface::class);

        $this->client = PosHttpClientFactory::create(
            IyzicoPosQueryApiHttpClient::class,
            self::BASE_URL,
            $this->serializerMock,
            $this->cryptMock,
            new IyzicoPosRequestValueMapper(),
            $this->loggerMock,
            $this->psrClient,
            $this->requestFactory,
            $this->streamFactory
        );
    }

    public function testSupports(): void
    {
        $this->assertTrue($this->client::supports(IyzicoPos::class, HttpClientInterface::API_NAME_QUERY_API));
        $this->assertFalse($this->client::supports(IyzicoPos::class, HttpClientInterface::API_NAME_PAYMENT_API));
        $this->assertFalse($this->client::supports(AkbankPos::class, HttpClientInterface::API_NAME_QUERY_API));
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
    public function testGetApiUrl(string $txType, string $expected): void
    {
        $actual = $this->client->getApiURL($txType);

        $this->assertSame($expected, $actual);
    }

    public function testGetApiUrlWithoutTxTypeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->client->getApiURL(null);
    }

    public function testRequestSendsGetWithQueryParams(): void
    {
        $txType       = PosInterface::TX_TYPE_HISTORY;
        $paymentModel = PosInterface::MODEL_NON_SECURE;
        $requestData  = ['transactionDate' => '2024-01-01', 'page' => 1];
        $order        = [];
        $account      = AccountFactory::createIyzicoPosAccount('iyzico', 'key', 'secret');

        $formBody    = 'transactionDate=2024-01-01&page=1';
        $encodedData = new EncodedData($formBody, SerializerInterface::FORMAT_FORM);
        $apiUrl      = self::BASE_URL . '/transactions';

        $this->serializerMock->expects(self::once())
            ->method('encode')
            ->with($requestData, $txType)
            ->willReturn($encodedData);

        $this->cryptMock->expects(self::once())
            ->method('generateRandomString')
            ->willReturn('rnd');

        $this->cryptMock->expects(self::once())
            ->method('createHash')
            ->willReturn('hash');

        $authHeader  = 'IYZWSv2 YXBpS2V5OmtleSZyYW5kb21LZXk6cm5kJnNpZ25hdHVyZTpoYXNo';
        $requestMock = $this->prepareHttpRequest(null, [
            ['name' => 'Content-Type', 'value' => 'application/json'],
            ['name' => 'Authorization', 'value' => $authHeader],
        ]);

        $this->requestFactory->expects(self::once())
            ->method('createRequest')
            ->with('GET', $apiUrl . '?' . $formBody)
            ->willReturn($requestMock);

        $responseBody = '{"status":"success","transactions":[]}';
        $response     = $this->prepareHttpResponse($responseBody, 200);

        $this->psrClient->expects(self::once())
            ->method('sendRequest')
            ->with($requestMock)
            ->willReturn($response);

        $decoded = ['status' => 'success', 'transactions' => []];
        $this->serializerMock->expects(self::once())
            ->method('decode')
            ->with($responseBody, $txType)
            ->willReturn($decoded);

        $actual = $this->client->request($txType, $paymentModel, $requestData, $order, $apiUrl, $account);

        $this->assertSame($decoded, $actual);
    }

    public function testRequestHandles204Response(): void
    {
        $txType       = PosInterface::TX_TYPE_HISTORY;
        $paymentModel = PosInterface::MODEL_NON_SECURE;
        $requestData  = ['transactionDate' => '2024-01-01'];
        $apiUrl       = self::BASE_URL . '/transactions';
        $formBody     = 'transactionDate=2024-01-01';
        $encodedData  = new EncodedData($formBody, SerializerInterface::FORMAT_FORM);
        $account      = AccountFactory::createIyzicoPosAccount('iyzico', 'key', 'secret');

        $this->serializerMock->expects(self::once())
            ->method('encode')
            ->willReturn($encodedData);

        $this->cryptMock->expects(self::once())
            ->method('generateRandomString')
            ->willReturn('rnd');

        $this->cryptMock->expects(self::once())
            ->method('createHash')
            ->willReturn('hash');

        $authHeader  = 'IYZWSv2 YXBpS2V5OmtleSZyYW5kb21LZXk6cm5kJnNpZ25hdHVyZTpoYXNo';
        $requestMock = $this->prepareHttpRequest(null, [
            ['name' => 'Content-Type', 'value' => 'application/json'],
            ['name' => 'Authorization', 'value' => $authHeader],
        ]);

        $this->requestFactory->expects(self::once())
            ->method('createRequest')
            ->with('GET', $apiUrl . '?' . $formBody)
            ->willReturn($requestMock);

        $this->psrClient->expects(self::once())
            ->method('sendRequest')
            ->willReturn($this->prepareHttpResponse('', 204));

        $this->serializerMock->expects(self::never())
            ->method('decode');

        $actual = $this->client->request($txType, $paymentModel, $requestData, [], $apiUrl, $account);

        $this->assertSame([], $actual);
    }

    public function testRequestFailsWith500(): void
    {
        $txType       = PosInterface::TX_TYPE_HISTORY;
        $paymentModel = PosInterface::MODEL_NON_SECURE;
        $requestData  = ['transactionDate' => '2024-01-01'];
        $apiUrl       = self::BASE_URL . '/transactions';
        $formBody     = 'transactionDate=2024-01-01';
        $encodedData  = new EncodedData($formBody, SerializerInterface::FORMAT_FORM);
        $account      = AccountFactory::createIyzicoPosAccount('iyzico', 'key', 'secret');

        $this->serializerMock->expects(self::once())
            ->method('encode')
            ->willReturn($encodedData);

        $this->cryptMock->expects(self::once())
            ->method('generateRandomString')
            ->willReturn('rnd');

        $this->cryptMock->expects(self::once())
            ->method('createHash')
            ->willReturn('hash');

        $authHeader  = 'IYZWSv2 YXBpS2V5OmtleSZyYW5kb21LZXk6cm5kJnNpZ25hdHVyZTpoYXNo';
        $requestMock = $this->prepareHttpRequest(null, [
            ['name' => 'Content-Type', 'value' => 'application/json'],
            ['name' => 'Authorization', 'value' => $authHeader],
        ]);

        $this->requestFactory->expects(self::once())
            ->method('createRequest')
            ->with('GET', $apiUrl . '?' . $formBody)
            ->willReturn($requestMock);

        $this->psrClient->expects(self::once())
            ->method('sendRequest')
            ->willReturn($this->prepareHttpResponse('Internal Server Error', 500));

        $this->serializerMock->expects(self::never())
            ->method('decode');

        $this->expectException(\RuntimeException::class);
        $this->client->request($txType, $paymentModel, $requestData, [], $apiUrl, $account);
    }

    public function testRequestWithNonIyzicoAccountThrows(): void
    {
        $txType      = PosInterface::TX_TYPE_HISTORY;
        $paymentModel = PosInterface::MODEL_NON_SECURE;
        $formBody    = 'transactionDate=2024-01-01';
        $encodedData = new EncodedData($formBody, SerializerInterface::FORMAT_FORM);

        $this->serializerMock->expects(self::once())
            ->method('encode')
            ->willReturn($encodedData);

        $wrongAccount = $this->createMock(\Mews\Pos\Entity\Account\AbstractPosAccount::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->client->request($txType, $paymentModel, ['transactionDate' => '2024-01-01'], [], self::BASE_URL . '/transactions', $wrongAccount);
    }

    public static function supportsTxDataProvider(): array
    {
        return [
            [PosInterface::TX_TYPE_HISTORY,       true],
            [PosInterface::TX_TYPE_ORDER_HISTORY, true],
            [PosInterface::TX_TYPE_PAY_AUTH,      false],
            [PosInterface::TX_TYPE_CANCEL,        false],
        ];
    }

    public static function getApiUrlDataProvider(): array
    {
        return [
            [PosInterface::TX_TYPE_HISTORY,       self::BASE_URL . '/transactions'],
            [PosInterface::TX_TYPE_ORDER_HISTORY, self::BASE_URL . '/details'],
        ];
    }
}
