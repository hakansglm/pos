<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\PosQuery\Request;

use Mews\Pos\Gateway\ToslaPos;
use Mews\Pos\Model\Account\AbstractPosAccount;

/**
 * @internal
 */
class ToslaPosQueryRequestDataMapper extends AbstractQueryRequestDataMapper
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return ToslaPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    public function createCustomQueryRequestData(AbstractPosAccount $posAccount, array $requestData): array
    {
        $requestData += $this->getRequestAccountData($posAccount) + [
            'rnd'      => $this->crypt->generateRandomString(),
            'timeSpan' => $this->valueFormatter->formatDateTime(
                $this->newTimeSpan(),
                'timeSpan'
            ),
        ];

        if (!isset($requestData['hash'])) {
            $requestData['hash'] = $this->crypt->createHash($posAccount, $requestData);
        }

        return $requestData;
    }

    /**
     * @inheritDoc
     */
    public function createInstallmentRatesRequestData(AbstractPosAccount $posAccount, array $params): array
    {
        $requestData = $this->getRequestAccountData($posAccount) + [
            'rnd'      => $this->crypt->generateRandomString(),
            'timeSpan' => $this->valueFormatter->formatDateTime(
                $this->newTimeSpan(),
                'timeSpan'
            ),
            'bin'      => $params['bin'],
        ];

        $requestData['hash'] = $this->crypt->createHash($posAccount, $requestData);

        return $requestData;
    }

    /**
     * @inheritDoc
     */
    public function createInstallmentPricesRequestData(AbstractPosAccount $posAccount, array $params): array
    {
        $requestData = $this->getRequestAccountData($posAccount) + [
            'rnd'         => $this->crypt->generateRandomString(),
            'timeSpan'    => $this->valueFormatter->formatDateTime($this->newTimeSpan(), 'timeSpan'),
            'amount'      => $params['amount'],
            'isCommission' => 1,
        ];

        $requestData['hash'] = $this->crypt->createHash($posAccount, $requestData);

        return $requestData;
    }

    /**
     * @return array{clientId: string, apiUser: string}
     */
    private function getRequestAccountData(AbstractPosAccount $posAccount): array
    {
        return [
            'clientId' => $posAccount->getMerchantId(),
            'apiUser'  => $posAccount->getUsername(),
        ];
    }

    /**
     * @return \DateTimeImmutable
     */
    private function newTimeSpan(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('Europe/Istanbul'));
    }
}
