<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Entity\Account;

class IyzicoPosAccount extends AbstractPosAccount
{
    private ?string $subMerchantId;

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
        parent::__construct($bank, $apiKey, '', '', $secretKey);
        $this->subMerchantId = $subMerchantKey;
    }

    /**
     * @return string|null
     */
    public function getSubMerchantId(): ?string
    {
        return $this->subMerchantId;
    }
}
