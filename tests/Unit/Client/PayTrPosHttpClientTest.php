<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Client;

use InvalidArgumentException;
use Generator;
use Mews\Pos\Client\AbstractHttpClient;
use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Client\PayTrPosHttpClient;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\Request\ValueMapper\RequestValueMapperInterface;
use Mews\Pos\Exception\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\PosHttpClientFactory;
use Mews\Pos\Gateway\AkbankPos;
use Mews\Pos\Gateway\PayTrPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

#[CoversClass(PayTrPosHttpClient::class)]
#[CoversClass(AbstractHttpClient::class)]
class PayTrPosHttpClientTest extends TestCase
{
    use HttpClientTestTrait;

    private PayTrPosHttpClient $client;

    /** @var LoggerInterface & MockObject */
    private MockObject $logger;

    /** @var ClientInterface & MockObject */
    private MockObject $psrClient;

    /** @var RequestFactoryInterface & MockObject */
    private MockObject $requestFactory;

    /** @var StreamFactoryInterface & MockObject */
    private MockObject $streamFactory;

    protected function setUp(): void
    {
        $this->logger         = $this->createMock(LoggerInterface::class);
        $crypt                = $this->createMock(CryptInterface::class);
        $requestValueMapper   = $this->createMock(RequestValueMapperInterface::class);
        $this->psrClient      = $this->createMock(ClientInterface::class);
        $this->requestFactory = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory  = $this->createMock(StreamFactoryInterface::class);

        $this->client = PosHttpClientFactory::create(
            PayTrPosHttpClient::class,
            'https://www.paytr.com',
            $crypt,
            $requestValueMapper,
            $this->logger,
            $this->psrClient,
            $this->requestFactory,
            $this->streamFactory
        );
    }

    public function testSupports(): void
    {
        $this->assertTrue($this->client::supports(PayTrPos::class, HttpClientInterface::API_NAME_PAYMENT_API));
        $this->assertFalse($this->client::supports(PayTrPos::class, HttpClientInterface::API_NAME_QUERY_API));
        $this->assertFalse($this->client::supports(AkbankPos::class, HttpClientInterface::API_NAME_PAYMENT_API));
    }

    public function testSupportsTx(): void
    {
        $this->assertTrue($this->client->supportsTx(PosInterface::TX_TYPE_PAY_AUTH, PosInterface::MODEL_3D_HOST));
        $this->assertTrue($this->client->supportsTx(PosInterface::TX_TYPE_HISTORY, PosInterface::MODEL_NON_SECURE));
        $this->assertTrue($this->client->supportsTx(PosInterface::TX_TYPE_REFUND, PosInterface::MODEL_NON_SECURE));
    }

    #[DataProvider('getApiUrlDataProvider')]
    /**
     * @dataProvider getApiUrlDataProvider
     */
    public function testGetApiUrl(string $txType, string $expected): void
    {
        $this->assertSame($expected, $this->client->getApiURL($txType));
    }

    #[TestWith([null, InvalidArgumentException::class, 'Transaction type is required to generate PayTR API URL'])]
    #[TestWith([PosInterface::TX_TYPE_CANCEL, UnsupportedTransactionTypeException::class, 'Unsupported transaction type!'])]
    public function testGetApiUrlNullThrows(?string $txType, string $expectedExceptionClass, string $expectedExpMsg): void
    {
        $this->expectException($expectedExceptionClass);
        $this->expectExceptionMessage($expectedExpMsg);
        $this->client->getApiURL($txType);
    }

    #[DataProvider('requestDataProvider')]
    public function testRequest(
        string $txType,
        string $paymentModel,
        array  $requestData,
        string $encodedRequestData,
        array  $order,
        string $expectedApiUrl
    ): void {
        $responseContent = '{"status":"success","token":"abc123"}';

        $request = $this->prepareHttpRequest($encodedRequestData, [
            [
                'name'  => 'Content-Type',
                'value' => 'application/x-www-form-urlencoded',
            ],
        ]);

        $response = $this->prepareHttpResponse($responseContent, 200);

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
            $order
        );

        $this->assertSame(['status' => 'success', 'token' => 'abc123'], $actual);
    }

    public static function getApiUrlDataProvider(): array
    {
        return [
            'iframe_token' => [
                'txType'   => PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD,
                'expected' => 'https://www.paytr.com/odeme/api/get-token',
            ],
            'refund' => [
                'txType'   => PosInterface::TX_TYPE_REFUND,
                'expected' => 'https://www.paytr.com/odeme/iade',
            ],
            'partial_refund' => [
                'txType'   => PosInterface::TX_TYPE_REFUND_PARTIAL,
                'expected' => 'https://www.paytr.com/odeme/iade',
            ],
            'status' => [
                'txType'   => PosInterface::TX_TYPE_STATUS,
                'expected' => 'https://www.paytr.com/odeme/durum-sorgu',
            ],
            'payment' => [
                'txType'   => PosInterface::TX_TYPE_PAY_AUTH,
                'expected' => 'https://www.paytr.com/odeme',
            ],
            'custom_query' => [
                'txType'   => PosInterface::TX_TYPE_CUSTOM_QUERY,
                'expected' => 'https://www.paytr.com/odeme',
            ],
            'history' => [
                'txType'   => PosInterface::TX_TYPE_HISTORY,
                'expected' => 'https://www.paytr.com/rapor/islem-dokumu',
            ],
        ];
    }

    public static function requestDataProvider(): Generator
    {
        yield 'payment' => [
            'txType'             => PosInterface::TX_TYPE_PAY_AUTH,
            'paymentModel'       => PosInterface::MODEL_NON_SECURE,
            'requestData'        => ['merchant_id' => '123456', 'merchant_oid' => 'order-1'],
            'encodedRequestData' => 'merchant_id=123456&merchant_oid=order-1',
            'order'              => ['id' => 'order-1'],
            'expectedApiUrl'     => 'https://www.paytr.com/odeme',
        ];

        yield 'iframe_token' => [
            'txType'             => PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD,
            'paymentModel'       => PosInterface::MODEL_3D_HOST,
            'requestData'        => ['merchant_id' => '123456', 'merchant_oid' => 'order-2'],
            'encodedRequestData' => 'merchant_id=123456&merchant_oid=order-2',
            'order'              => ['id' => 'order-2'],
            'expectedApiUrl'     => 'https://www.paytr.com/odeme/api/get-token',
        ];
    }
}
