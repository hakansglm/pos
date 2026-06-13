<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Entity\Account;

class IyzicoPosAccount extends AbstractPosAccount
{
    /**
     * @param string      $bank
     * @param string      $apiKey
     * @param string      $secretKey
     * @param string|null $subMerchantKey
     */
    public function __construct(
        string  $bank,
        string  $apiKey,
        string  $secretKey,
        ?string $subMerchantKey = null
    ) {
        parent::__construct($bank, $apiKey, '', '', $secretKey, $subMerchantKey);
    }
}
