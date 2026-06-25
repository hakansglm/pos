<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Model\Card;

use DateTimeImmutable;
use Mews\Pos\Model\Card\AbstractCreditCard;
use Mews\Pos\Model\Card\CreditCard;
use Mews\Pos\Model\Card\CreditCardInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreditCard::class)]
#[CoversClass(AbstractCreditCard::class)]
class CreditCardTest extends TestCase
{
    public function testGetters(): void
    {
        $expDate = new DateTimeImmutable('2026-02-01');
        $card    = new CreditCard('4444555566667777', $expDate, '123', 'John Doe', CreditCardInterface::CARD_TYPE_VISA);

        $this->assertSame('4444555566667777', $card->getNumber());
        $this->assertSame($expDate, $card->getExpirationDate());
        $this->assertSame('123', $card->getCvv());
        $this->assertSame('John Doe', $card->getHolderName());
        $this->assertSame(CreditCardInterface::CARD_TYPE_VISA, $card->getType());
    }

    public function testNumberSpacesAreStripped(): void
    {
        $card = new CreditCard('4444 5555 6666 7777', new DateTimeImmutable('2026-02-01'), '123');

        $this->assertSame('4444555566667777', $card->getNumber());
    }

    public function testHolderNameAndTypeDefaultToNull(): void
    {
        $card = new CreditCard('4444555566667777', new DateTimeImmutable('2026-02-01'), '123');

        $this->assertNull($card->getHolderName());
        $this->assertNull($card->getType());
    }

    public function testSetHolderNameReturnsSelf(): void
    {
        $card   = new CreditCard('4444555566667777', new DateTimeImmutable('2026-02-01'), '123');
        $result = $card->setHolderName('Jane Doe');

        $this->assertSame($card, $result);
        $this->assertSame('Jane Doe', $card->getHolderName());
    }

    public function testSetHolderNameToNull(): void
    {
        $card = new CreditCard('4444555566667777', new DateTimeImmutable('2026-02-01'), '123', 'John Doe');
        $card->setHolderName(null);

        $this->assertNull($card->getHolderName());
    }
}
