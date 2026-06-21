<?php

/**
 * @license
 */

namespace Mews\Pos\Model\Account;

class AkbankPosAccount extends AbstractPosAccount
{
    /**
     * @param string      $bankName
     * @param string      $merchantSafeId Üye İş Yeri numarası
     * @param string      $terminalSafeId
     * @param string      $secretKey
     * @param string|null $subMerchantId
     */
    public function __construct(
        string  $bankName,
        string  $merchantSafeId,
        string  $terminalSafeId,
        string  $secretKey,
        ?string $subMerchantId = null
    ) {
        parent::__construct($bankName, $merchantSafeId, $terminalSafeId, '', $secretKey, $subMerchantId);
    }

    /**
     * @return string
     */
    public function getTerminalId(): string
    {
        return $this->username;
    }
}
