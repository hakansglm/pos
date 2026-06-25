<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Factory;

use Mews\Pos\Model\Account\AkbankPosAccount;
use Mews\Pos\Model\Account\BoaPosAccount;
use Mews\Pos\Model\Account\IyzicoPosAccount;
use Mews\Pos\Model\Account\ParamPosAccount;
use Mews\Pos\Model\Account\PayForPosAccount;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Model\Account\PayTrPosAccount;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AccountFactory::class)]
#[CoversClass(BoaPosAccount::class)]
#[CoversClass(AkbankPosAccount::class)]
#[CoversClass(ParamPosAccount::class)]
#[CoversClass(PayForPosAccount::class)]
#[CoversClass(IyzicoPosAccount::class)]
#[CoversClass(PayTrPosAccount::class)]
class AccountFactoryTest extends TestCase
{
    public function testCreateBoaPosAccount(): void
    {
        $account = AccountFactory::createBoaPosAccount(
            'vakif-katilim',
            '1',
            'APIUSER',
            '11111',
            'kdsnsksl',
            PosInterface::MODEL_3D_SECURE,
            'SUB1',
        );

        $this->assertSame('1', $account->getClientId());
        $this->assertSame('APIUSER', $account->getUsername());
        $this->assertSame('11111', $account->getCustomerId());
        $this->assertSame('kdsnsksl', $account->getStoreKey());
        $this->assertSame('SUB1', $account->getSubMerchantId());
    }

    public function testCreateAkbankPosAccount(): void
    {
        $account = AccountFactory::createAkbankPosAccount(
            'akbank-pos',
            '1',
            'APIUSER',
            'kdsnsksl',
            'SUB1',
        );

        $this->assertSame('1', $account->getClientId());
        $this->assertSame('APIUSER', $account->getTerminalId());
        $this->assertSame('kdsnsksl', $account->getStoreKey());
        $this->assertSame('SUB1', $account->getSubMerchantId());
    }

    public function testCreateParamPosAccount(): void
    {
        $account = AccountFactory::createParamPosAccount(
            'param-pos',
            '12345',
            'APIUSER',
            'kdsnsksl',
            'guid123',
        );

        $this->assertSame('12345', $account->getClientId());
        $this->assertSame('APIUSER', $account->getUsername());
        $this->assertSame('kdsnsksl', $account->getPassword());
        $this->assertSame('guid123', $account->getStoreKey());
    }

    public function testCreatePayForPosAccount(): void
    {
        $account = AccountFactory::createPayForPosAccount(
            'qnbfinansbank-payfor',
            '085300000009704',
            'QNB_API_KULLANICI_3DPAY',
            'UcBN0',
            PosInterface::MODEL_3D_SECURE,
            '12345678',
            PayForPosAccount::MBR_ID_ZIRAAT_KATILIM
        );

        $this->assertSame('085300000009704', $account->getClientId());
        $this->assertSame('QNB_API_KULLANICI_3DPAY', $account->getUsername());
        $this->assertSame('UcBN0', $account->getPassword());
        $this->assertSame('12345678', $account->getStoreKey());
        $this->assertSame(PayForPosAccount::MBR_ID_ZIRAAT_KATILIM, $account->getMbrId());
    }

    public function testCreateIyzicoPosAccount(): void
    {
        $account = \Mews\Pos\Factory\AccountFactory::createIyzicoPosAccount(
            'iyzico',
            'api-key',
            'api-secret-key',
        );

        $this->assertInstanceOf(IyzicoPosAccount::class, $account);
        $this->assertSame('api-key', $account->getClientId());
        $this->assertSame('api-secret-key', $account->getStoreKey());
    }

    public function testCreatePayTrPosAccount(): void
    {
        $account = AccountFactory::createPayTrPosAccount(
            'paytr',
            '123456',
            'wWwU8buJp6jo1r25',
            'YEUaNcdHXqyt7hjt',
        );

        $this->assertInstanceOf(PayTrPosAccount::class, $account);
        $this->assertSame('123456', $account->getClientId());
        $this->assertSame('wWwU8buJp6jo1r25', $account->getPassword());
        $this->assertSame('YEUaNcdHXqyt7hjt', $account->getStoreKey());
    }
}
