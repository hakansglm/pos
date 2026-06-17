<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Entity\Account;

class IyzicoPosAccount extends AbstractPosAccount
{
    /**
     * @param string      $bankName
     * @param string      $apiKey
     * @param string      $secretKey
     * @param string|null $subMerchantKey
     */
    public function __construct(
        string  $bankName,
        string  $apiKey,
        string  $secretKey,
        ?string $subMerchantKey = null
    ) {
        parent::__construct($bankName, $apiKey, '', '', $secretKey, $subMerchantKey);
    }
}
