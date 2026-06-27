<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Factory;

use Mews\Pos\Exception\MissingAccountInfoException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Model\Account\AkbankPosAccount;
use Mews\Pos\Model\Account\AssecoPosAccount;
use Mews\Pos\Model\Account\BoaPosAccount;
use Mews\Pos\Model\Account\GarantiPosAccount;
use Mews\Pos\Model\Account\InterPosAccount;
use Mews\Pos\Model\Account\IyzicoPosAccount;
use Mews\Pos\Model\Account\ParamPosAccount;
use Mews\Pos\Model\Account\PayFlexPosAccount;
use Mews\Pos\Model\Account\PayForPosAccount;
use Mews\Pos\Model\Account\PayTrPosAccount;
use Mews\Pos\Model\Account\PosNetPosAccount;
use Mews\Pos\Model\Account\ToslaPosAccount;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AccountFactory::class)]
#[CoversClass(AbstractPosAccount::class)]
#[CoversClass(AkbankPosAccount::class)]
#[CoversClass(AssecoPosAccount::class)]
#[CoversClass(BoaPosAccount::class)]
#[CoversClass(GarantiPosAccount::class)]
#[CoversClass(InterPosAccount::class)]
#[CoversClass(IyzicoPosAccount::class)]
#[CoversClass(ParamPosAccount::class)]
#[CoversClass(PayFlexPosAccount::class)]
#[CoversClass(PayForPosAccount::class)]
#[CoversClass(PayTrPosAccount::class)]
#[CoversClass(PosNetPosAccount::class)]
#[CoversClass(ToslaPosAccount::class)]
class AccountFactoryTest extends TestCase
{
    public function testCreateAssecoPosAccountNonSecure(): void
    {
        $account = AccountFactory::createAssecoPosAccount(
            'akbank',
            'merchant-id',
            'user',
            'pass',
        );

        $this->assertInstanceOf(AssecoPosAccount::class, $account);
        $this->assertSame('akbank', $account->getBankName());
        $this->assertSame('merchant-id', $account->getMerchantId());
        $this->assertSame('user', $account->getUsername());
        $this->assertSame('pass', $account->getPassword());
        $this->assertNull($account->getStoreKey());
    }

    public function testCreateAssecoPosAccount3DSecure(): void
    {
        $account = AccountFactory::createAssecoPosAccount(
            'akbank',
            'merchant-id',
            'user',
            'pass',
            'store-key',
        );

        $this->assertSame('merchant-id', $account->getMerchantId());
        $this->assertSame('user', $account->getUsername());
        $this->assertSame('pass', $account->getPassword());
        $this->assertSame('store-key', $account->getStoreKey());
    }

    public function testCreateBoaPosAccount(): void
    {
        $account = AccountFactory::createBoaPosAccount(
            'vakif-katilim',
            '1',
            'APIUSER',
            '11111',
            'kdsnsksl',
            'SUB1',
        );

        $this->assertSame('vakif-katilim', $account->getBankName());
        $this->assertSame('1', $account->getMerchantId());
        $this->assertSame('APIUSER', $account->getUsername());
        $this->assertSame('11111', $account->getCustomerId());
        $this->assertSame('kdsnsksl', $account->getStoreKey());
        $this->assertSame('SUB1', $account->getSubMerchantId());
    }

    public function testCreateBoaPosAccountWithoutSubMerchantId(): void
    {
        $account = AccountFactory::createBoaPosAccount(
            'vakif-katilim',
            '1',
            'APIUSER',
            '11111',
            'kdsnsksl',
        );

        $this->assertSame('1', $account->getMerchantId());
        $this->assertNull($account->getSubMerchantId());
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

        $this->assertSame('akbank-pos', $account->getBankName());
        $this->assertSame('1', $account->getMerchantId());
        $this->assertSame('APIUSER', $account->getTerminalId());
        $this->assertSame('kdsnsksl', $account->getStoreKey());
        $this->assertSame('SUB1', $account->getSubMerchantId());
    }

    public function testCreateAkbankPosAccountWithoutSubMerchantId(): void
    {
        $account = AccountFactory::createAkbankPosAccount(
            'akbank-pos',
            '1',
            'APIUSER',
            'kdsnsksl',
        );

        $this->assertSame('1', $account->getMerchantId());
        $this->assertSame('APIUSER', $account->getTerminalId());
        $this->assertSame('kdsnsksl', $account->getStoreKey());
        $this->assertNull($account->getSubMerchantId());
    }

    public function testCreateParamPosAccount(): void
    {
        $account = AccountFactory::createParamPosAccount(
            'param-pos',
            12345,
            'APIUSER',
            'kdsnsksl',
            'guid123',
        );

        $this->assertSame('param-pos', $account->getBankName());
        $this->assertSame('12345', $account->getMerchantId());
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
            '12345678',
            PayForPosAccount::MBR_ID_ZIRAAT_KATILIM
        );

