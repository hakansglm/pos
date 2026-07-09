<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\PosQuery\Request;

use Mews\Pos\Gateway\AkbankPos;
use Mews\Pos\Model\Account\AbstractPosAccount;

/**
 * @internal
 */
class AkbankPosQueryRequestDataMapper extends AbstractQueryRequestDataMapper
{
    private const API_VERSION = '1.00';

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return AkbankPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    public function createCustomQueryRequestData(AbstractPosAccount $posAccount, array $requestData): array
    {
        $dateTime = $requestData['requestDateTime']
            ?? $this->valueFormatter->formatDateTime(new \DateTimeImmutable(), 'requestDateTime');

        return $requestData
            + $this->getRequestAccountData($posAccount)
            + [
                'version'         => self::API_VERSION,
                'requestDateTime' => $dateTime,
                'randomNumber'    => $this->crypt->generateRandomString(),
            ];
    }

    /**
     * @inheritDoc
     */
    public function createHistoryRequestData(AbstractPosAccount $posAccount, array $data): array
    {
        $requestData = $this->getRequestAccountData($posAccount) + [
            'randomNumber' => $this->crypt->generateRandomString(),
        ];

        if (isset($data['batch_num'])) {
            $requestData['report'] = [
                'batchNumber' => $data['batch_num'],
            ];
        } elseif (isset($data['start_date'], $data['end_date'])) {
            $requestData['report'] = [
                'startDateTime' => $this->valueFormatter->formatDateTime($data['start_date'], 'startDateTime'),
                'endDateTime'   => $this->valueFormatter->formatDateTime($data['end_date'], 'endDateTime'),
            ];
        }

        return $requestData;
    }

    /**
     * @return array{terminal: array{merchantSafeId: string, terminalSafeId: string}}
     */
    private function getRequestAccountData(AbstractPosAccount $posAccount): array
    {
        $data = [
            'terminal' => [
                'merchantSafeId' => $posAccount->getMerchantId(),
                'terminalSafeId' => $posAccount->getTerminalId(),
            ],
        ];

        if (null !== $posAccount->getSubMerchantId()) {
            $data['subMerchant'] = [
                'subMerchantId' => $posAccount->getSubMerchantId(),
            ];
        }

        return $data;
    }
}
