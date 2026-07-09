<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\PosQuery;

use DateTimeImmutable;
use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Client\HttpClientStrategyInterface;
use Mews\Pos\DataMapper\PosQuery\Request\QueryRequestDataMapperInterface;
use Mews\Pos\DataMapper\PosQuery\Response\QueryResponseDataMapperInterface;
use Mews\Pos\Event\PosQueryRequestDataPreparedEvent;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Gateway\AkbankPos;
use Mews\Pos\Gateway\PayForPos;
use Mews\Pos\Model\Account\PayForPosAccount;
use Mews\Pos\PosInterface;
use Mews\Pos\PosQuery\AbstractMappedPosQuery;
use Mews\Pos\PosQuery\AbstractPosQuery;
use Mews\Pos\PosQuery\PayForPosQuery;
use Mews\Pos\PosQuery\PosQueryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

#[CoversClass(PayForPosQuery::class)]
#[CoversClass(AbstractMappedPosQuery::class)]
#[CoversClass(AbstractPosQuery::class)]
class PayForPosQueryTest extends TestCase
{
    private PayForPosAccount $account;

    private PayForPosQuery $posQuery;

    /** @var QueryRequestDataMapperInterface & MockObject */
    private MockObject $requestMapperMock;

    /** @var QueryResponseDataMapperInterface & MockObject */
    private MockObject $responseMapperMock;

    /** @var HttpClientStrategyInterface & MockObject */
    private MockObject $httpClientStrategyMock;

    /** @var HttpClientInterface & MockObject */
    private MockObject $httpClientMock;

    /** @var EventDispatcherInterface & MockObject */
    private MockObject $eventDispatcherMock;

    /** @var LoggerInterface & MockObject */
    private MockObject $loggerMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createPayForPosAccount(
            'qnbfinansbank-payfor',
            '085300000009704',
            'QNB_API_KULLANICI_3DPAY',
            'UcBN0',
            '12345678'
        );

        $this->requestMapperMock      = $this->createMock(QueryRequestDataMapperInterface::class);
        $this->responseMapperMock     = $this->createMock(QueryResponseDataMapperInterface::class);
        $this->httpClientStrategyMock = $this->createMock(HttpClientStrategyInterface::class);
        $this->httpClientMock         = $this->createMock(HttpClientInterface::class);
        $this->eventDispatcherMock    = $this->createMock(EventDispatcherInterface::class);
        $this->loggerMock             = $this->createMock(LoggerInterface::class);

