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
        private string  $terminalId,
        ?string         $secretKey = null,
        private ?string $refundUsername = null,
        private ?string $refundPassword = null
    ) {
        parent::__construct($bankName, $merchantId, $username, $password, $secretKey);
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

    /**
     * @return string
     */
    public function getTerminalId(): string
    {
        return $this->terminalId;
    }
}
