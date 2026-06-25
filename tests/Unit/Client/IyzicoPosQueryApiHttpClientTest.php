<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Client;

use InvalidArgumentException;
use RuntimeException;
use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Client\AbstractHttpClient;
use Mews\Pos\Client\AbstractIyzicoPosHttpClient;
use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Client\IyzicoPosQueryApiHttpClient;
use Mews\Pos\Crypt\IyzicoPosCrypt;
use Mews\Pos\DataMapper\Request\ValueMapper\RequestValueMapperInterface;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\PosHttpClientFactory;
use Mews\Pos\Gateway\AkbankPos;
use Mews\Pos\Gateway\IyzicoPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

#[CoversClass(IyzicoPosQueryApiHttpClient::class)]
#[CoversClass(AbstractIyzicoPosHttpClient::class)]
#[CoversClass(AbstractHttpClient::class)]
class IyzicoPosQueryApiHttpClientTest extends TestCase
{
    use HttpClientTestTrait;

    private const BASE_URL = 'https://sandbox-api.iyzipay.com/v2/reporting/payment';

    private IyzicoPosQueryApiHttpClient $client;

    /** @var LoggerInterface & MockObject */
    private MockObject $loggerMock;

    /** @var ClientInterface & MockObject */
    private MockObject $psrClient;

    /** @var RequestFactoryInterface & MockObject */
    private MockObject $requestFactory;

    /** @var StreamFactoryInterface & MockObject */
    private MockObject $streamFactory;

    /** @var IyzicoPosCrypt & MockObject */
    private MockObject $cryptMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loggerMock     = $this->createMock(LoggerInterface::class);
        $this->cryptMock      = $this->createMock(IyzicoPosCrypt::class);
        $this->psrClient      = $this->createMock(ClientInterface::class);
        $this->requestFactory = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory  = $this->createMock(StreamFactoryInterface::class);

        $this->client = PosHttpClientFactory::create(
            IyzicoPosQueryApiHttpClient::class,
            self::BASE_URL,
            $this->cryptMock,
            $this->createMock(RequestValueMapperInterface::class),
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

    #[DataProvider('supportsTxDataProvider')]
    public function testSupportsTx(string $txType, bool $expected): void
    {
        $this->assertSame($expected, $this->client->supportsTx($txType, PosInterface::MODEL_NON_SECURE));
    }

    #[DataProvider('getApiUrlDataProvider')]
    public function testGetApiUrl(string $txType, string $expected): void
    {
        $actual = $this->client->getApiURL($txType);

        $this->assertSame($expected, $actual);
    }

    public function testGetApiUrlWithoutTxTypeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->client->getApiURL();
    }

    public function testRequestSendsGetWithQueryParams(): void
    {
        $txType       = PosInterface::TX_TYPE_HISTORY;
        $paymentModel = PosInterface::MODEL_NON_SECURE;
        $requestData  = ['transactionDate' => '2024-01-01', 'page' => 1];
        $order        = [];
        $account      = AccountFactory::createIyzicoPosAccount('iyzico', 'key', 'secret');

        $formBody    = 'transactionDate=2024-01-01&page=1';
        $apiUrl      = self::BASE_URL . '/transactions';

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
        $account      = AccountFactory::createIyzicoPosAccount('iyzico', 'key', 'secret');

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
        $account      = AccountFactory::createIyzicoPosAccount('iyzico', 'key', 'secret');

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

        $this->expectException(RuntimeException::class);
        $this->client->request($txType, $paymentModel, $requestData, [], $apiUrl, $account);
    }

    public function testRequestWithNonIyzicoAccountThrows(): void
    {
        $txType      = PosInterface::TX_TYPE_HISTORY;
        $paymentModel = PosInterface::MODEL_NON_SECURE;

        $wrongAccount = $this->createMock(AbstractPosAccount::class);

        $this->expectException(InvalidArgumentException::class);
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
