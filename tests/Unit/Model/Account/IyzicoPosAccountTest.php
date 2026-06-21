<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Model\Account;

use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Model\Account\IyzicoPosAccount;
use Mews\Pos\Factory\AccountFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IyzicoPosAccount::class)]
#[CoversClass(AbstractPosAccount::class)]
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
