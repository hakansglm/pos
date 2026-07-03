<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\PosQuery\Request;

use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\PosInterface;

/**
 * @internal
 */
interface QueryRequestDataMapperInterface
{
    /**
     * Enriches $requestData with bank-specific credentials, timestamps, and hash.
     * Returns $requestData unchanged if the bank requires no enrichment.
     *
     * @param array<string, mixed> $requestData
     *
     * @return array<string, mixed>
     */
    public function createCustomQueryRequestData(AbstractPosAccount $posAccount, array $requestData): array;

    /**
     * Builds the request payload for a general transaction history query.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function createHistoryRequestData(AbstractPosAccount $posAccount, array $data): array;

    /**
     * Builds the request payload for an installment rates query.
     *
     * @param array<string, mixed> $params Must include at minimum the `bin` key (int, first 6 digits).
     *
     * @return array<string, mixed>
     */
    public function createInstallmentRatesRequestData(AbstractPosAccount $posAccount, array $params): array;

    /**
     * Builds the request payload for an installment prices query.
     *
     * @param array<string, mixed> $params Must include `bin` (string) and `amount` (float).
     *
     * @return array<string, mixed>
     */
    public function createInstallmentPricesRequestData(AbstractPosAccount $posAccount, array $params): array;

    /**
     * Builds the request payload for a BIN lookup or full BIN table query.
     *
     * `$params['bin']` is optional: required for Iyzico/PayTr (always single result),
     * optional for Param/Garanti (filtered list when provided, full table when absent).
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function createBinListRequestData(AbstractPosAccount $posAccount, array $params): array;

    /**
     * @param class-string<PosInterface> $gatewayClass
     */
    public static function supports(string $gatewayClass): bool;

    public function isTestMode(): bool;

    public function setTestMode(bool $testMode): void;
}
