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
     * @param string|null $secretKey
     * @param string|null $terminalId
     * @param string|null $subMerchantId
     */
    public function __construct(
        protected string  $bankName,
        protected string  $merchantId,
        protected string  $username,
        protected string  $password,
        protected ?string $secretKey = null,
        protected ?string $terminalId = null,
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
     * @return string
     */
    public function getSecretKey(): string
    {
        if (null === $this->secretKey) {
            throw new \LogicException(\sprintf('%s::$secretKey is not set.', static::class));
        }

        return $this->secretKey;
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

    /**
     * @return string
     */
    public function getTerminalId(): string
    {
        if (null === $this->terminalId) {
            throw new \LogicException(\sprintf('%s::$terminalId is not set.', static::class));
        }

        return $this->terminalId;
    }
}
