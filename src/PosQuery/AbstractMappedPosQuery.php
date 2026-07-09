<?php

/**
 * @license MIT
 */

namespace Mews\Pos\PosQuery;

use Mews\Pos\Client\HttpClientStrategyInterface;
use Mews\Pos\DataMapper\PosQuery\Request\QueryRequestDataMapperInterface;
use Mews\Pos\DataMapper\PosQuery\Response\QueryResponseDataMapperInterface;
use Mews\Pos\Exception\UnsupportedTransactionTypeException;
use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\PosInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractMappedPosQuery extends AbstractPosQuery
{
    /**
     * @param array{
     *      gateway_configs?: array{
     *           test_mode?: bool
     *      }
     *  } $config
     */
    public function __construct(
        array                                     $config,
        AbstractPosAccount                        $account,
        QueryRequestDataMapperInterface           $requestDataMapper,
        protected QueryResponseDataMapperInterface $responseDataMapper,
        HttpClientStrategyInterface               $clientStrategy,
        EventDispatcherInterface                  $eventDispatcher,
        LoggerInterface                           $logger
    ) {
        parent::__construct($config, $account, $requestDataMapper, $clientStrategy, $eventDispatcher, $logger);
    }

    /**
     * @inheritDoc
     */
    public function history(array $data): array
    {
        if (!static::isSupportedQuery(PosQueryInterface::QUERY_TYPE_HISTORY)) {
            throw new UnsupportedTransactionTypeException(PosQueryInterface::QUERY_TYPE_HISTORY);
        }

        $txType       = PosQueryInterface::QUERY_TYPE_HISTORY;
        $paymentModel = PosInterface::MODEL_NON_SECURE;

        $requestData = $this->requestDataMapper->createHistoryRequestData($this->account, $data);
        $requestData = $this->dispatchEvent($txType, $requestData, $data);

        /** @var array<string, mixed> $bankResponse */
        $bankResponse = $this->clientStrategy->getClient($txType, $paymentModel)->request(
            $txType,
            $paymentModel,
            $requestData,
            $data,
            null,
            $this->account
        );

        $this->response = $this->responseDataMapper->mapHistoryResponse($bankResponse);

        return $this->response;
    }

    /**
     * @inheritDoc
     */
    public function getInstallmentRates(array $params): array
    {
        if (!static::isSupportedQuery(PosQueryInterface::QUERY_TYPE_INSTALLMENT_RATES)) {
            throw new UnsupportedTransactionTypeException(PosQueryInterface::QUERY_TYPE_INSTALLMENT_RATES);
        }

        $txType       = PosQueryInterface::QUERY_TYPE_INSTALLMENT_RATES;
        $paymentModel = PosInterface::MODEL_NON_SECURE;

        $requestData = $this->requestDataMapper->createInstallmentRatesRequestData($this->account, $params);
        $requestData = $this->dispatchEvent($txType, $requestData, $params);

        $httpClient = $this->clientStrategy->getClient($txType, $paymentModel);

        /** @var array<string, mixed> $bankResponse */
        $bankResponse = $httpClient->request($txType, $paymentModel, $requestData, $params, null, $this->account);

        $this->response = $this->responseDataMapper->mapInstallmentRatesResponse($bankResponse);

        return $this->response;
    }

    /**
     * @inheritDoc
     */
    public function getInstallmentPrices(array $params): array
    {
        if (!static::isSupportedQuery(PosQueryInterface::QUERY_TYPE_INSTALLMENT_PRICES)) {
            throw new UnsupportedTransactionTypeException(PosQueryInterface::QUERY_TYPE_INSTALLMENT_PRICES);
        }

        $txType       = PosQueryInterface::QUERY_TYPE_INSTALLMENT_PRICES;
        $paymentModel = PosInterface::MODEL_NON_SECURE;

        $requestData = $this->requestDataMapper->createInstallmentPricesRequestData($this->account, $params);
        $requestData = $this->dispatchEvent($txType, $requestData, $params);

        $httpClient = $this->clientStrategy->getClient($txType, $paymentModel);

        /** @var array<string, mixed> $bankResponse */
        $bankResponse = $httpClient->request($txType, $paymentModel, $requestData, $params, null, $this->account);

        $this->response = $this->responseDataMapper->mapInstallmentPricesResponse($bankResponse);

        return $this->response;
    }

    /**
     * @inheritDoc
     */
    public function getBinList(array $params): array
    {
        if (!static::isSupportedQuery(PosQueryInterface::QUERY_TYPE_BIN_LIST)) {
            throw new UnsupportedTransactionTypeException(PosQueryInterface::QUERY_TYPE_BIN_LIST);
        }

        $txType       = PosQueryInterface::QUERY_TYPE_BIN_LIST;
        $paymentModel = PosInterface::MODEL_NON_SECURE;

        $requestData = $this->requestDataMapper->createBinListRequestData($this->account, $params);
        $requestData = $this->dispatchEvent($txType, $requestData, $params);

        $httpClient = $this->clientStrategy->getClient($txType, $paymentModel);

        /** @var array<string, mixed> $bankResponse */
        $bankResponse = $httpClient->request($txType, $paymentModel, $requestData, $params, null, $this->account);

        $this->response = $this->responseDataMapper->mapBinListResponse($bankResponse);

        return $this->response;
    }
}
