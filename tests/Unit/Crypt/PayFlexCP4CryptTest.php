<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Crypt;

use Mews\Pos\Crypt\AbstractCrypt;
use Mews\Pos\Crypt\PayFlexCPV4Crypt;
use Mews\Pos\Entity\Account\PayFlexPosAccount;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Gateways\AssecoPos;
use Mews\Pos\Gateways\PayFlexCPV4Pos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(PayFlexCPV4Crypt::class)]
#[CoversClass(AbstractCrypt::class)]
class PayFlexCP4CryptTest extends TestCase
{
    public PayFlexCPV4Crypt $crypt;

    private PayFlexPosAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createPayFlexPosAccount(
            'vakifbank-cp',
            '000000000111111',
            '3XTgER89as',
            'VP999999',
            PosInterface::MODEL_3D_SECURE
        );

        $logger      = $this->createMock(LoggerInterface::class);
        $this->crypt = new PayFlexCPV4Crypt($logger);
    }

    public function testSupports(): void
    {
        $supports = $this->crypt::supports(PayFlexCPV4Pos::class);
        $this->assertTrue($supports);

        $supports = $this->crypt::supports(AssecoPos::class);
        $this->assertFalse($supports);
    }

    public function testCreate3DHash(): void
    {
        $this->expectException(NotImplementedException::class);

        $this->crypt->create3DHash($this->account, []);
    }

    /**
     * @dataProvider hashCreateDataProvider
     */
    public function testCreateHash(array $requestData, string $expected): void
    {
        $actual = $this->crypt->createHash($this->account, $requestData);

        $this->assertSame($expected, $actual);
    }

    public function testCheck3DHash(): void
    {
        $this->expectException(NotImplementedException::class);

        $this->crypt->check3DHash($this->account, []);
    }

    public static function hashCreateDataProvider(): array
    {
        return [
            [
                'requestData' => [
                    'HostMerchantId'   => '000000000111111',
                    'MerchantPassword' => '3XTgER89as',
                    'AmountCode'       => '949',
                    'Amount'           => '10.10',
                ],
                'expected'    => '/MfLewtkUjpN5e/RY2iuIoT72hk=',
            ],
        ];
    }
}
