<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Event;

use Mews\Pos\PosQuery\PosQueryInterface;

/**
 * Fired when a PosQuery API request is prepared.
 * Listeners may inspect or modify the request data before it is sent to the bank.
 */
class PosQueryRequestDataPreparedEvent
{
    /**
     * @phpstan-param PosQueryInterface::QUERY_TYPE_* $txType
     *
     * @param array<string, mixed> $requestData
     * @param string               $bankName
     * @param string               $txType
     * @param array<string, mixed> $originalData Data as supplied by the caller, before enrichment.
     */
    public function __construct(
        private array  $requestData,
        private string $bankName,
        private string $txType,
        private array  $originalData
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getRequestData(): array
    {
        return $this->requestData;
    }

    /**
     * @param array<string, mixed> $requestData
     */
    public function setRequestData(array $requestData): self
    {
        $this->requestData = $requestData;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOriginalData(): array
    {
        return $this->originalData;
    }

    /**
     * @return PosQueryInterface::QUERY_TYPE_*
     */
    public function getTxType(): string
    {
        return $this->txType;
    }

    public function getBankName(): string
    {
        return $this->bankName;
    }
}
