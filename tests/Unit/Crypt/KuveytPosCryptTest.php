<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Crypt;

use LogicException;
use Mews\Pos\Crypt\AbstractCrypt;
use Mews\Pos\Crypt\KuveytPosCrypt;
use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Model\Account\BoaPosAccount;
use Mews\Pos\Exception\NotImplementedException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\KuveytPos;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(KuveytPosCrypt::class)]
#[CoversClass(AbstractCrypt::class)]
class KuveytPosCryptTest extends TestCase
{
    public KuveytPosCrypt $crypt;

    private BoaPosAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createBoaPosAccount(
            'kuveytpos',
            '80',
            'apiuser',
            '400235',
            'Api123'
        );

        $logger      = $this->createMock(LoggerInterface::class);
        $this->crypt = new KuveytPosCrypt($logger);
    }

    public function testSupports(): void
    {
        $supports = $this->crypt::supports(KuveytPos::class);
        $this->assertTrue($supports);

        $supports = $this->crypt::supports(AssecoPos::class);
        $this->assertFalse($supports);
    }

    public function testHashString(): void
    {
        $actual = $this->crypt->hashString('123');

        $this->assertSame('QL0AFWMIX8NRZTKeof9cXsvbvu8=', $actual);
    }

    public function testCreate3DHashException(): void
    {
        $this->expectException(NotImplementedException::class);

        $this->crypt->create3DHash($this->account, []);
    }

    public function testCheck3DHash(): void
    {
        $this->expectException(NotImplementedException::class);

        $this->crypt->check3DHash($this->account, []);
    }

    #[DataProvider('hashCreateDataProvider')]
    public function testCreateHash(array $requestData, string $expected): void
    {
        $actual = $this->crypt->createHash($this->account, $requestData);

        $this->assertSame($expected, $actual);
    }

    public function testCreateHashException(): void
    {
        $account = $this->createMock(AbstractPosAccount::class);
        $this->expectException(LogicException::class);
        $this->crypt->createHash($account, []);
    }

    public static function hashCreateDataProvider(): array
    {
        return [
            [
                'requestData' => [
                    'MerchantId'      => '80',
                    'UserName'        => 'apiuser',
                    'MerchantOrderId' => 'ORDER-123',
                    'Amount'          => 7256,
                ],
                'expected'    => 'Bf+hZf2c1gf1pTXnEaSGxDpGRr0=',
            ],
            [
                'requestData' => [
                    'MerchantId'      => '80',
                    'UserName'        => 'apiuser',
                    'MerchantOrderId' => 'ORDER-123',
                    'Amount'          => 7256,
                    'OkUrl'           => 'http://localhost:44785/Home/Success',
                    'FailUrl'         => 'http://localhost:44785/Home/Fail',
                ],
                'expected'    => 'P3a0zjAklu2g8XDJfTx2qvwHH8g=',
            ],
        ];
    }
}
