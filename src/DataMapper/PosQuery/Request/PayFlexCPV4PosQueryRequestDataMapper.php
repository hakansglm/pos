<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\PosQuery\Request;

use Mews\Pos\Gateway\PayFlexCPV4Pos;
use Mews\Pos\Model\Account\AbstractPosAccount;

/**
 * @internal
 */
class PayFlexCPV4PosQueryRequestDataMapper extends AbstractQueryRequestDataMapper
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return PayFlexCPV4Pos::class === $gatewayClass;
    }

    /**
     * @param \Mews\Pos\Model\Account\PayFlexPosAccount $posAccount
     *
     * @inheritDoc
     */
    public function createCustomQueryRequestData(AbstractPosAccount $posAccount, array $requestData): array
    {
        return $requestData + [
            'HostMerchantId' => $posAccount->getMerchantId(),
            'Password'       => $posAccount->getPassword(),
        ];
    }
}
