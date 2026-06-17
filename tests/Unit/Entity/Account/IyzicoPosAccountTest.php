<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Entity\Account;

use Mews\Pos\Factory\AccountFactory;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\Entity\Account\IyzicoPosAccount
 * @covers \Mews\Pos\Entity\Account\AbstractPosAccount
 */
class IyzicoPosAccountTest extends TestCase
{
    public function testGetters(): void
    {
        $account = AccountFactory::createIyzicoPosAccount('iyzico', 'api-key', 'secret-key', 'sub-merchant-123');

        $this->assertSame('iyzico', $account->getBankName());
        $this->assertSame('api-key', $account->getClientId());
        $this->assertSame('secret-key', $account->getStoreKey());
        $this->assertSame('sub-merchant-123', $account->getSubMerchantId());
    }

    public function testGetSubMerchantIdReturnsNullWhenNotSet(): void
    {
        $account = AccountFactory::createIyzicoPosAccount('iyzico', 'api-key', 'secret-key');

        $this->assertNull($account->getSubMerchantId());
    }
}
