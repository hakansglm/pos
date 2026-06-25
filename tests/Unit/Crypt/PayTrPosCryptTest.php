<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Crypt;

use InvalidArgumentException;
use Mews\Pos\Model\Account\AbstractPosAccount;
use LogicException;
use Mews\Pos\Crypt\AbstractCrypt;
use Mews\Pos\Crypt\PayTrPosCrypt;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Gateway\AkbankPos;
use Mews\Pos\Gateway\PayTrPos;
use Mews\Pos\Model\Account\PayTrPosAccount;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(PayTrPosCrypt::class)]
#[CoversClass(AbstractCrypt::class)]
class PayTrPosCryptTest extends TestCase
{
    private PayTrPosAccount $account;

    private PayTrPosCrypt $crypt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createPayTrPosAccount(
            'paytr',
            '123456',
            'YEUaNcdHXqyt7hjt',
            'wWwU8buJp6jo1r25',
        );

        $logger      = $this->createMock(LoggerInterface::class);
        $this->crypt = new PayTrPosCrypt($logger);
    }

    public function testSupports(): void
    {
        $this->assertTrue($this->crypt::supports(PayTrPos::class));
        $this->assertFalse($this->crypt::supports(AkbankPos::class));
    }

    #[DataProvider('check3DHashDataProvider')]
    public function testCheck3DHash(bool $expected, array $data): void
    {
        $this->assertSame($expected, $this->crypt->check3DHash($this->account, $data));
    }

    #[DataProvider('createHashDataProvider')]
    public function testCreateHash(array $requestData, string $expected): void
    {
        $this->assertSame($expected, $this->crypt->createHash($this->account, $requestData));
    }

    public function testCreate3DHashDelegatesToCreateHash(): void
    {
        $requestData = [
            'merchant_id'     => '123456',
            'user_ip'         => '127.0.0.1',
            'merchant_oid'    => 'order-123',
            'email'           => 'test@example.com',
            'payment_amount'  => '10050',
            'user_basket'     => 'W1siUHJvZHVjdCIsIjEwLjUwIiwxXV0=',
            'no_installment'  => '0',
            'max_installment' => '0',
            'currency'        => 'TL',
            'test_mode'       => '1',
        ];

        $this->assertSame(
            $this->crypt->createHash($this->account, $requestData),
            $this->crypt->create3DHash($this->account, $requestData)
        );
    }

    #[TestWith([null, 24])]
    #[TestWith([16, 16])]
    public function testGenerateRandomString(?int $length, int $expectedLength): void
    {
        $str = null !== $length ? $this->crypt->generateRandomString($length) : $this->crypt->generateRandomString();

        $this->assertSame($expectedLength, \strlen($str));
        $this->assertMatchesRegularExpression('/^[0-9A-F]+$/', $str);
    }

    public function testHashString(): void
    {
        $this->assertSame('HdQYuvtRLkP0h8lE1t71qtcxH/Nl25OCqp3JdoO8Sao=', $this->crypt->hashString('test-string'));
    }

    public static function check3DHashDataProvider(): array
    {
        // Real callback data captured from a 3D-Pay transaction; hash verified with known credentials.
        // Formula: base64_encode(HMAC-SHA256(merchant_oid + merchant_salt + status + total_amount, merchant_key))
        $realCallbackData = [
            'hash'         => 'ZDVOQUw4aDJhNR5dWYBC5bD95bLtSOtj9DzzSQ9sUHs=',
            'merchant_oid' => '202606234E4E',
            'status'       => 'success',
            'total_amount' => '1001',
        ];

        return [
            'success_real_callback_data' => [
                'expected' => true,
                'data'     => $realCallbackData,
            ],
            'wrong_hash' => [
                'expected' => false,
                'data'     => \array_merge($realCallbackData, ['hash' => 'wronghash==']),
            ],
            'missing_hash_key' => [
                'expected' => false,
                'data'     => \array_diff_key($realCallbackData, ['hash' => '']),
            ],
            'empty_data' => [
                'expected' => false,
                'data'     => [],
            ],
        ];
    }

    public function testHashFromParamsEmptyHashParamsValueThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('hashParamsValue cannot be empty');
        $this->crypt->hashFromParams($this->account, [], '', ':');
    }

    public function testHashFromParamsNullStoreKeyThrows(): void
    {
        $accountMock = $this->createMock(AbstractPosAccount::class);
        $accountMock->method('getStoreKey')->willReturn(null);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Account storeKey eksik!');
        $this->crypt->hashFromParams($accountMock, ['merchant_id' => '123'], 'merchant_id', ':');
    }

    public static function createHashDataProvider(): array
    {
        return [
            // History
            'history' => [
                'requestData' => [
                    'merchant_id' => '123456',
                    'start_date'  => '2026-06-01 00:00:00',
                    'end_date'    => '2026-06-03 23:59:59',
                ],
                'expected' => 'wlPtqQINumrhu5CNYON0mQzbzhSLWM8699d9htCoy14=',
            ],
            // iFrame token
            'iframe_token' => [
                'requestData' => [
                    'merchant_id'     => '123456',
                    'user_ip'         => '127.0.0.1',
                    'merchant_oid'    => 'order-123',
                    'email'           => 'test@example.com',
                    'payment_amount'  => '10050',
                    'user_basket'     => 'W1siUHJvZHVjdCIsIjEwLjUwIiwxXV0=',
                    'no_installment'  => '0',
                    'max_installment' => '0',
                    'currency'        => 'TL',
                    'test_mode'       => '1',
                ],
                'expected' => 'O5CaLankrigiiPDh3G75lLWdRX6wrEXGbLRjt37tuzY=',
            ],
            // Direct (non-3D) payment:
            'direct_payment' => [
                'requestData' => [
                    'merchant_id'       => '123456',
                    'user_ip'           => '127.0.0.1',
                    'merchant_oid'      => 'order-456',
                    'email'             => 'test@example.com',
                    'payment_amount'    => '5000',
                    'payment_type'      => 'card',
                    'installment_count' => '0',
                    'currency'          => 'TL',
                    'test_mode'         => '1',
                    'non_3d'            => '1',
                ],
                'expected' => 'guwHEsodarZg3KTuihE7fpb+qAdKI0IQMUkrGFWZDO0=',
            ],
            // Refund:
            'refund' => [
                'requestData' => [
                    'merchant_id'   => '123456',
                    'merchant_oid'  => 'order-789',
                    'return_amount' => '5000',
                ],
                'expected' => '7ZqQEds0nem2gCqpLltwuNlmV9f7KHVlc73qeiNjwMg=',
            ],
            // Order status (else branch):
            'status_query' => [
                'requestData' => [
                    'merchant_id'  => '123456',
                    'merchant_oid' => 'order-999',
                ],
                'expected' => 'mIFJNpzMuo/gd9pcPNBinujmMVlEkR3DHZ6stLoQo/s=',
            ],
        ];
    }
}
