<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\PosQuery\Request;

use Mews\Pos\Gateway\PayTrPos;
use Mews\Pos\Model\Account\AbstractPosAccount;

/**
 * @internal
 */
class PayTrPosQueryRequestDataMapper extends AbstractQueryRequestDataMapper
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return PayTrPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    public function createCustomQueryRequestData(AbstractPosAccount $posAccount, array $requestData): array
    {
        $requestData['merchant_id'] ??= $posAccount->getMerchantId();

        if (!isset($requestData['paytr_token'])) {
            $requestData['paytr_token'] = $this->crypt->createHash($posAccount, $requestData);
        }

        return $requestData;
    }

    /**
     * @inheritDoc
     */
    public function createHistoryRequestData(AbstractPosAccount $posAccount, array $data): array
    {
        $requestData = [
            'merchant_id' => $posAccount->getMerchantId(),
            'start_date'  => $this->valueFormatter->formatDateTime($data['start_date'], 'start_date'),
            'end_date'    => $this->valueFormatter->formatDateTime($data['end_date'], 'end_date'),
        ];

        if ($this->isTestMode()) {
            $requestData['dummy'] = 1;
        }

        $requestData['paytr_token'] = $this->crypt->createHash($posAccount, $requestData);

        return $requestData;
    }

    /**
     * @inheritDoc
     */
    public function createBinListRequestData(AbstractPosAccount $posAccount, array $params): array
    {
        $requestData = [
            'merchant_id' => $posAccount->getMerchantId(),
            'bin_number'  => (string) $params['bin'],
        ];

        $requestData['paytr_token'] = $this->crypt->createHash($posAccount, $requestData);

        return $requestData;
    }

    /**
     * @inheritDoc
     */
    public function createInstallmentRatesRequestData(AbstractPosAccount $posAccount, array $params): array
    {
        $requestData = [
            'merchant_id' => $posAccount->getMerchantId(),
            'request_id'  => $this->crypt->generateRandomString(),
        ];

        $requestData['paytr_token'] = $this->crypt->createHash($posAccount, $requestData);

        return $requestData;
    }
}
