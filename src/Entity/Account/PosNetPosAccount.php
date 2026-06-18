<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Entity\Account;

/**
 * PosNetPosAccount
 */
class PosNetPosAccount extends AbstractPosAccount
{
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
