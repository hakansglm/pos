<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\PosQuery\Request;

use Mews\Pos\Gateway\IyzicoPos;
use Mews\Pos\Model\Account\AbstractPosAccount;

/**
 * @internal
 */
class IyzicoPosQueryRequestDataMapper extends AbstractQueryRequestDataMapper
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return IyzicoPos::class === $gatewayClass;
    }

    /**
     * iyzico custom queries require no credential enrichment.
     *
     * @inheritDoc
     */
    public function createCustomQueryRequestData(AbstractPosAccount $posAccount, array $requestData): array
    {
        return $requestData;
    }

    /**
     * @inheritDoc
     */
    public function createHistoryRequestData(AbstractPosAccount $posAccount, array $data): array
    {
        return [
            'locale'          => $this->getLang($data),
            'transactionDate' => $this->valueFormatter->formatDateTime($data['transaction_date'], 'transactionDate'),
            'page'            => $data['page'] ?? 1,
        ];
    }

    /**
     * @inheritDoc
     */
    public function createBinListRequestData(AbstractPosAccount $posAccount, array $params): array
    {
        return [
            'locale'         => $this->getLang($params),
            'conversationId' => $this->crypt->generateRandomString(),
            'binNumber'      => (string) $params['bin'],
        ];
    }

    /**
     * @inheritDoc
     */
    public function createInstallmentPricesRequestData(AbstractPosAccount $posAccount, array $params): array
    {
        $requestData = [
            'locale'         => $this->getLang($params),
            'conversationId' => $this->crypt->generateRandomString(),
            'price'          => $params['amount'],
        ];
        if (isset($params['bin'])) {
            $requestData['binNumber'] = (string) $params['bin'];
        }

        return $requestData;
    }
}
