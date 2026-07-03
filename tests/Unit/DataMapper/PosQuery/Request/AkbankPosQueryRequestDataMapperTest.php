<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\PosQuery\Request;

use DateTimeImmutable;
use Generator;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\PosQuery\Request\AbstractQueryRequestDataMapper;
use Mews\Pos\DataMapper\PosQuery\Request\AkbankPosQueryRequestDataMapper;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\RequestValueFormatterFactory;
use Mews\Pos\Factory\RequestValueMapperFactory;
use Mews\Pos\Gateway\AkbankPos;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Model\Account\AkbankPosAccount;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(AkbankPosQueryRequestDataMapper::class)]
#[CoversClass(AbstractQueryRequestDataMapper::class)]
class AkbankPosQueryRequestDataMapperTest extends TestCase
{
    private AkbankPosAccount $account;

    private AkbankPosQueryRequestDataMapper $mapper;

    /** @var CryptInterface & MockObject */
    private MockObject $cryptMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createAkbankPosAccount(
            'akbank-pos',
            '2023090417500272654BD9A49CF07574',
            '2023090417500284633D137A249DBBEB',
            '3230323330393034313735303032363031353172675f357637355f3273387373745f7233725f73323333383737335f323272383774767276327672323531355f'
        );

        $this->cryptMock = $this->createMock(CryptInterface::class);
        $this->mapper    = new AkbankPosQueryRequestDataMapper(
            RequestValueMapperFactory::createForGateway(AkbankPos::class),
            RequestValueFormatterFactory::createForGateway(AkbankPos::class),
            $this->cryptMock
        );
    }

    public function testSupports(): void
    {
        $this->assertTrue(AkbankPosQueryRequestDataMapper::supports(AkbankPos::class));
        $this->assertFalse(AkbankPosQueryRequestDataMapper::supports(AssecoPos::class));
    }

    public function testIsTestMode(): void
    {
        $this->assertFalse($this->mapper->isTestMode());
        $this->mapper->setTestMode(true);
        $this->assertTrue($this->mapper->isTestMode());
    }

    #[DataProvider('createCustomQueryRequestDataDataProvider')]
    public function testCreateCustomQueryRequestData(array $requestData, array $expected): void
    {
        $this->cryptMock->expects(self::once())
            ->method('generateRandomString')
            ->willReturn($expected['randomNumber']);

        $actual = $this->mapper->createCustomQueryRequestData($this->account, $requestData);

        $this->assertSame(23, \strlen((string) $actual['requestDateTime']));
        unset($actual['requestDateTime'], $expected['requestDateTime']);

        \ksort($actual);
        \ksort($expected);
        $this->assertSame($expected, $actual);
    }

    #[DataProvider('createHistoryRequestDataDataProvider')]
    public function testCreateHistoryRequestData(array $data, array $expected): void
    {
        $this->cryptMock->expects(self::once())
            ->method('generateRandomString')
            ->willReturn($expected['randomNumber']);

        $actual = $this->mapper->createHistoryRequestData($this->account, $data);

        \ksort($actual);
        \ksort($expected);
        $this->assertSame($expected, $actual);
    }

    public static function createCustomQueryRequestDataDataProvider(): Generator
    {
        yield 'link_creation_request' => [
            'request_data' => [
                'txnCode'     => '1020',
                'order'       => ['orderTrackId' => 'ae15a6c8-467e-45de-b24c-b98821a42667'],
                'transaction' => ['amount' => 1.00, 'currencyCode' => 949, 'motoInd' => 0, 'installCount' => 1],
            ],
            'expected' => [
                'version'      => '1.00',
                'txnCode'      => '1020',
                'randomNumber' => 'rand_134',
                'requestDateTime' => null, // will be unset in test
                'terminal'     => [
                    'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                    'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                ],
                'order'        => ['orderTrackId' => 'ae15a6c8-467e-45de-b24c-b98821a42667'],
                'transaction'  => ['amount' => 1.00, 'currencyCode' => 949, 'motoInd' => 0, 'installCount' => 1],
            ],
        ];

        yield 'with_pre_set_request_date_time' => [
            'request_data' => [
                'txnCode'         => '1020',
                'requestDateTime' => '2024-01-01T12:00:00.000',
            ],
            'expected' => [
                'version'         => '1.00',
                'txnCode'         => '1020',
                'randomNumber'    => 'rand_abc',
                'requestDateTime' => '2024-01-01T12:00:00.000',
                'terminal'        => [
                    'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                    'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                ],
            ],
        ];
    }

    public function testCreateCustomQueryRequestDataWithSubMerchantAccount(): void
    {
        $account = AccountFactory::createAkbankPosAccount(
            'akbank-pos',
            '2023090417500272654BD9A49CF07574',
            '2023090417500284633D137A249DBBEB',
            '3230323330393034313735303032363031353172675f357637355f3273387373745f7233725f73323333383737335f323272383774767276327672323531355f',
            'sub-merchant-123'
        );

        $this->cryptMock->expects(self::once())
            ->method('generateRandomString')
            ->willReturn('rand_sub');

        $actual = $this->mapper->createCustomQueryRequestData($account, ['txnCode' => '1000']);

        $this->assertSame('sub-merchant-123', $actual['subMerchant']['subMerchantId']);
        $this->assertSame('2023090417500272654BD9A49CF07574', $actual['terminal']['merchantSafeId']);
    }

    public static function createHistoryRequestDataDataProvider(): Generator
    {
        yield 'with_batch_number' => [
            'data'     => ['batch_num' => 39],
            'expected' => [
                'terminal'     => [
                    'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                    'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                ],
                'report'       => ['batchNumber' => 39],
                'randomNumber' => '128-character-random-string',
            ],
        ];

        yield 'with_date_range' => [
            'data'     => [
                'start_date' => new DateTimeImmutable('2024-04-13 13:00:00'),
                'end_date'   => new DateTimeImmutable('2024-04-14 13:00:00'),
            ],
            'expected' => [
                'terminal'     => [
                    'merchantSafeId' => '2023090417500272654BD9A49CF07574',
                    'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                ],
                'report'       => [
                    'startDateTime' => '2024-04-13T13:00:00.000',
                    'endDateTime'   => '2024-04-14T13:00:00.000',
                ],
                'randomNumber' => '128-character-random-string',
            ],
        ];
    }
}
