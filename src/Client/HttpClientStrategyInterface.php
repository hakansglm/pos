<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\PosInterface;
use Mews\Pos\PosQuery\PosQueryInterface;

interface HttpClientStrategyInterface
{
    /**
     * @return array<HttpClientInterface::API_NAME_*, HttpClientInterface>
     */
    public function getAllClients(): array;

    /**
     * @param PosInterface::TX_TYPE_*|PosQueryInterface::QUERY_TYPE_* $txType
     * @param PosInterface::MODEL_*                                   $paymentModel
     *
     * @return HttpClientInterface
     */
    public function getClient(string $txType, string $paymentModel): HttpClientInterface;
}
