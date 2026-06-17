<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Entity\Account;

abstract class AbstractPosAccount
{
    protected string $clientId;

    protected string $username;

    protected string $password;

    /**
     * required for non regular account models
     */
    protected ?string $storeKey;

    /**
     * bank key name used in configuration file
     */
    protected string $bankName;

    protected ?string $subMerchantId = null;

    /**
     * AbstractPosAccount constructor.
     *
     * @param string      $bankName
     * @param string      $clientId
     * @param string      $username
     * @param string      $password
     * @param string|null $storeKey
     * @param string|null $subMerchantId
     */
    public function __construct(string $bankName, string $clientId, string $username, string $password, ?string $storeKey = null, ?string $subMerchantId = null)
    {
        $this->clientId      = $clientId;
        $this->username      = $username;
        $this->password      = $password;
        $this->storeKey      = $storeKey;
        $this->bankName          = $bankName;
        $this->subMerchantId = $subMerchantId;
    }

    /**
     * @return string
     */
    public function getClientId(): string
    {
        return $this->clientId;
    }

    /**
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @return string|null
     */
    public function getStoreKey(): ?string
    {
        return $this->storeKey;
    }

    /**
     * @return string
     */
    public function getBankName(): string
    {
        return $this->bankName;
    }

    /**
     * @return string|null
     */
    public function getSubMerchantId(): ?string
    {
        return $this->subMerchantId;
    }
}
