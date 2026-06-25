<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Client;

use InvalidArgumentException;
use LogicException;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\Model\Account\AbstractPosAccount;
use RuntimeException;
use Mews\Pos\Client\AbstractHttpClient;
use Mews\Pos\Client\AbstractIyzicoPosHttpClient;
use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Client\IyzicoPosHttpClient;
use Mews\Pos\Crypt\IyzicoPosCrypt;
use Mews\Pos\DataMapper\Request\ValueMapper\RequestValueMapperInterface;
use Mews\Pos\Model\Account\IyzicoPosAccount;
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

#[CoversClass(IyzicoPosHttpClient::class)]
#[CoversClass(AbstractIyzicoPosHttpClient::class)]
#[CoversClass(AbstractHttpClient::class)]
class IyzicoPosHttpClientTest extends TestCase
{
    use HttpClientTestTrait;

    private const BASE_URL = 'https://sandbox-api.iyzipay.com';

    private IyzicoPosHttpClient $client;

    private IyzicoPosAccount $account;

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

        $this->account        = AccountFactory::createIyzicoPosAccount('iyzico', 'api-key', 'secret-key');
        $this->loggerMock     = $this->createMock(LoggerInterface::class);
        $this->cryptMock      = $this->createMock(IyzicoPosCrypt::class);
        $this->psrClient      = $this->createMock(ClientInterface::class);
        $this->requestFactory = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory  = $this->createMock(StreamFactoryInterface::class);

        $this->client = PosHttpClientFactory::create(
            IyzicoPosHttpClient::class,
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
        $this->assertTrue($this->client::supports(IyzicoPos::class, HttpClientInterface::API_NAME_PAYMENT_API));
        $this->assertFalse($this->client::supports(IyzicoPos::class, HttpClientInterface::API_NAME_GATEWAY_3D_API));
        $this->assertFalse($this->client::supports(AkbankPos::class, HttpClientInterface::API_NAME_PAYMENT_API));
    }

    #[DataProvider('supportsTxDataProvider')]
    public function testSupportsTx(string $txType, bool $expected): void
    {
        $this->assertSame($expected, $this->client->supportsTx($txType, PosInterface::MODEL_NON_SECURE));
    }

    #[DataProvider('getApiUrlDataProvider')]
    public function testGetApiUrl(string $txType, ?string $paymentModel, ?string $orderTxType, string $expected): void
    {
        $actual = $this->client->getApiURL($txType, $paymentModel, $orderTxType);

        $this->assertSame($expected, $actual);
    }

