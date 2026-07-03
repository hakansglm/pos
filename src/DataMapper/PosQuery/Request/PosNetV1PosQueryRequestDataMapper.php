<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\PosQuery\Request;

use Mews\Pos\Gateway\PosNetV1Pos;
use Mews\Pos\Model\Account\AbstractPosAccount;

/**
 * @internal
 */
class PosNetV1PosQueryRequestDataMapper extends AbstractQueryRequestDataMapper
{
    private const API_VERSION = 'V100';

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return PosNetV1Pos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    public function createCustomQueryRequestData(AbstractPosAccount $posAccount, array $requestData): array
    {
        $requestData += [
            'ApiType'    => 'JSON',
            'ApiVersion' => self::API_VERSION,
            'MerchantNo' => $posAccount->getMerchantId(),
            'TerminalNo' => $posAccount->getTerminalId(),
        ];

        if (!isset($requestData['MAC'])) {
            $requestData['MAC'] = $this->crypt->hashFromParams($posAccount, $requestData, $requestData['MACParams'], ':');
        }

        return $requestData;
    }
}
