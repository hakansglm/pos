<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Model\Account;

use AssertionError;
use Mews\Pos\Model\Account\AbstractPosAccount;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbstractPosAccount::class)]
class AbstractPosAccountTest extends TestCase
{
    public function testGetters(): void
    {
        $account = new class ('test-bank', 'merchant-1', 'user', 'pass', 'secret', 'term-1', 'sub-1') extends AbstractPosAccount {};

        $this->assertSame('test-bank', $account->getBankName());
        $this->assertSame('merchant-1', $account->getMerchantId());
        $this->assertSame('user', $account->getUsername());
        $this->assertSame('pass', $account->getPassword());
        $this->assertSame('secret', $account->getSecretKey());
        $this->assertSame('term-1', $account->getTerminalId());
        $this->assertSame('sub-1', $account->getSubMerchantId());
    }

    public function testSubMerchantIdDefaultsToNull(): void
    {
        $account = new class ('bank', 'mid', 'user', 'pass') extends AbstractPosAccount {};

        $this->assertNull($account->getSubMerchantId());
    }

    public function testGetSecretKeyThrowsWhenNull(): void
    {
        $account = new class ('bank', 'mid', 'user', 'pass') extends AbstractPosAccount {};

        $this->expectException(AssertionError::class);
        $account->getSecretKey();
    }

    public function testGetTerminalIdThrowsWhenNull(): void
    {
        $account = new class ('bank', 'mid', 'user', 'pass') extends AbstractPosAccount {};

        $this->expectException(AssertionError::class);
        $account->getTerminalId();
    }
}