        $this->assertSame('qnbfinansbank-payfor', $account->getBankName());
        $this->assertSame('085300000009704', $account->getMerchantId());
        $this->assertSame('QNB_API_KULLANICI_3DPAY', $account->getUsername());
        $this->assertSame('UcBN0', $account->getPassword());
        $this->assertSame('12345678', $account->getStoreKey());
        $this->assertSame(PayForPosAccount::MBR_ID_ZIRAAT_KATILIM, $account->getMbrId());
    }

    public function testCreatePayForPosAccountNonSecure(): void
    {
        $account = AccountFactory::createPayForPosAccount(
            'qnbfinansbank-payfor',
            '085300000009704',
            'QNB_API_KULLANICI',
            'UcBN0',
        );

        $this->assertSame('085300000009704', $account->getMerchantId());
        $this->assertSame('QNB_API_KULLANICI', $account->getUsername());
        $this->assertNull($account->getStoreKey());
        $this->assertSame(PayForPosAccount::MBR_ID_FINANSBANK, $account->getMbrId());
    }

    public function testCreateIyzicoPosAccount(): void
    {
        $account = AccountFactory::createIyzicoPosAccount(
            'iyzico',
            'api-key',
            'api-secret-key',
        );

        $this->assertInstanceOf(IyzicoPosAccount::class, $account);
        $this->assertSame('iyzico', $account->getBankName());
        $this->assertSame('api-key', $account->getMerchantId());
        $this->assertSame('api-secret-key', $account->getStoreKey());
        $this->assertNull($account->getSubMerchantId());
    }

    public function testCreateIyzicoPosAccountWithSubMerchantKey(): void
    {
        $account = AccountFactory::createIyzicoPosAccount(
            'iyzico',
            'api-key',
            'api-secret-key',
            'sub-merchant-key',
        );

        $this->assertSame('api-key', $account->getMerchantId());
        $this->assertSame('api-secret-key', $account->getStoreKey());
        $this->assertSame('sub-merchant-key', $account->getSubMerchantId());
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
        $this->assertSame('paytr', $account->getBankName());
        $this->assertSame('123456', $account->getMerchantId());
        $this->assertSame('wWwU8buJp6jo1r25', $account->getPassword());
        $this->assertSame('YEUaNcdHXqyt7hjt', $account->getStoreKey());
    }

    public function testCreateToslaPosAccount(): void
    {
        $account = AccountFactory::createToslaPosAccount(
            'tosla',
            'merchant-id',
            'api-user',
            'api-pass',
        );

        $this->assertInstanceOf(ToslaPosAccount::class, $account);
        $this->assertSame('tosla', $account->getBankName());
        $this->assertSame('merchant-id', $account->getMerchantId());
        $this->assertSame('api-user', $account->getUsername());
        $this->assertSame('api-pass', $account->getStoreKey());
    }

    public function testCreateGarantiPosAccountNonSecure(): void
    {
        $account = AccountFactory::createGarantiPosAccount(
            'garanti',
            '7000679',
            'PROVAUT',
            'pass',
            '30691298',
        );

        $this->assertInstanceOf(GarantiPosAccount::class, $account);
        $this->assertSame('garanti', $account->getBankName());
        $this->assertSame('7000679', $account->getMerchantId());
        $this->assertSame('PROVAUT', $account->getUsername());
        $this->assertSame('pass', $account->getPassword());
        $this->assertSame('30691298', $account->getTerminalId());
        $this->assertNull($account->getStoreKey());
        $this->assertNull($account->getRefundUsername());
        $this->assertNull($account->getRefundPassword());
    }

    public function testCreateGarantiPosAccount3DSecure(): void
    {
        $account = AccountFactory::createGarantiPosAccount(
            'garanti',
            '7000679',
            'PROVAUT',
            'pass',
            '30691298',
            'store-key',
            'PROVRFN',
            'refund-pass',
        );

        $this->assertSame('7000679', $account->getMerchantId());
        $this->assertSame('PROVAUT', $account->getUsername());
        $this->assertSame('pass', $account->getPassword());
        $this->assertSame('30691298', $account->getTerminalId());
        $this->assertSame('store-key', $account->getStoreKey());
        $this->assertSame('PROVRFN', $account->getRefundUsername());
        $this->assertSame('refund-pass', $account->getRefundPassword());
    }

    public function testCreateInterPosAccountNonSecure(): void
    {
        $account = AccountFactory::createInterPosAccount(
            'denizbank',
            'shop-code',
            'user-code',
            'user-pass',
        );

        $this->assertInstanceOf(InterPosAccount::class, $account);
        $this->assertSame('denizbank', $account->getBankName());
        $this->assertSame('shop-code', $account->getMerchantId());
        $this->assertSame('user-code', $account->getUsername());
        $this->assertSame('user-pass', $account->getPassword());
        $this->assertNull($account->getStoreKey());
    }

    public function testCreateInterPosAccount3DSecure(): void
    {
        $account = AccountFactory::createInterPosAccount(
            'denizbank',
            'shop-code',
            'user-code',
            'user-pass',
            'merchant-pass',
        );

        $this->assertSame('shop-code', $account->getMerchantId());
        $this->assertSame('user-code', $account->getUsername());
        $this->assertSame('user-pass', $account->getPassword());
        $this->assertSame('merchant-pass', $account->getStoreKey());
    }

    public function testCreatePosNetPosAccountNonSecure(): void
    {
        $account = AccountFactory::createPosNetPosAccount(
            'yapikredi',
            '6706598320',
            '67005551',
            '27426457',
        );

        $this->assertInstanceOf(PosNetPosAccount::class, $account);
        $this->assertSame('yapikredi', $account->getBankName());
        $this->assertSame('6706598320', $account->getMerchantId());
        $this->assertSame('27426457', $account->getPosNetId());
        $this->assertSame('67005551', $account->getTerminalId());
        $this->assertNull($account->getStoreKey());
    }

    public function testCreatePosNetPosAccount3DSecure(): void
    {
        $account = AccountFactory::createPosNetPosAccount(
            'yapikredi',
            '6706598320',
            '67005551',
            '27426457',
            '10,10,10,10,10,10,10,10,10,10,10,10,10,10,10,10,10,10,10,10',
        );

        $this->assertSame('6706598320', $account->getMerchantId());
        $this->assertSame('27426457', $account->getPosNetId());
        $this->assertSame('67005551', $account->getTerminalId());
        $this->assertSame('10,10,10,10,10,10,10,10,10,10,10,10,10,10,10,10,10,10,10,10', $account->getStoreKey());
    }

    public function testCreatePayFlexPosAccountStandard(): void
    {
        $account = AccountFactory::createPayFlexPosAccount(
            'vakifbank',
            'merchant-id',
            'password',
            'dVB007000000000',
        );

        $this->assertInstanceOf(PayFlexPosAccount::class, $account);
        $this->assertSame('vakifbank', $account->getBankName());
        $this->assertSame('merchant-id', $account->getMerchantId());
        $this->assertSame('password', $account->getPassword());
        $this->assertSame('dVB007000000000', $account->getTerminalId());
        $this->assertSame(PayFlexPosAccount::MERCHANT_TYPE_STANDARD, $account->getMerchantType());
        $this->assertFalse($account->isSubBranch());
        $this->assertNull($account->getSubMerchantId());
    }

    public function testCreatePayFlexPosAccountMainDealer(): void
    {
        $account = AccountFactory::createPayFlexPosAccount(
            'vakifbank',
            'merchant-id',
            'password',
            'dVB007000000000',
            PayFlexPosAccount::MERCHANT_TYPE_MAIN_DEALER,
        );

        $this->assertSame(PayFlexPosAccount::MERCHANT_TYPE_MAIN_DEALER, $account->getMerchantType());
        $this->assertFalse($account->isSubBranch());
    }

    public function testCreatePayFlexPosAccountSubDealer(): void
    {
        $account = AccountFactory::createPayFlexPosAccount(
            'vakifbank',
            'merchant-id',
            'password',
            'dVB007000000000',
            PayFlexPosAccount::MERCHANT_TYPE_SUB_DEALER,
            'sub-merchant-1',
        );

        $this->assertSame(PayFlexPosAccount::MERCHANT_TYPE_SUB_DEALER, $account->getMerchantType());
        $this->assertTrue($account->isSubBranch());
        $this->assertSame('sub-merchant-1', $account->getSubMerchantId());
    }

    public function testCreatePayFlexPosAccountThrowsExceptionForSubDealerWithoutSubMerchantId(): void
    {
        $this->expectException(MissingAccountInfoException::class);
        $this->expectExceptionMessage('SubMerchantId is required for sub branches!');

        AccountFactory::createPayFlexPosAccount(
            'vakifbank',
            'merchant-id',
            'password',
            'dVB007000000000',
            PayFlexPosAccount::MERCHANT_TYPE_SUB_DEALER,
        );
    }

    public function testCreatePayFlexPosAccountThrowsExceptionForInvalidMerchantType(): void
    {
        $this->expectException(MissingAccountInfoException::class);
        $this->expectExceptionMessage('Invalid MerchantType!');

        AccountFactory::createPayFlexPosAccount(
            'vakifbank',
            'merchant-id',
            'password',
            'dVB007000000000',
            999,
        );
    }
}