        $this->posQuery = $this->createPosQuery();
    }

    public function testInit(): void
    {
        $this->assertNull($this->posQuery->getResponse());
        $this->assertFalse($this->posQuery->isSuccess());
        $this->assertFalse($this->posQuery->isTestMode());
        $this->assertSame($this->account, $this->posQuery->getAccount());
    }

    public function testSupports(): void
    {
        $this->assertTrue(PayForPosQuery::supports(PayForPos::class));
        $this->assertFalse(PayForPosQuery::supports(AkbankPos::class));
    }

    public function testIsSupportedQuery(): void
    {
        $this->assertTrue(PayForPosQuery::isSupportedQuery(PosQueryInterface::QUERY_TYPE_CUSTOM_QUERY));
        $this->assertTrue(PayForPosQuery::isSupportedQuery(PosQueryInterface::QUERY_TYPE_HISTORY));
        $this->assertFalse(PayForPosQuery::isSupportedQuery(PosQueryInterface::QUERY_TYPE_INSTALLMENT_RATES));
        $this->assertFalse(PayForPosQuery::isSupportedQuery(PosQueryInterface::QUERY_TYPE_INSTALLMENT_PRICES));
        $this->assertFalse(PayForPosQuery::isSupportedQuery('unknown_query_type'));
    }

    public function testTestModeSetViaConfig(): void
    {
        $this->requestMapperMock->expects(self::once())
            ->method('setTestMode')
            ->with(true);

        $posQuery = new PayForPosQuery(
            ['gateway_configs' => ['test_mode' => true]],
            $this->account,
            $this->requestMapperMock,
            $this->responseMapperMock,
            $this->httpClientStrategyMock,
            $this->eventDispatcherMock,
            $this->loggerMock
        );

        $this->assertTrue($posQuery->isTestMode());
    }

    public function testCustomQuery(): void
    {
        $inputData    = ['bin' => '415956'];
        $requestData  = ['bin' => '415956', 'MbrId' => '5'];
        $bankResponse = ['status' => 'success'];

        $this->requestMapperMock->expects(self::once())
            ->method('createCustomQueryRequestData')
            ->with($this->account, $inputData)
            ->willReturn($requestData);

        $this->configureClientResponse(
            PosQueryInterface::QUERY_TYPE_CUSTOM_QUERY,
            $requestData,
            $bankResponse,
            $inputData
        );

        $result = $this->posQuery->customQuery($inputData);

        $this->assertSame($bankResponse, $result);
        $this->assertNull($this->posQuery->getResponse());
        $this->assertFalse($this->posQuery->isSuccess());
    }

    #[DataProvider('historyResponseDataProvider')]
    public function testHistory(array $bankResponse, array $mapped, bool $expectedSuccess): void
    {
        $data        = ['start_date' => new DateTimeImmutable()];
        $requestData = ['startDate' => '2024-01-01'];

        $this->requestMapperMock->expects(self::once())
            ->method('createHistoryRequestData')
            ->with($this->account, $data)
            ->willReturn($requestData);

        $this->responseMapperMock->expects(self::once())
            ->method('mapHistoryResponse')
            ->with($bankResponse)
            ->willReturn($mapped);

        $this->configureClientResponse(
            PosQueryInterface::QUERY_TYPE_HISTORY,
            $requestData,
            $bankResponse,
            $data
        );

        $result = $this->posQuery->history($data);

        $this->assertSame($mapped, $result);
        $this->assertSame($mapped, $this->posQuery->getResponse());
        $this->assertSame($expectedSuccess, $this->posQuery->isSuccess());
    }

    /**
     * @return array<string, array{array<string, mixed>, array<string, mixed>, bool}>
     */
    public static function historyResponseDataProvider(): array
    {
        return [
            'approved' => [
                ['transactions' => []],
                ['status' => 'approved', 'transactions' => []],
                true,
            ],
            'declined' => [
                ['error' => 'no transactions'],
                ['status' => 'declined', 'transactions' => []],
                false,
            ],
        ];
    }

    private function createPosQuery(): PayForPosQuery
    {
        return new PayForPosQuery(
            [],
            $this->account,
            $this->requestMapperMock,
            $this->responseMapperMock,
            $this->httpClientStrategyMock,
            $this->eventDispatcherMock,
            $this->loggerMock
        );
    }

    private function configureClientResponse(
        string $txType,
        array  $requestData,
        array  $decodedResponse,
        array  $originalData
    ): void {
        $updatedEvent = null;

        $this->httpClientStrategyMock->expects(self::once())
            ->method('getClient')
            ->with($txType, PosInterface::MODEL_NON_SECURE)
            ->willReturn($this->httpClientMock);

        $this->httpClientMock->expects(self::once())
            ->method('request')
            ->with(
                $txType,
                PosInterface::MODEL_NON_SECURE,
                $this->callback(fn (array $data): bool => ($data['test-update-request-data-with-event'] ?? false) === true),
                $originalData,
                null,
                $this->account
            )
            ->willReturn($decodedResponse);

        $this->eventDispatcherMock->expects(self::once())
            ->method('dispatch')
            ->with($this->logicalAnd(
                $this->isInstanceOf(PosQueryRequestDataPreparedEvent::class),
                $this->callback(
                    function (PosQueryRequestDataPreparedEvent $event) use ($requestData, $txType, $originalData, &$updatedEvent): bool {
                        $updatedEvent = $event;

                        return $txType === $event->getTxType()
                            && $requestData === $event->getRequestData()
                            && $originalData === $event->getOriginalData()
                            && $this->account->getBankName() === $event->getBankName();
                    }
                )
            ))
            ->willReturnCallback(function () use (&$updatedEvent): PosQueryRequestDataPreparedEvent {
                /** @var PosQueryRequestDataPreparedEvent $updatedEvent */
                $updatedData                                        = $updatedEvent->getRequestData();
                $updatedData['test-update-request-data-with-event'] = true;
                $updatedEvent->setRequestData($updatedData);

                return $updatedEvent;
            });
    }
}
