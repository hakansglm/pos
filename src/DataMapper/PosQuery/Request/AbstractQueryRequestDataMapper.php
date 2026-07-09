<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\PosQuery\Request;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\Request\ValueFormatter\RequestValueFormatterInterface;
use Mews\Pos\DataMapper\Request\ValueMapper\RequestValueMapperInterface;
use Mews\Pos\Exception\UnsupportedTransactionTypeException;
use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\PosInterface;
use Mews\Pos\PosQuery\PosQueryInterface;

/**
 * @internal
 */
abstract class AbstractQueryRequestDataMapper implements QueryRequestDataMapperInterface
{
    protected bool $testMode = false;

    /**
     * @param PosInterface::LANG_* $defaultLang
     */
    public function __construct(
        protected RequestValueMapperInterface    $valueMapper,
        protected RequestValueFormatterInterface $valueFormatter,
        protected CryptInterface                 $crypt,
        protected string                         $defaultLang = PosInterface::LANG_TR
    ) {
    }

    /**
     * Default implementation: returns $requestData as-is.
     * Override to add bank-specific credentials, timestamps, and hash.
     *
     * @inheritDoc
     */
    public function createCustomQueryRequestData(AbstractPosAccount $posAccount, array $requestData): array
    {
        return $requestData;
    }

    /**
     * Default implementation: throws because most banks require a specific override.
     *
     * @inheritDoc
     */
    public function createHistoryRequestData(AbstractPosAccount $posAccount, array $data): array
    {
        throw new UnsupportedTransactionTypeException(PosQueryInterface::QUERY_TYPE_HISTORY);
    }

    /**
     * Default implementation: throws because most banks require a specific override.
     *
     * @inheritDoc
     */
    public function createInstallmentRatesRequestData(AbstractPosAccount $posAccount, array $params): array
    {
        throw new UnsupportedTransactionTypeException(PosQueryInterface::QUERY_TYPE_INSTALLMENT_RATES);
    }

    /**
     * Default implementation: throws because most banks require a specific override.
     *
     * @inheritDoc
     */
    public function createInstallmentPricesRequestData(AbstractPosAccount $posAccount, array $params): array
    {
        throw new UnsupportedTransactionTypeException(PosQueryInterface::QUERY_TYPE_INSTALLMENT_PRICES);
    }

    /**
     * Default implementation: throws because most banks require a specific override.
     *
     * @inheritDoc
     */
    public function createBinListRequestData(AbstractPosAccount $posAccount, array $params): array
    {
        throw new UnsupportedTransactionTypeException(PosQueryInterface::QUERY_TYPE_BIN_LIST);
    }

    public function isTestMode(): bool
    {
        return $this->testMode;
    }

    public function setTestMode(bool $testMode): void
    {
        $this->testMode = $testMode;
    }

    /**
     * according to the language value, the POS UI will be displayed in the selected language
     * and error messages will be returned in the selected language
     *
     * @param array<string, mixed> $params
     *
     * @return string if language mapping is not available, it returns default LANG_TR or as is.
     */
    protected function getLang(array $params): string
    {
        $lang = $params['lang'] ?? $this->defaultLang;

        return $this->valueMapper->mapLang($lang);
    }
}
