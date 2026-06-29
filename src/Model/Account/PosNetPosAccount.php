<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Model\Account;

/**
 * PosNetPosAccount
 */
class PosNetPosAccount extends AbstractPosAccount
{
    public function __construct(
        string  $bankName,
        string  $merchantId,
        string  $posNetId,
        string  $terminalId,
        ?string $secretKey = null,
        ?string $subMerchantId = null
    ) {
        parent::__construct($bankName, $merchantId, $posNetId, '', $secretKey, $terminalId, $subMerchantId);
    }

    /**
     * @return string
     */
    public function getPosNetId(): string
    {
        return $this->username;
    }
}
