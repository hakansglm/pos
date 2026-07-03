<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\PosQuery;

use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Client\HttpClientStrategyInterface;
use Mews\Pos\DataMapper\PosQuery\Request\QueryRequestDataMapperInterface;
use Mews\Pos\Event\PosQueryRequestDataPreparedEvent;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Gateway\AkbankPos;
use Mews\Pos\Gateway\InterPos;
use Mews\Pos\Model\Account\InterPosAccount;
use Mews\Pos\PosInterface;
use Mews\Pos\PosQuery\AbstractPosQuery;
use Mews\Pos\PosQuery\InterPosQuery;
use Mews\Pos\PosQuery\PosQueryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

#[CoversClass(InterPosQuery::class)]
#[CoversClass(AbstractPosQuery::class)]
class InterPosQueryTest extends TestCase
{
    private InterPosAccount $account;

    private InterPosQuery $posQuery;

    /** @var QueryRequestDataMapperInterface & MockObject */
    private MockObject $requestMapperMock;

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

        $this->account = AccountFactory::createInterPosAccount(
            'denizbank',
            '3123',
            'InterTestApi',
            '3',
            'gDg1N'
        );

        $this->requestMapperMock      = $this->createMock(QueryRequestDataMapperInterface::class);
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
        $this->assertTrue(InterPosQuery::supports(InterPos::class));
        $this->assertFalse(InterPosQuery::supports(AkbankPos::class));
    }

    public function testIsSupportedQuery(): void
    {
        $this->assertTrue(InterPosQuery::isSupportedQuery(PosQueryInterface::QUERY_TYPE_CUSTOM_QUERY));
        $this->assertFalse(InterPosQuery::isSupportedQuery(PosQueryInterface::QUERY_TYPE_HISTORY));
        $this->assertFalse(InterPosQuery::isSupportedQuery(PosQueryInterface::QUERY_TYPE_INSTALLMENT_RATES));
        $this->assertFalse(InterPosQuery::isSupportedQuery(PosQueryInterface::QUERY_TYPE_INSTALLMENT_PRICES));
        $this->assertFalse(InterPosQuery::isSupportedQuery('unknown_query_type'));
    }

    public function testTestModeSetViaConfig(): void
    {
        $this->requestMapperMock->expects(self::once())
            ->method('setTestMode')
            ->with(true);

        $posQuery = new InterPosQuery(
            ['gateway_configs' => ['test_mode' => true]],
            $this->account,
            $this->requestMapperMock,
            $this->httpClientStrategyMock,
            $this->eventDispatcherMock,
            $this->loggerMock
        );

        $this->assertTrue($posQuery->isTestMode());
    }

    public function testCustomQuery(): void
    {
        $inputData    = ['bin' => '415956'];
        $requestData  = ['bin' => '415956', 'ShopCode' => '3123'];
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

    private function createPosQuery(): InterPosQuery
    {
        return new InterPosQuery(
            [],
            $this->account,
            $this->requestMapperMock,
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
