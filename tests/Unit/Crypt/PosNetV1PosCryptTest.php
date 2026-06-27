<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Crypt;

use Mews\Pos\Crypt\AbstractCrypt;
use InvalidArgumentException;
use LogicException;
use Mews\Pos\Crypt\PosNetV1PosCrypt;
use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Model\Account\PosNetPosAccount;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\PosNetV1Pos;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(PosNetV1PosCrypt::class)]
#[CoversClass(AbstractCrypt::class)]
class PosNetV1PosCryptTest extends TestCase
{
    public PosNetV1PosCrypt $crypt;

    private PosNetPosAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createPosNetPosAccount(
            'albaraka',
            '6700950031',
            '67540050',
            '1010272261352072',
            '10,10,10,10,10,10,10,10'
        );

        $logger      = $this->createMock(LoggerInterface::class);
        $this->crypt = new PosNetV1PosCrypt($logger);
    }

    public function testSupports(): void
    {
        $supports = $this->crypt::supports(PosNetV1Pos::class);
        $this->assertTrue($supports);

        $supports = $this->crypt::supports(AssecoPos::class);
        $this->assertFalse($supports);
    }

    #[DataProvider('hashFromParamsDataProvider')]
    public function testHashFromParams(array $data, string $expected): void
    {
        $this->assertSame($expected, $this->crypt->hashFromParams($this->account, $data, $data['MACParams'], ':'));
    }

    public function testHashFromParamsWhenNotFound(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('hashParamsValue cannot be empty');
        $this->crypt->hashFromParams($this->account, [], '', ':');
    }

    #[DataProvider('hashCreateDataProvider')]
    public function testCreateHash(array $requestData, string $expected): void
    {
        $actual = $this->crypt->createHash($this->account, $requestData);
        $this->assertSame($expected, $actual);
    }


    #[DataProvider('threeDHashCreateDataProvider')]
    public function testCreate3DHash(array $requestData, string $expected): void
    {
        $actual = $this->crypt->create3DHash($this->account, $requestData);

        $this->assertSame($expected, $actual);
    }

    #[DataProvider('threeDHashCheckDataProvider')]
    public function testCheck3DHash(bool $expected, array $responseData): void
    {
        $this->assertSame($expected, $this->crypt->check3DHash($this->account, $responseData));
    }

    public function testCheck3DHashException(): void
    {
        $account = $this->createMock(AbstractPosAccount::class);
        $this->expectException(LogicException::class);
        $this->crypt->check3DHash($account, []);
    }

    public static function threeDHashCreateDataProvider(): array
    {
        return [
            [
                'requestData' => [
                    'MerchantNo'  => '6700950031',
                    'TerminalNo'  => '67540050',
                    'Amount'      => '175',
                    'CardNo'      => '5400619360964581',
                    'ExpiredDate' => '2001',
                    'Cvv'         => '056',
                ],
                'expected'    => 'xuhPbpcPJ6kVs7JeIXS8f06Cv0mb9cNPMfjp1HiB7Ew=',
            ],
        ];
    }

    public static function hashFromParamsDataProvider(): array
    {
        return [
            [
                'data'     => [
                    'MACParams'     => 'MerchantNo:TerminalNo:ReferenceCode:OrderId',
                    'MerchantNo'    => '6700950031',
                    'TerminalNo'    => '67540050',
                    'ReferenceCode' => '021459486690000191',
                    'OrderId'       => null,
                ],
                'expected' => 'qhLo/2Ro+vT81i0SMV/VHifDV9VzQQgK+7d8hlId9YM=',
            ],
            [
                'data'     => [
                    'MerchantNo'          => '6700950031',
                    'TerminalNo'          => '67540050',
                    'MACParams'           => 'MerchantNo:TerminalNo:CardNo:Cvc2:ExpireDate:Amount',
                    'CardInformationData' => [
                        'Amount'     => '175',
                        'CardNo'     => '5400619360964581',
                        'ExpireDate' => '2001',
                        'Cvc2'       => '056',
                    ],
                ],
                'expected' => 'xuhPbpcPJ6kVs7JeIXS8f06Cv0mb9cNPMfjp1HiB7Ew=',
            ],
        ];
    }

    public static function threeDHashCheckDataProvider(): array
    {
        return [
            [
                'expectedResult' => true,
                'responseData'   => [
                    'ECI'                 => '02',
                    'CAVV'                => 'jKOBaLBL3hQ+CREBPu1HBQQAAAA=',
                    'MdStatus'            => '1',
                    'MdErrorMessage'      => 'Authenticated',
                    'MD'                  => '0161010028947569644,0161010028947569644',
                    'SecureTransactionId' => '1010028947569644',
                    'Mac'                 => 'r21kMm4nMqvJakjq47Jl+3fk2xrFPrDoTJFQGxkgkfk=',
                    'MacParams'           => 'ECI:CAVV:MdStatus:MdErrorMessage:MD:SecureTransactionId',
                    'OrderId'             => '123',
                ],
            ],
            [
                'expectedResult' => false,
                'responseData'   => [
                    'ECI'                 => '',
                    'CAVV'                => 'jKOBaLBL3hQ+CREBPu1HBQQAAAA=',
                    'MdStatus'            => '1',
                    'MdErrorMessage'      => 'Authenticated',
                    'MD'                  => '0161010028947569644,0161010028947569644',
                    'SecureTransactionId' => '1010028947569644',
                    'Mac'                 => 'r21kMm4nMqvJakjq47Jl+3fk2xrFPrDoTJFQGxkgkfk=',
                    'MacParams'           => 'ECI:CAVV:MdStatus:MdErrorMessage:MD:SecureTransactionId',
                    'OrderId'             => '123',
                ],
            ],
        ];
    }

    public static function hashCreateDataProvider(): array
    {
        return [
            [
                'requestData' => [
                    'MerchantNo'       => '6700950031',
                    'TerminalNo'       => '67540050',
                    'ThreeDSecureData' => [
                        'MerchantNo'          => '6700950031',
                        'TerminalNo'          => '67540050',
                        'SecureTransactionId' => '1010028947569644',
                        'CavvData'            => 'jKOBaLBL3hQ+CREBPu1HBQQAAAA=',
                        'Eci'                 => '02',
                        'MdStatus'            => '1',
                    ],
                ],
                'expected'    => 'kAKxvbwXvmrM6lapGx1UcRTs454tsSuPrBXV7oA7L7w=',
            ],
            [
                'requestData' => [
                    'MerchantNo'       => '6700950031',
                    'TerminalNo'       => '67540050',
                    'ThreeDSecureData' => [
                        'MerchantNo'          => '6700950031',
                        'TerminalNo'          => '67540050',
                        'SecureTransactionId' => '1010028947569644',
                        'CavvData'            => 'jKOBaLBL3hQ+CREBPu1HBQQAAAA=',
                        'Eci'                 => '02',
                        'MdStatus'            => '',
                    ],
                ],
                'expected'    => 'RRYxriTO++OHc4cQ3VIp0z9HMrFy/Msm3Dw2t+sEXCA=',
            ],
        ];
    }
}