    #[DataProvider('getApiUrlThrowsDataProvider')]
    public function testGetApiUrlThrows(?string $txType, ?string $paymentModel, ?string $orderTxType): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->client->getApiURL($txType, $paymentModel, $orderTxType);
    }

    public function testConstructorRejectsNonIyzicoCrypt(): void
    {
        $this->expectException(LogicException::class);

        $wrongCrypt = $this->createMock(CryptInterface::class);

        PosHttpClientFactory::create(
            IyzicoPosHttpClient::class,
            self::BASE_URL,
            $wrongCrypt,
            $this->createMock(RequestValueMapperInterface::class),
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

        $jsonBody    = '{"locale":"tr","price":100.0}';


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

        $actual = $this->client->request($txType, $paymentModel, $requestData, $order, $apiUrl, $this->account);

        $this->assertSame(['status' => 'success'], $actual);
    }

    public function testRequestWithNonIyzicoAccountThrows(): void
    {
        $txType       = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel = PosInterface::MODEL_NON_SECURE;

        $this->requestFactory->expects(self::never())
            ->method('createRequest');

        $wrongAccount = $this->createMock(AbstractPosAccount::class);

        $this->expectException(InvalidArgumentException::class);
        $this->client->request($txType, $paymentModel, ['data' => 'x'], [], null, $wrongAccount);
    }

    public function testRequestHandles204Response(): void
    {
        $txType       = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel = PosInterface::MODEL_NON_SECURE;
        $requestData  = ['key' => 'val'];
        $apiUrl       = self::BASE_URL.'/payment/auth';
        $jsonBody     = '{"key":"val"}';

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

        $this->expectException(RuntimeException::class);
        $this->client->request($txType, $paymentModel, $requestData, [], $apiUrl, $this->account);
    }

    public function testRequestFailsWith4xx(): void
    {
        $txType       = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel = PosInterface::MODEL_NON_SECURE;
        $requestData  = ['locale' => 'tr'];
        $apiUrl       = self::BASE_URL.'/payment/auth';
        $jsonBody     = '{"locale":"tr"}';

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

        $this->expectException(RuntimeException::class);
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
            [PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD, true],
            [PosInterface::TX_TYPE_INTERNAL_3D_PAYMENT_STATUS, true],
            [PosInterface::TX_TYPE_ORDER_HISTORY, false],
            [PosInterface::TX_TYPE_HISTORY, false],
        ];
    }

    public static function getApiUrlDataProvider(): array
    {
        return [
            [PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_NON_SECURE, null, self::BASE_URL.'/payment/auth'],
            [PosInterface::TX_TYPE_PAY_PRE_AUTH, PosInterface::MODEL_NON_SECURE, null, self::BASE_URL.'/payment/preauth'],
            [PosInterface::TX_TYPE_PAY_POST_AUTH, PosInterface::MODEL_NON_SECURE, null, self::BASE_URL.'/payment/postauth'],
            [PosInterface::TX_TYPE_CANCEL, PosInterface::MODEL_NON_SECURE, null, self::BASE_URL.'/payment/cancel'],
            [PosInterface::TX_TYPE_STATUS, PosInterface::MODEL_NON_SECURE, null, self::BASE_URL.'/payment/detail'],
            [PosInterface::TX_TYPE_REFUND, PosInterface::MODEL_NON_SECURE, null, self::BASE_URL.'/v2/payment/refund'],
            [PosInterface::TX_TYPE_REFUND_PARTIAL, PosInterface::MODEL_NON_SECURE, null, self::BASE_URL.'/v2/payment/refund'],
            [PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_3D_SECURE, null, self::BASE_URL.'/payment/v2/3dsecure/auth'],
            [PosInterface::TX_TYPE_PAY_PRE_AUTH, PosInterface::MODEL_3D_SECURE, null, self::BASE_URL.'/payment/v2/3dsecure/auth'],
            [PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD, PosInterface::MODEL_3D_SECURE, PosInterface::TX_TYPE_PAY_AUTH, self::BASE_URL.'/payment/3dsecure/initialize'],
            [PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD, PosInterface::MODEL_3D_SECURE, PosInterface::TX_TYPE_PAY_PRE_AUTH, self::BASE_URL.'/payment/3dsecure/initialize/preauth'],
            [PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD, PosInterface::MODEL_3D_HOST, PosInterface::TX_TYPE_PAY_AUTH, self::BASE_URL.'/payment/iyzipos/checkoutform/initialize/auth/ecom'],
            [PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD, PosInterface::MODEL_3D_HOST, PosInterface::TX_TYPE_PAY_PRE_AUTH, self::BASE_URL.'/payment/iyzipos/checkoutform/initialize/preauth/ecom'],
            [PosInterface::TX_TYPE_INTERNAL_3D_PAYMENT_STATUS, PosInterface::MODEL_3D_HOST, null, self::BASE_URL.'/payment/iyzipos/checkoutform/auth/ecom/detail'],
        ];
    }

    public static function getApiUrlThrowsDataProvider(): array
    {
        return [
            'missing_tx_type'                    => [null, null, null],
            '3d_host_form_build_missing_order_tx' => [PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD, PosInterface::MODEL_3D_HOST, null],
        ];
    }
}
