<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Entity\Card;

use DateTimeImmutable;

/**
 * Class AbstractCreditCard
 */
abstract class AbstractCreditCard implements CreditCardInterface
{
    /**
     * 16 digit credit card number without spaces
     */
    protected string $number;

    /**
     * @phpstan-param CreditCardInterface::CARD_TYPE_*|null $type
     *
     * @param string            $number     credit card number with or without spaces
     * @param DateTimeImmutable $expDate
     * @param string            $cvv
     * @param string|null       $holderName
     * @param string|null       $type       examples values: 'visa', 'master', '1', '2'
     *
     * @throws \LogicException
     */
    public function __construct(
        string $number,
        protected DateTimeImmutable $expDate,
        protected string $cvv,
        protected ?string $holderName = null,
        /**
         * @phpstan-var CreditCardInterface::CARD_TYPE_*
         */
        protected ?string $type = null
    ) {
        $number = \preg_replace('/\s+/', '', $number);
        if (null === $number) {
            throw new \LogicException('Kredit numarası formatlanamadı!');
        }

        $this->number     = $number;
    }

    /**
     * @inheritDoc
     */
    public function getNumber(): string
    {
        return $this->number;
    }

    /**
     * @inheritDoc
     */
    public function getExpirationDate(): \DateTimeImmutable
    {
        return $this->expDate;
    }

    /**
     * @inheritDoc
     */
    public function getCvv(): string
    {
        return $this->cvv;
    }

    /**
     * @inheritDoc
     */
    public function getHolderName(): ?string
    {
        return $this->holderName;
    }

    /**
     * @inheritDoc
     */
    public function setHolderName(?string $name): CreditCardInterface
    {
        $this->holderName = $name;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getType(): ?string
    {
        return $this->type;
    }
}
