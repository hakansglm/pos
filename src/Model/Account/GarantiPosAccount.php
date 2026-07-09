<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Model\Account;

/**
 * GarantiPosAccount
 */
class GarantiPosAccount extends AbstractPosAccount
{
    public function __construct(
        string          $bankName,
        string          $merchantId,
        string          $username,
        string          $password,
        string          $terminalId,
        ?string         $secretKey = null,
        private ?string $refundUsername = null,
        private ?string $refundPassword = null
    ) {
        parent::__construct($bankName, $merchantId, $username, $password, $secretKey, $terminalId);
    }

    /**
     * @return string|null
     */
    public function getRefundPassword(): ?string
    {
        return $this->refundPassword;
    }

    /**
     * @return string|null
     */
    public function getRefundUsername(): ?string
    {
        return $this->refundUsername;
    }
}
