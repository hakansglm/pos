<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Client;

use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Client\IyzicoPos3DFormHttpClient;
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
 * @covers \Mews\Pos\Client\IyzicoPos3DFormHttpClient
 * @covers \Mews\Pos\Client\AbstractIyzicoPosHttpClient
 * @covers \Mews\Pos\Client\AbstractHttpClient
 */
class IyzicoPos3DFormHttpClientTest extends TestCase
{
    use HttpClientTestTrait;

    private const BASE_URL = 'https://sandbox-api.iyzipay.com';

    private IyzicoPos3DFormHttpClient $client;

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
            IyzicoPos3DFormHttpClient::class,
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
        $this->assertTrue($this->client::supports(IyzicoPos::class, HttpClientInterface::API_NAME_GATEWAY_3D_API));
        $this->assertFalse($this->client::supports(IyzicoPos::class, HttpClientInterface::API_NAME_PAYMENT_API));
        $this->assertFalse($this->client::supports(AkbankPos::class, HttpClientInterface::API_NAME_GATEWAY_3D_API));
    }

    public function testSupportsTx(): void
    {
        $this->assertTrue($this->client->supportsTx(PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD, PosInterface::MODEL_3D_SECURE));
        $this->assertFalse($this->client->supportsTx(PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_3D_SECURE));
    }

    /**
     * @dataProvider getApiUrlDataProvider
     */
    public function testGetApiUrl(string $txType, string $paymentModel, string $expected): void
    {
        $actual = $this->client->getApiURL($txType, $paymentModel);

        $this->assertSame($expected, $actual);
    }

    public function testGetApiUrlWithoutTxTypeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->client->getApiURL(null, PosInterface::MODEL_3D_SECURE);
    }

    public function testRequest(): void
    {
        $txType      = PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD;
        $paymentModel = PosInterface::MODEL_3D_SECURE;
        $requestData = ['locale' => 'tr'];
        $order       = ['id' => 'order-1'];
        $account     = AccountFactory::createIyzicoPosAccount('iyzico', 'key', 'secret');
        $apiUrl      = self::BASE_URL . '/payment/3dsecure/initialize';

        $jsonBody    = '{"locale":"tr"}';
        $encodedData = new EncodedData($jsonBody, SerializerInterface::FORMAT_JSON);

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
        $requestMock = $this->prepareHttpRequest($jsonBody, [
            ['name' => 'Content-Type', 'value' => 'application/json'],
            ['name' => 'Authorization', 'value' => $authHeader],
        ]);

        $response = $this->prepareHttpResponse('{"status":"success","threeDSHtmlContent":"abc"}', 200);

        $this->requestFactory->expects(self::once())
            ->method('createRequest')
            ->with('POST', $apiUrl)
            ->willReturn($requestMock);

        $this->psrClient->expects(self::once())
            ->method('sendRequest')
            ->with($requestMock)
            ->willReturn($response);

        $decoded = ['status' => 'success', 'threeDSHtmlContent' => 'abc'];
        $this->serializerMock->expects(self::once())
            ->method('decode')
            ->willReturn($decoded);

        $actual = $this->client->request($txType, $paymentModel, $requestData, $order, $apiUrl, $account);

        $this->assertSame($decoded, $actual);
    }

    public function testRequestHandles204Response(): void
    {
        $txType       = PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD;
        $paymentModel = PosInterface::MODEL_3D_SECURE;
        $requestData  = ['locale' => 'tr'];
        $apiUrl       = self::BASE_URL . '/payment/3dsecure/initialize';
        $jsonBody     = '{"locale":"tr"}';
        $encodedData  = new EncodedData($jsonBody, SerializerInterface::FORMAT_JSON);
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

        $actual = $this->client->request($txType, $paymentModel, $requestData, [], $apiUrl, $account);

        $this->assertSame([], $actual);
    }

    public function testRequestFailsWith500(): void
    {
        $txType       = PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD;
        $paymentModel = PosInterface::MODEL_3D_SECURE;
        $requestData  = ['locale' => 'tr'];
        $apiUrl       = self::BASE_URL . '/payment/3dsecure/initialize';
        $jsonBody     = '{"locale":"tr"}';
        $encodedData  = new EncodedData($jsonBody, SerializerInterface::FORMAT_JSON);
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
        $this->client->request($txType, $paymentModel, $requestData, [], $apiUrl, $account);
    }

    public function testRequestWithNonIyzicoAccountThrows(): void
    {
        $txType      = PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD;
        $paymentModel = PosInterface::MODEL_3D_SECURE;
        $jsonBody    = '{"data":"x"}';
        $encodedData = new EncodedData($jsonBody, SerializerInterface::FORMAT_JSON);

        $this->serializerMock->expects(self::once())
            ->method('encode')
            ->willReturn($encodedData);

        $wrongAccount = $this->createMock(\Mews\Pos\Entity\Account\AbstractPosAccount::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->client->request($txType, $paymentModel, ['data' => 'x'], [], null, $wrongAccount);
    }

    public static function getApiUrlDataProvider(): array
    {
        return [
            '3d_secure_auth' => [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'expected'     => self::BASE_URL . '/payment/3dsecure/initialize',
            ],
            '3d_secure_pre_auth' => [
                'txType'       => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'expected'     => self::BASE_URL . '/payment/3dsecure/initialize/preauth',
            ],
            '3d_host_auth' => [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_HOST,
                'expected'     => self::BASE_URL . '/payment/iyzipos/checkoutform/initialize/auth/ecom',
            ],
            '3d_host_pre_auth' => [
                'txType'       => PosInterface::TX_TYPE_PAY_PRE_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_HOST,
                'expected'     => self::BASE_URL . '/payment/iyzipos/checkoutform/initialize/preauth/ecom',
            ],
            '3d_host_status' => [
                'txType'       => PosInterface::TX_TYPE_STATUS,
                'paymentModel' => PosInterface::MODEL_3D_HOST,
                'expected'     => self::BASE_URL . '/payment/iyzipos/checkoutform/auth/ecom/detail',
            ],
        ];
    }
}
