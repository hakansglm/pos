<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\PosQuery;

use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Client\HttpClientStrategyInterface;
use Mews\Pos\DataMapper\PosQuery\Request\QueryRequestDataMapperInterface;
use Mews\Pos\DataMapper\PosQuery\Response\QueryResponseDataMapperInterface;
use Mews\Pos\Event\PosQueryRequestDataPreparedEvent;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Gateway\AkbankPos;
use Mews\Pos\Gateway\ParamPos;
use Mews\Pos\Model\Account\ParamPosAccount;
use Mews\Pos\PosInterface;
use Mews\Pos\PosQuery\AbstractMappedPosQuery;
use Mews\Pos\PosQuery\AbstractPosQuery;
use Mews\Pos\PosQuery\ParamPosQuery;
use Mews\Pos\PosQuery\PosQueryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

#[CoversClass(ParamPosQuery::class)]
#[CoversClass(AbstractMappedPosQuery::class)]
#[CoversClass(AbstractPosQuery::class)]
class ParamPosQueryTest extends TestCase
{
    private ParamPosAccount $account;

    private ParamPosQuery $posQuery;

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

        $this->account = AccountFactory::createParamPosAccount(
            'param-pos',
            '10738',
            'Test',
            'Test',
            '0c13d406-873b-403b-9c09-a5766840d98c'
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
        $this->assertTrue(ParamPosQuery::supports(ParamPos::class));
        $this->assertFalse(ParamPosQuery::supports(AkbankPos::class));
    }

    public function testIsSupportedQuery(): void
    {
        $this->assertTrue(ParamPosQuery::isSupportedQuery(PosQueryInterface::QUERY_TYPE_CUSTOM_QUERY));
        $this->assertTrue(ParamPosQuery::isSupportedQuery(PosQueryInterface::QUERY_TYPE_HISTORY));
        $this->assertTrue(ParamPosQuery::isSupportedQuery(PosQueryInterface::QUERY_TYPE_INSTALLMENT_RATES));
        $this->assertTrue(ParamPosQuery::isSupportedQuery(PosQueryInterface::QUERY_TYPE_BIN_LIST));
        $this->assertFalse(ParamPosQuery::isSupportedQuery(PosQueryInterface::QUERY_TYPE_INSTALLMENT_PRICES));
        $this->assertFalse(ParamPosQuery::isSupportedQuery('unknown_query_type'));
    }

    public function testTestModeSetViaConfig(): void
    {
        $this->requestMapperMock->expects(self::once())
            ->method('setTestMode')
            ->with(true);

        $posQuery = new ParamPosQuery(
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
        $requestData  = ['bin' => '415956', 'CLIENT_CODE' => '10738'];
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
        $data        = ['start_date' => new \DateTimeImmutable()];
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

    #[DataProvider('installmentRatesResponseDataProvider')]
    public function testGetInstallmentRates(array $bankResponse, array $mapped, bool $expectedSuccess): void
    {
        $params      = ['bin' => '415956'];
        $requestData = ['CLIENT_CODE' => '10738', 'BIN' => '415956'];

        $this->requestMapperMock->expects(self::once())
            ->method('createInstallmentRatesRequestData')
            ->with($this->account, $params)
            ->willReturn($requestData);

        $this->responseMapperMock->expects(self::once())
            ->method('mapInstallmentRatesResponse')
            ->with($bankResponse)
            ->willReturn($mapped);

        $this->configureClientResponse(
            PosQueryInterface::QUERY_TYPE_INSTALLMENT_RATES,
            $requestData,
            $bankResponse,
            $params
        );

        $result = $this->posQuery->getInstallmentRates($params);

        $this->assertSame($mapped, $result);
        $this->assertSame($mapped, $this->posQuery->getResponse());
        $this->assertSame($expectedSuccess, $this->posQuery->isSuccess());
    }

    /**
     * @return array<string, array{array<string, mixed>, array<string, mixed>, bool}>
     */
    public static function installmentRatesResponseDataProvider(): array
    {
        return [
            'approved' => [
                ['installmentRates' => []],
                ['status' => 'approved', 'installment_rates' => []],
                true,
            ],
            'declined' => [
                ['error' => 'invalid bin'],
                ['status' => 'declined', 'installment_rates' => []],
                false,
            ],
        ];
    }

    #[DataProvider('binListResponseDataProvider')]
    public function testGetBinList(array $bankResponse, array $mapped, bool $expectedSuccess): void
    {
        $params      = ['bin' => '415956'];
        $requestData = ['soap:Body' => ['BIN_SanalPos' => ['BIN' => '415956']]];

        $this->requestMapperMock->expects(self::once())
            ->method('createBinListRequestData')
            ->with($this->account, $params)
            ->willReturn($requestData);

        $this->responseMapperMock->expects(self::once())
            ->method('mapBinListResponse')
            ->with($bankResponse)
            ->willReturn($mapped);

        $this->configureClientResponse(
            PosQueryInterface::QUERY_TYPE_BIN_LIST,
            $requestData,
            $bankResponse,
            $params
        );

        $result = $this->posQuery->getBinList($params);

        $this->assertSame($mapped, $result);
        $this->assertSame($mapped, $this->posQuery->getResponse());
        $this->assertSame($expectedSuccess, $this->posQuery->isSuccess());
    }

    /**
     * @return array<string, array{array<string, mixed>, array<string, mixed>, bool}>
     */
    public static function binListResponseDataProvider(): array
    {
        return [
            'approved' => [
                ['BIN_SanalPosResponse' => ['BIN_SanalPosResult' => ['Sonuc' => 1]]],
                ['status' => 'approved', 'bin_list' => [['bin' => '415956']]],
                true,
            ],
            'declined' => [
                ['BIN_SanalPosResponse' => ['BIN_SanalPosResult' => ['Sonuc' => -1, 'Sonuc_Str' => 'BIN bulunamadı']]],
                ['status' => 'declined', 'bin_list' => []],
                false,
            ],
        ];
    }

    private function createPosQuery(): ParamPosQuery
    {
        return new ParamPosQuery(
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
