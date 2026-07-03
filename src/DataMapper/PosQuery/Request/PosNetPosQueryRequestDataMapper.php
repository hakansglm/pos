<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\PosQuery\Request;

use Mews\Pos\Gateway\PosNetPos;
use Mews\Pos\Model\Account\AbstractPosAccount;

/**
 * @internal
 */
class PosNetPosQueryRequestDataMapper extends AbstractQueryRequestDataMapper
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return PosNetPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    public function createCustomQueryRequestData(AbstractPosAccount $posAccount, array $requestData): array
    {
        return $requestData + [
            'mid' => $posAccount->getMerchantId(),
            'tid' => $posAccount->getTerminalId(),
        ];
    }
}
