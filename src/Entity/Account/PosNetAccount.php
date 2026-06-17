<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Entity\Account;

/**
 * PosNetAccount
 */
class PosNetAccount extends AbstractPosAccount
{
    public function __construct(
        string  $bankName,
        string  $clientId,
        string  $posNetId,
        string  $terminalId,
        ?string $storeKey = null
    ) {
        parent::__construct($bankName, $clientId, $posNetId, $terminalId, $storeKey);
    }

    /**
     * @return string
     */
    public function getTerminalId(): string
    {
        return $this->password;
    }

    /**
     * @return string
     */
    public function getPosNetId(): string
    {
        return $this->username;
    }
}
