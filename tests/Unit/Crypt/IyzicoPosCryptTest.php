<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Crypt;

use Mews\Pos\Crypt\AbstractCrypt;
use Mews\Pos\Crypt\IyzicoPosCrypt;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\IyzicoPosAccount;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\Gateways\IyzicoPos;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(IyzicoPosCrypt::class)]
#[CoversClass(AbstractCrypt::class)]
class IyzicoPosCryptTest extends TestCase
{
    private IyzicoPosAccount $account;

    private IyzicoPosCrypt $crypt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createIyzicoPosAccount(
            'iyzico',
            'sandbox-apiKey',
            'sandbox-secretKey'
        );

        $logger      = $this->createMock(LoggerInterface::class);
        $this->crypt = new IyzicoPosCrypt($logger);
    }

    public function testSupports(): void
    {
        $this->assertTrue($this->crypt::supports(IyzicoPos::class));
        $this->assertFalse($this->crypt::supports(AkbankPos::class));
    }

    public function testCreate3DHash(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->crypt->create3DHash($this->account, []);
    }

    /**
     * @dataProvider createHashDataProvider
     */
    public function testCreateHash(array $requestData, string $expected): void
    {
        $actual = $this->crypt->createHash($this->account, $requestData);

        $this->assertSame($expected, $actual);
    }

    public function testHashStringRequiresKey(): void
    {
        $this->expectException(\LogicException::class);
        $this->crypt->hashString('data');
    }

    /**
     * @dataProvider hashStringDataProvider
     */
    public function testHashStringReturnsBin2Hex(string $str, string $key, string $expected): void
    {
        $this->assertSame($expected, $this->crypt->hashString($str, $key));
    }

    public function testCheck3DHashRequiresIyzicoPosAccount(): void
    {
        $account = $this->createMock(AbstractPosAccount::class);
        $this->expectException(\LogicException::class);
        $this->crypt->check3DHash($account, []);
    }

    /**
     * @dataProvider check3DHashDataProvider
     */
    public function testCheck3DHash(bool $expected, array $responseData): void
    {
        $this->assertSame($expected, $this->crypt->check3DHash($this->account, $responseData));
    }

    #[TestWith([null, 24])]
    #[TestWith([16, 16])]
    public function testGenerateRandomString(?int $length, int $expectedLength): void
    {
        $str = null !== $length ? $this->crypt->generateRandomString($length) : $this->crypt->generateRandomString();

        $this->assertSame($expectedLength, \strlen($str));
        $this->assertMatchesRegularExpression('/^[0-9A-F]+$/', $str);
    }

    /**
     * @dataProvider hashFromParamsDataProvider
     */
    public function testHashFromParams(string $storeKey, array $data, string $hashParamsKey, string $expected): void
    {
        $this->assertSame($expected, $this->crypt->hashFromParams($storeKey, $data, $hashParamsKey));
    }

    public function testHashFromParamsWhenNotFound(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"hashParams" key not found in data');
        $this->crypt->hashFromParams('key', ['a' => '1'], 'hashParams');
    }

    public static function createHashDataProvider(): array
    {
        return [
            'default' => [
                'requestData' => [
                    'rnd'         => 'abc123',
                    'uri'         => '/payment/auth',
                    'requestBody' => '{"key":"value"}',
                ],
                // HMAC-SHA256('abc123/payment/auth{"key":"value"}', 'sandbox-secretKey') → hex
                'expected'    => 'cdf060d3da2bb89928cc9110c50594b47239deb879daf0fb72a556fcb9c0f563',
            ],
        ];
    }

    public static function hashStringDataProvider(): array
    {
        return [
            'default' => [
                'str'      => 'test-string',
                'key'      => 'secret',
                // HMAC-SHA256('test-string', 'secret') → hex
                'expected' => '03359f1240bd7dd721e166122248951b5c6c86f517ea9d9a898af29100e9325a',
            ],
        ];
    }

    public static function hashFromParamsDataProvider(): array
    {
        return [
            'with_hash_params'        => [
                'storeKey'      => 'sandbox-secretKey',
                'data'          => ['orderId' => 'order-1', 'amount' => '100', 'hashParams' => 'orderId:amount'],
                'hashParamsKey' => 'hashParams',
                // HMAC-SHA256('order-1100sandbox-secretKey', 'sandbox-secretKey') → hex
                'expected'      => 'd45f9cd95075babe50ef5e10e3891f3dfdb9fca24dc1154dbf0bcb3833244b55',
            ],

        ];
    }

    public static function check3DHashDataProvider(): array
    {
        $data = [
            'conversationData' => 'conv-data',
            'conversationId'   => 'conv-id-1',
            'mdStatus'         => '1',
            'paymentId'        => 'pay-001',
            'status'           => 'success',
        ];

        // HMAC-SHA256('conv-data:conv-id-1:1:pay-001:success', 'sandbox-secretKey') → hex
        $validSig = '5d94003eabf9905de331bd8f03391f5fb1d07a1f722095cd9056c23f4526180c';

        return [
            'success'           => [
                'expected'     => true,
                'responseData' => array_merge($data, ['signature' => $validSig]),
            ],
            'wrong_signature'   => [
                'expected'     => false,
                'responseData' => array_merge($data, ['signature' => 'wrong-sig']),
            ],
            'missing_signature' => [
                'expected'     => false,
                'responseData' => $data,
            ],
            'missing_fields'    => [
                'expected'     => false,
                'responseData' => ['signature' => 'anything'],
            ],
        ];
    }
}
