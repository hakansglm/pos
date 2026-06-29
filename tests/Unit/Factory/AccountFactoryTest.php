<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Factory;

use DomainException;
use Mews\Pos\Exception\MissingAccountInfoException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Gateway\AkbankPos;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\GarantiPos;
use Mews\Pos\Gateway\InterPos;
use Mews\Pos\Gateway\IyzicoPos;
use Mews\Pos\Gateway\KuveytPos;
use Mews\Pos\Gateway\Param3DHostPos;
use Mews\Pos\Gateway\ParamPos;
use Mews\Pos\Gateway\PayFlexCPV4Pos;
use Mews\Pos\Gateway\PayFlexV4Pos;
use Mews\Pos\Gateway\PayForPos;
use Mews\Pos\Gateway\PayTrPos;
use Mews\Pos\Gateway\PosNetPos;
use Mews\Pos\Gateway\PosNetV1Pos;
use Mews\Pos\Gateway\ToslaPos;
use Mews\Pos\Gateway\VakifKatilimPos;
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
use PHPUnit\Framework\Attributes\DataProvider;
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
        $this->assertSame('store-key', $account->getSecretKey());
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
        $this->assertSame('kdsnsksl', $account->getSecretKey());
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
        $this->assertSame('kdsnsksl', $account->getSecretKey());
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
        $this->assertSame('kdsnsksl', $account->getSecretKey());
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
        $this->assertSame('guid123', $account->getSecretKey());
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
        $this->assertSame('12345678', $account->getSecretKey());
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
        $this->assertSame('api-secret-key', $account->getSecretKey());
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
        $this->assertSame('api-secret-key', $account->getSecretKey());
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
        $this->assertSame('YEUaNcdHXqyt7hjt', $account->getSecretKey());
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
        $this->assertSame('api-pass', $account->getSecretKey());
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
        $this->assertSame('store-key', $account->getSecretKey());
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
        $this->assertSame('merchant-pass', $account->getSecretKey());
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
        $this->assertSame('10,10,10,10,10,10,10,10,10,10,10,10,10,10,10,10,10,10,10,10', $account->getSecretKey());
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

    /**
     * @dataProvider createForGatewayDataProvider
     */
    #[DataProvider('createForGatewayDataProvider')]
    public function testCreateForGateway(string $gatewayClass, string $bank, array $credentials, string $expectedAccountClass): void
    {
        $account = AccountFactory::createForGateway($gatewayClass, $bank, $credentials);

        $this->assertInstanceOf($expectedAccountClass, $account);
        $this->assertSame($bank, $account->getBankName());
    }

    public static function createForGatewayDataProvider(): array
    {
        return [
            'AssecoPos without enc_key' => [
                AssecoPos::class,
                'akbank',
                ['merchant_id' => 'mid', 'user_name' => 'user', 'user_password' => 'pass'],
                AssecoPosAccount::class,
            ],
            'AssecoPos with enc_key' => [
                AssecoPos::class,
                'akbank',
                ['merchant_id' => 'mid', 'user_name' => 'user', 'user_password' => 'pass', 'secret_key' => 'sk'],
                AssecoPosAccount::class,
            ],
            'AkbankPos without sub_merchant_id' => [
                AkbankPos::class,
                'akbank-pos',
                ['merchant_id' => 'msid', 'terminal_id' => 'tsid', 'secret_key' => 'skey'],
                AkbankPosAccount::class,
            ],
            'AkbankPos with sub_merchant_id' => [
                AkbankPos::class,
                'akbank-pos',
                ['merchant_id' => 'msid', 'terminal_id' => 'tsid', 'secret_key' => 'skey', 'sub_merchant_id' => 'sub1'],
                AkbankPosAccount::class,
            ],
            'GarantiPos minimal' => [
                GarantiPos::class,
                'garanti',
                ['merchant_id' => '7000679', 'user_name' => 'PROVAUT', 'user_password' => 'pass', 'terminal_id' => '30691298'],
                GarantiPosAccount::class,
            ],
            'GarantiPos full' => [
                GarantiPos::class,
                'garanti',
                [
                    'merchant_id'          => '7000679',
                    'user_name'            => 'PROVAUT',
                    'user_password'        => 'pass',
                    'terminal_id'          => '30691298',
                    'secret_key'              => 'sk',
                    'refund_user_name'     => 'PROVRFN',
                    'refund_user_password' => 'refpass',
                ],
                GarantiPosAccount::class,
            ],
            'InterPos without enc_key' => [
                InterPos::class,
                'denizbank',
                ['merchant_id' => 'shop-code', 'user_name' => 'user', 'user_password' => 'pass'],
                InterPosAccount::class,
            ],
            'InterPos with enc_key' => [
                InterPos::class,
                'denizbank',
                ['merchant_id' => 'shop-code', 'user_name' => 'user', 'user_password' => 'pass', 'secret_key' => 'mpass'],
                InterPosAccount::class,
            ],
            'IyzicoPos without sub_merchant_id' => [
                IyzicoPos::class,
                'iyzico',
                ['merchant_id' => 'ak', 'secret_key' => 'sk'],
                IyzicoPosAccount::class,
            ],
            'IyzicoPos with sub_merchant_id' => [
                IyzicoPos::class,
                'iyzico',
                ['merchant_id' => 'ak', 'secret_key' => 'sk', 'sub_merchant_id' => 'smk'],
                IyzicoPosAccount::class,
            ],
            'KuveytPos' => [
                KuveytPos::class,
                'kuveyt-turk',
                ['merchant_id' => 'mid', 'user_name' => 'user', 'terminal_id' => 'cid', 'secret_key' => 'sk'],
                BoaPosAccount::class,
            ],
            'VakifKatilimPos with sub_merchant_id' => [
                VakifKatilimPos::class,
                'vakif-katilim',
                ['merchant_id' => 'mid', 'user_name' => 'user', 'terminal_id' => 'cid', 'secret_key' => 'sk', 'sub_merchant_id' => 'sub1'],
                BoaPosAccount::class,
            ],
            'ParamPos' => [
                ParamPos::class,
                'param',
                ['merchant_id' => '12345', 'user_name' => 'user', 'user_password' => 'pass', 'secret_key' => 'guid123'],
                ParamPosAccount::class,
            ],
            'Param3DHostPos' => [
                Param3DHostPos::class,
                'param',
                ['merchant_id' => '12345', 'user_name' => 'user', 'user_password' => 'pass', 'secret_key' => 'guid123'],
                ParamPosAccount::class,
            ],
            'PayFlexV4Pos standard' => [
                PayFlexV4Pos::class,
                'vakifbank',
                ['merchant_id' => 'mid', 'user_password' => 'pass', 'terminal_id' => 'dVB007'],
                PayFlexPosAccount::class,
            ],
            'PayFlexCPV4Pos with merchant_type' => [
                PayFlexCPV4Pos::class,
                'vakifbank',
                ['merchant_id' => 'mid', 'user_password' => 'pass', 'terminal_id' => 'dVB007', 'merchant_type' => (string) PayFlexPosAccount::MERCHANT_TYPE_MAIN_DEALER],
                PayFlexPosAccount::class,
            ],
            'PayForPos without enc_key' => [
                PayForPos::class,
                'qnbfinansbank-payfor',
                ['merchant_id' => '085300000009704', 'user_name' => 'QNB_API', 'user_password' => 'UcBN0'],
                PayForPosAccount::class,
            ],
            'PayForPos with enc_key and mbr_id' => [
                PayForPos::class,
                'qnbfinansbank-payfor',
                ['merchant_id' => '085300000009704', 'user_name' => 'QNB_API', 'user_password' => 'UcBN0', 'secret_key' => '12345678', 'mbr_id' => PayForPosAccount::MBR_ID_ZIRAAT_KATILIM],
                PayForPosAccount::class,
            ],
            'PayTrPos' => [
                PayTrPos::class,
                'paytr',
                ['merchant_id' => '123456', 'user_password' => 'wWwU8buJp6jo1r25', 'secret_key' => 'YEUaNcdHXqyt7hjt'],
                PayTrPosAccount::class,
            ],
            'PosNetPos without enc_key' => [
                PosNetPos::class,
                'yapikredi',
                ['merchant_id' => '6706598320', 'terminal_id' => '67005551', 'user_name' => '27426457'],
                PosNetPosAccount::class,
            ],
            'PosNetV1Pos with enc_key' => [
                PosNetV1Pos::class,
                'yapikredi',
                ['merchant_id' => '6706598320', 'terminal_id' => '67005551', 'user_name' => '27426457', 'secret_key' => '10,10,10,10,10,10,10,10,10,10,10,10,10,10,10,10,10,10,10,10'],
                PosNetPosAccount::class,
            ],
            'ToslaPos' => [
                ToslaPos::class,
                'tosla',
                ['merchant_id' => 'mid', 'user_name' => 'api-user', 'secret_key' => 'api-pass'],
                ToslaPosAccount::class,
            ],
        ];
    }

    public function testCreateThrowsDomainExceptionForUnknownGateway(): void
    {
        $this->expectException(DomainException::class);

        AccountFactory::createForGateway('UnknownGateway', 'bank', []);
    }

    public function testCreateAssecoPosSetsCredentialsCorrectly(): void
    {
        $account = AccountFactory::createForGateway(AssecoPos::class, 'akbank', [
            'merchant_id'   => 'mid',
            'user_name'     => 'user',
            'user_password' => 'pass',
            'secret_key'    => 'sk',
        ]);

        $this->assertSame('mid', $account->getMerchantId());
        $this->assertSame('user', $account->getUsername());
        $this->assertSame('pass', $account->getPassword());
        $this->assertSame('sk', $account->getSecretKey());
    }

    public function testCreateAkbankPosSetsCredentialsCorrectly(): void
    {
        $account = AccountFactory::createForGateway(AkbankPos::class, 'akbank-pos', [
            'merchant_id'     => 'msid',
            'terminal_id'     => 'tsid',
            'secret_key'      => 'skey',
            'sub_merchant_id' => 'sub1',
        ]);

        $this->assertInstanceOf(AkbankPosAccount::class, $account);
        $this->assertSame('msid', $account->getMerchantId());
        $this->assertSame('tsid', $account->getTerminalId());
        $this->assertSame('skey', $account->getSecretKey());
        $this->assertSame('sub1', $account->getSubMerchantId());
    }

    public function testCreateGarantiPosSetsCredentialsCorrectly(): void
    {
        $account = AccountFactory::createForGateway(GarantiPos::class, 'garanti', [
            'merchant_id'          => '7000679',
            'user_name'            => 'PROVAUT',
            'user_password'        => 'pass',
            'terminal_id'          => '30691298',
            'secret_key'           => 'sk',
            'refund_user_name'     => 'PROVRFN',
            'refund_user_password' => 'refpass',
        ]);

        $this->assertInstanceOf(GarantiPosAccount::class, $account);
        $this->assertSame('7000679', $account->getMerchantId());
        $this->assertSame('PROVAUT', $account->getUsername());
        $this->assertSame('pass', $account->getPassword());
        $this->assertSame('30691298', $account->getTerminalId());
        $this->assertSame('sk', $account->getSecretKey());
        $this->assertSame('PROVRFN', $account->getRefundUsername());
        $this->assertSame('refpass', $account->getRefundPassword());
    }

    public function testCreateIyzicoPosSetsCredentialsCorrectly(): void
    {
        $account = AccountFactory::createForGateway(IyzicoPos::class, 'iyzico', [
            'merchant_id'     => 'ak',
            'secret_key'      => 'sk',
            'sub_merchant_id' => 'smk',
        ]);

        $this->assertInstanceOf(IyzicoPosAccount::class, $account);
        $this->assertSame('ak', $account->getMerchantId());
        $this->assertSame('sk', $account->getSecretKey());
        $this->assertSame('smk', $account->getSubMerchantId());
    }

    public function testCreateKuveytPosSetsCredentialsCorrectly(): void
    {
        $account = AccountFactory::createForGateway(KuveytPos::class, 'kuveyt-turk', [
            'merchant_id' => 'mid',
            'user_name'   => 'user',
            'terminal_id' => 'cid',
            'secret_key'  => 'sk',
        ]);

        $this->assertInstanceOf(BoaPosAccount::class, $account);
        $this->assertSame('mid', $account->getMerchantId());
        $this->assertSame('user', $account->getUsername());
        $this->assertSame('cid', $account->getCustomerId());
        $this->assertSame('sk', $account->getSecretKey());
        $this->assertNull($account->getSubMerchantId());
    }

    public function testCreateParamPosSetsCredentialsCorrectly(): void
    {
        $account = AccountFactory::createForGateway(ParamPos::class, 'param', [
            'merchant_id'   => '12345',
            'user_name'     => 'user',
            'user_password' => 'pass',
            'secret_key'    => 'guid123',
        ]);

        $this->assertInstanceOf(ParamPosAccount::class, $account);
        $this->assertSame('12345', $account->getMerchantId());
        $this->assertSame('user', $account->getUsername());
        $this->assertSame('pass', $account->getPassword());
        $this->assertSame('guid123', $account->getSecretKey());
    }

    public function testCreatePayFlexPosSetsCredentialsCorrectly(): void
    {
        $account = AccountFactory::createForGateway(PayFlexV4Pos::class, 'vakifbank', [
            'merchant_id'   => 'mid',
            'user_password' => 'pass',
            'terminal_id'   => 'dVB007',
        ]);

        $this->assertInstanceOf(PayFlexPosAccount::class, $account);
        $this->assertSame('mid', $account->getMerchantId());
        $this->assertSame('pass', $account->getPassword());
        $this->assertSame('dVB007', $account->getTerminalId());
        $this->assertSame(PayFlexPosAccount::MERCHANT_TYPE_STANDARD, $account->getMerchantType());
    }

    public function testCreatePayForPosSetsCredentialsCorrectly(): void
    {
        $account = AccountFactory::createForGateway(PayForPos::class, 'qnbfinansbank-payfor', [
            'merchant_id'   => '085300000009704',
            'user_name'     => 'QNB_API',
            'user_password' => 'UcBN0',
            'secret_key'    => '12345678',
            'mbr_id'        => PayForPosAccount::MBR_ID_ZIRAAT_KATILIM,
        ]);

        $this->assertInstanceOf(PayForPosAccount::class, $account);
        $this->assertSame('085300000009704', $account->getMerchantId());
        $this->assertSame('12345678', $account->getSecretKey());
        $this->assertSame(PayForPosAccount::MBR_ID_ZIRAAT_KATILIM, $account->getMbrId());
    }

    public function testCreatePayTrPosSetsCredentialsCorrectly(): void
    {
        $account = AccountFactory::createForGateway(PayTrPos::class, 'paytr', [
            'merchant_id'   => '123456',
            'user_password' => 'wWwU8buJp6jo1r25',
            'secret_key'    => 'YEUaNcdHXqyt7hjt',
        ]);

        $this->assertInstanceOf(PayTrPosAccount::class, $account);
        $this->assertSame('123456', $account->getMerchantId());
        $this->assertSame('wWwU8buJp6jo1r25', $account->getPassword());
        $this->assertSame('YEUaNcdHXqyt7hjt', $account->getSecretKey());
    }

    public function testCreatePosNetPosSetsCredentialsCorrectly(): void
    {
        $account = AccountFactory::createForGateway(PosNetPos::class, 'yapikredi', [
            'merchant_id' => '6706598320',
            'terminal_id' => '67005551',
            'user_name'   => '27426457',
            'secret_key'  => '10,10,10,10,10,10,10,10,10,10,10,10,10,10,10,10,10,10,10,10',
        ]);

        $this->assertInstanceOf(PosNetPosAccount::class, $account);
        $this->assertSame('6706598320', $account->getMerchantId());
        $this->assertSame('27426457', $account->getPosNetId());
        $this->assertSame('67005551', $account->getTerminalId());
        $this->assertSame('10,10,10,10,10,10,10,10,10,10,10,10,10,10,10,10,10,10,10,10', $account->getSecretKey());
    }

    public function testCreateToslaPosSetsCredentialsCorrectly(): void
    {
        $account = AccountFactory::createForGateway(ToslaPos::class, 'tosla', [
            'merchant_id' => 'mid',
            'user_name'   => 'api-user',
            'secret_key'  => 'api-pass',
        ]);

        $this->assertInstanceOf(ToslaPosAccount::class, $account);
        $this->assertSame('mid', $account->getMerchantId());
        $this->assertSame('api-user', $account->getUsername());
        $this->assertSame('api-pass', $account->getSecretKey());
    }
}
