<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Model\Account;

class PayTrPosAccount extends AbstractPosAccount
{
    /**
     * @param non-empty-string $bank
     * @param non-empty-string $merchantId   merchant_id from PayTR panel
     * @param non-empty-string $merchantSalt merchant_salt from PayTR panel (appended to hash string)
     * @param non-empty-string $merchantKey  merchant_key from PayTR panel (HMAC signing key → secretKey)
     */
    public function __construct(string $bank, string $merchantId, string $merchantSalt, string $merchantKey)
    {
        parent::__construct($bank, $merchantId, '', $merchantSalt, $merchantKey);
    }
}
