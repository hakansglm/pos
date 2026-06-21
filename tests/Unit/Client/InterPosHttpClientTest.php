<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Client;

use Mews\Pos\Client\AbstractHttpClient;
use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Client\InterPosHttpClient;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\Request\ValueMapper\RequestValueMapperInterface;
use Mews\Pos\Factory\PosHttpClientFactory;
use Mews\Pos\Gateway\AkbankPos;
use Mews\Pos\Gateway\InterPos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\Decoder\InterPosDecoder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

#[CoversClass(InterPosHttpClient::class)]
#[CoversClass(AbstractHttpClient::class)]
#[CoversClass(InterPosDecoder::class)]
class InterPosHttpClientTest extends TestCase
{
    use HttpClientTestTrait;

    private InterPosHttpClient $client;

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
            InterPosHttpClient::class,
            'https://test.inter-vpos.com.tr/mpi/Default.aspx',
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
        $this->assertTrue($this->client::supports(InterPos::class, HttpClientInterface::API_NAME_PAYMENT_API));
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
        array  $expectedDecodedResponse,
        array  $order,
        string $expectedApiUrl
    ): void {
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
            $order,
            $expectedApiUrl
        );

        $this->assertSame($expectedDecodedResponse, $actual);
    }

    public function testRequestUndecodableResponse(): void
    {
        $txType       = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel = PosInterface::MODEL_3D_SECURE;
        $requestData  = ['request-data' => 'abc'];
        $order        = ['id' => 123];
        $encodedBody  = 'request-data=abc';

        $request = $this->prepareHttpRequest($encodedBody, [
            [
                'name'  => 'Content-Type',
                'value' => 'application/x-www-form-urlencoded',
            ],
        ]);

        $responseContent = 'response-content';
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

    public function testRequestBadRequest(): void
    {
        $txType       = PosInterface::TX_TYPE_PAY_AUTH;
        $paymentModel = PosInterface::MODEL_3D_SECURE;
        $requestData  = ['request-data' => 'abc'];
        $order        = ['id' => 123];
        $encodedBody  = 'request-data=abc';

        $request = $this->prepareHttpRequest($encodedBody, [
            [
                'name'  => 'Content-Type',
                'value' => 'application/x-www-form-urlencoded',
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

    public static function getApiUrlDataProvider(): array
    {
        return [
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'expected'     => 'https://test.inter-vpos.com.tr/mpi/Default.aspx',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://test.inter-vpos.com.tr/mpi/Default.aspx',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_REFUND,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://test.inter-vpos.com.tr/mpi/Default.aspx',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_REFUND_PARTIAL,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://test.inter-vpos.com.tr/mpi/Default.aspx',
            ],
            [
                'txType'       => PosInterface::TX_TYPE_HISTORY,
                'paymentModel' => PosInterface::MODEL_NON_SECURE,
                'expected'     => 'https://test.inter-vpos.com.tr/mpi/Default.aspx',
            ],
        ];
    }

    public static function requestDataProvider(): \Generator
    {
        yield [
            'txType'                  => PosInterface::TX_TYPE_PAY_AUTH,
            'paymentModel'            => PosInterface::MODEL_3D_SECURE,
            'requestData'             => ['abc' => 1, 'sa' => 'aa'],
            'encodedRequestData'      => 'abc=1&sa=aa',
            'responseContent'         => 'OrderId=33554969;;ProcReturnCode=00;;HostRefNum=hostid;;AuthCode=gizlendi;;TxnResult=Success;;ErrorMessage=;;CampanyId=;;CampanyInstallCount=0;;CampanyShiftDateCount=0;;CampanyTxnId=;;CampanyType=;;CampanyInstallment=0;;CampanyDate=0;;CampanyAmnt=0;;TRXDATE=09.08.2024 10:40:34;;TransId=gizlendi;;ErrorCode=;;EarnedBonus=0,00;;UsedBonus=0,00;;AvailableBonus=0,00;;BonusToBonus=0;;CampaignBonus=0,00;;FoldedBonus=0;;SurchargeAmount=0;;Amount=1,00;;CardHolderName=gizlendi;;QrReferenceNumber=;;QrCardToken=;;QrData=;;QrPayIsSucess=False;;QrIssuerPaymentMethod=;;QrFastMessageReferenceNo=;;QrFastParticipantReceiverCode=;;QrFastParticipantReceiverName=;;QrFastParticipantSenderCode=;;QrFastSenderIban=;;QrFastParticipantSenderName=;;QrFastPaymentResultDesc=',
            'expectedDecodedResponse' => [
                'OrderId'                       => '33554969',
                'ProcReturnCode'                => '00',
                'HostRefNum'                    => 'hostid',
                'AuthCode'                      => 'gizlendi',
                'TxnResult'                     => 'Success',
                'ErrorMessage'                  => '',
                'CampanyId'                     => '',
                'CampanyInstallCount'           => '0',
                'CampanyShiftDateCount'         => '0',
                'CampanyTxnId'                  => '',
                'CampanyType'                   => '',
                'CampanyInstallment'            => '0',
                'CampanyDate'                   => '0',
                'CampanyAmnt'                   => '0',
                'TRXDATE'                       => '09.08.2024 10:40:34',
                'TransId'                       => 'gizlendi',
                'ErrorCode'                     => '',
                'EarnedBonus'                   => '0,00',
                'UsedBonus'                     => '0,00',
                'AvailableBonus'                => '0,00',
                'BonusToBonus'                  => '0',
                'CampaignBonus'                 => '0,00',
                'FoldedBonus'                   => '0',
                'SurchargeAmount'               => '0',
                'Amount'                        => '1,00',
                'CardHolderName'                => 'gizlendi',
                'QrReferenceNumber'             => '',
                'QrCardToken'                   => '',
                'QrData'                        => '',
                'QrPayIsSucess'                 => 'False',
                'QrIssuerPaymentMethod'         => '',
                'QrFastMessageReferenceNo'      => '',
                'QrFastParticipantReceiverCode' => '',
                'QrFastParticipantReceiverName' => '',
                'QrFastParticipantSenderCode'   => '',
                'QrFastSenderIban'              => '',
                'QrFastParticipantSenderName'   => '',
                'QrFastPaymentResultDesc'       => '',
            ],
            'order'                   => ['id' => 123],
            'expectedApiUrl'          => 'https://test.inter-vpos.com.tr/mpi/Default.aspx',
        ];
    }
}
