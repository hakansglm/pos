<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\PosQuery\Request;

use Mews\Pos\Gateway\PayFlexV4Pos;
use Mews\Pos\Model\Account\AbstractPosAccount;

/**
 * @internal
 */
class PayFlexV4PosQueryRequestDataMapper extends AbstractQueryRequestDataMapper
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return PayFlexV4Pos::class === $gatewayClass;
    }

    /**
     * @param \Mews\Pos\Model\Account\PayFlexPosAccount $posAccount
     *
     * @inheritDoc
     */
    public function createCustomQueryRequestData(AbstractPosAccount $posAccount, array $requestData): array
    {
        return $requestData + [
            'MerchantId' => $posAccount->getMerchantId(),
            'Password'   => $posAccount->getPassword(),
            'TerminalNo' => $posAccount->getTerminalId(),
        ];
    }
}
