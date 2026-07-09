<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Model\Card;

/**
 * Interface CreditCardInterface
 */
interface CreditCardInterface
{
    /** @var string */
    public const CARD_TYPE_VISA = 'visa';

    /** @var string */
    public const CARD_TYPE_MASTERCARD = 'master';

    /** @var string */
    public const CARD_TYPE_AMEX = 'amex';

    /** @var string */
    public const CARD_TYPE_TROY = 'troy';

    /** @var string */
    public const CARD_CLASS_CREDIT = 'credit';

    /** @var string */
    public const CARD_CLASS_DEBIT = 'debit';

    /** @var string */
    public const CARD_CLASS_PREPAID = 'prepaid';

    /** @var string */
    public const CARD_FAMILY_WORLD = 'world';

    /** @var string */
    public const CARD_FAMILY_AXESS = 'axess';

    /** @var string */
    public const CARD_FAMILY_CARDFINANS = 'cardfinans';

    /** @var string */
    public const CARD_FAMILY_PARAF = 'paraf';

    /** @var string */
    public const CARD_FAMILY_ADVANTAGE = 'advantage';

    /** @var string */
    public const CARD_FAMILY_BONUS = 'bonus';

    /** @var string */
    public const CARD_FAMILY_MAXIMUM = 'maximum';

    /** @var string */
    public const CARD_FAMILY_SAGLAMKART = 'saglamkart';

    /**
     * returns card number without white spaces
     *
     * @return string
     */
    public function getNumber(): string;

    /**
     * @return \DateTimeImmutable
     */
    public function getExpirationDate(): \DateTimeImmutable;

    /**
     * @return string
     */
    public function getCvv(): string;

    /**
     * @return string|null
     */
    public function getHolderName(): ?string;

    /**
     * @param string|null $name
     *
     * @return $this
     */
    public function setHolderName(?string $name): self;

    /**
     * @return CreditCardInterface::CARD_TYPE_*|null
     */
    public function getType(): ?string;
}
