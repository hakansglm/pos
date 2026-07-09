<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\PosQuery\Request;

use Mews\Pos\Gateway\PayForPos;
use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\PosQuery\PosQueryInterface;

/**
 * @internal
 */
class PayForPosQueryRequestDataMapper extends AbstractQueryRequestDataMapper
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return PayForPos::class === $gatewayClass;
    }

    /**
     * @param \Mews\Pos\Model\Account\PayForPosAccount $posAccount
     *
     * @inheritDoc
     */
    public function createCustomQueryRequestData(AbstractPosAccount $posAccount, array $requestData): array
    {
        return $requestData + $this->getRequestAccountData($posAccount);
    }

    /**
     * @param \Mews\Pos\Model\Account\PayForPosAccount $posAccount
     *
     * @inheritDoc
     */
    public function createHistoryRequestData(AbstractPosAccount $posAccount, array $data): array
    {
        $requestData = [
            'SecureType' => 'Report',
            'ReqDate'    => $this->valueFormatter->formatDateTime($data['transaction_date'], 'ReqDate'),
            'TxnType'    => $this->valueMapper->mapTxType(PosQueryInterface::QUERY_TYPE_HISTORY),
            'Lang'       => $this->getLang($data),
        ];

        return $this->getRequestAccountData($posAccount) + $requestData;
    }

    /**
     * @param \Mews\Pos\Model\Account\PayForPosAccount $posAccount
     *
     * @return array{MerchantId: string, UserCode: string, UserPass: string, MbrId: string}
     */
    private function getRequestAccountData(AbstractPosAccount $posAccount): array
    {
        return [
            'MerchantId' => $posAccount->getMerchantId(),
            'UserCode'   => $posAccount->getUsername(),
            'UserPass'   => $posAccount->getPassword(),
            'MbrId'      => $posAccount->getMbrId(),
        ];
    }
}
