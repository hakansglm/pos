<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\PosQuery\Request;

use Mews\Pos\Gateway\VakifKatilimPos;
use Mews\Pos\Model\Account\AbstractPosAccount;

/**
 * @internal
 */
class VakifKatilimPosQueryRequestDataMapper extends AbstractQueryRequestDataMapper
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return VakifKatilimPos::class === $gatewayClass;
    }

    /**
     * @param \Mews\Pos\Model\Account\BoaPosAccount $posAccount
     *
     * @inheritDoc
     */
    public function createHistoryRequestData(AbstractPosAccount $posAccount, array $data): array
    {
        $page     = $data['page'] ?? 1;
        $pageSize = $data['page_size'] ?? 10;

        $result = $this->getRequestAccountData($posAccount) + [
            'StartDate'   => $this->valueFormatter->formatDateTime($data['start_date'], 'StartDate'),
            'EndDate'     => $this->valueFormatter->formatDateTime($data['end_date'], 'EndDate'),
            'LowerLimit'  => ($page - 1) * $pageSize,
            'UpperLimit'  => $pageSize,
            'ProvNumber'  => null,
            'OrderStatus' => null,
            'TranResult'  => null,
            'OrderNo'     => null,
        ];

        $result['HashData'] = $this->crypt->createHash($posAccount, $result);

        return $result;
    }

    /**
     * @param \Mews\Pos\Model\Account\BoaPosAccount $posAccount
     *
     * @return array{MerchantId: string, CustomerId: string, UserName: string, SubMerchantId: string}
     */
    private function getRequestAccountData(AbstractPosAccount $posAccount): array
    {
        return [
            'MerchantId'    => $posAccount->getMerchantId(),
            'CustomerId'    => $posAccount->getCustomerId(),
            'UserName'      => $posAccount->getUsername(),
            'SubMerchantId' => $posAccount->getSubMerchantId() ?? '0',
        ];
    }
}
