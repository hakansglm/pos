<?php

/**
 * @license MIT
 */

namespace Mews\Pos\PosQuery;

use Mews\Pos\Client\HttpClientStrategyInterface;
use Mews\Pos\DataMapper\PosQuery\Request\QueryRequestDataMapperInterface;
use Mews\Pos\DataMapper\PosQuery\Response\QueryResponseDataMapperInterface;
use Mews\Pos\Event\PosQueryRequestDataPreparedEvent;
use Mews\Pos\Exception\UnsupportedTransactionTypeException;
use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\PosInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractPosQuery implements PosQueryInterface
{
    /**
     * Declare supported query types per concrete subclass.
     *
     * @var array<string, bool>
     */
    protected static array $supportedQueries = [];

    /** @var array<string, mixed>|null */
    protected ?array $response = null;

    private bool $testMode = false;

    /**
     * @param array{
     *      gateway_configs?: array{
     *           test_mode?: bool
     *      }
     *  } $config
     */
    public function __construct(
        protected array                           $config,
        protected AbstractPosAccount              $account,
        protected QueryRequestDataMapperInterface $requestDataMapper,
        protected HttpClientStrategyInterface     $clientStrategy,
        protected EventDispatcherInterface        $eventDispatcher,
        protected LoggerInterface                 $logger
    ) {
        if (isset($this->config['gateway_configs']['test_mode'])) {
            $this->setTestMode($this->config['gateway_configs']['test_mode']);
        }
    }

    /**
     * @inheritDoc
     */
    public function customQuery(array $requestData, ?string $apiUrl = null): array
    {
        $txType       = PosQueryInterface::QUERY_TYPE_CUSTOM_QUERY;
        $paymentModel = PosInterface::MODEL_NON_SECURE;

        $enriched = $this->requestDataMapper->createCustomQueryRequestData($this->account, $requestData);
        $enriched = $this->dispatchEvent($txType, $enriched, $requestData);

        /** @var array<string, mixed> $bankResponse */
        $bankResponse = $this->clientStrategy->getClient($txType, $paymentModel)->request(
            $txType,
            $paymentModel,
            $enriched,
            $requestData,
            $apiUrl,
            $this->account
        );

        return $bankResponse;
    }

    /**
     * @inheritDoc
     */
    public function history(array $data): array
    {
        throw new UnsupportedTransactionTypeException(PosQueryInterface::QUERY_TYPE_HISTORY);
    }

    /**
     * @inheritDoc
     */
    public function getInstallmentRates(array $params): array
    {
        throw new UnsupportedTransactionTypeException(PosQueryInterface::QUERY_TYPE_INSTALLMENT_RATES);
    }

    /**
     * @inheritDoc
     */
    public function getInstallmentPrices(array $params): array
    {
        throw new UnsupportedTransactionTypeException(PosQueryInterface::QUERY_TYPE_INSTALLMENT_PRICES);
    }

    /**
     * @inheritDoc
     */
    public function getBinList(array $params): array
    {
        throw new UnsupportedTransactionTypeException(PosQueryInterface::QUERY_TYPE_BIN_LIST);
    }

    /**
     * @inheritDoc
     */
    public function getResponse(): ?array
    {
        return $this->response;
    }

    /**
     * @inheritDoc
     */
    public function isSuccess(): bool
    {
        return isset($this->response['status'])
            && QueryResponseDataMapperInterface::TX_STATUS_APPROVED === $this->response['status'];
    }

    /**
     * @inheritDoc
     */
    public static function isSupportedQuery(string $queryType): bool
    {
        return static::$supportedQueries[$queryType] ?? false;
    }

    public function getAccount(): AbstractPosAccount
    {
        return $this->account;
    }

    public function isTestMode(): bool
    {
        return $this->testMode;
    }

    /**
     * @phpstan-param PosQueryInterface::QUERY_TYPE_* $txType
     *
     * @param array<string, mixed> $requestData
     * @param array<string, mixed> $originalData
     *
     * @return array<string, mixed>
     */
    protected function dispatchEvent(string $txType, array $requestData, array $originalData): array
    {
        $event = new PosQueryRequestDataPreparedEvent(
            $requestData,
            $this->account->getBankName(),
            $txType,
            $originalData
        );

        /** @var PosQueryRequestDataPreparedEvent $event */
        $event = $this->eventDispatcher->dispatch($event);

        if ($requestData !== $event->getRequestData()) {
            $this->logger->debug('Request data is changed via listeners', [
                'tx_type'      => $txType,
                'bank_name'    => $this->account->getBankName(),
                'initial_data' => $requestData,
                'updated_data' => $event->getRequestData(),
            ]);
        }

        return $event->getRequestData();
    }

    /**
     * Enable/Disable test mode
     *
     * @param bool $testMode
     */
    private function setTestMode(bool $testMode): void
    {
        $this->testMode = $testMode;
        $this->requestDataMapper->setTestMode($testMode);
        $this->logger->debug('switching mode', ['is_test_mode' => $this->isTestMode()]);
    }
}
