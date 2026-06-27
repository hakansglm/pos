<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Model\Account;

abstract class AbstractPosAccount
{
    /**
     * AbstractPosAccount constructor.
     *
     * @param string      $bankName
     * @param string      $merchantId
     * @param string      $username
     * @param string      $password
     * @param string|null $storeKey
     * @param string|null $subMerchantId
     */
    public function __construct(
        protected string  $bankName,
        protected string  $merchantId,
        protected string  $username,
        protected string  $password,
        protected ?string $storeKey = null,
        protected ?string $subMerchantId = null
    ) {
    }

    /**
     * @return string
     */
    public function getMerchantId(): string
    {
        return $this->merchantId;
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
