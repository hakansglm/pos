<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\PosQuery\Request;

use DateTimeImmutable;
use Generator;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\PosQuery\Request\AbstractQueryRequestDataMapper;
use Mews\Pos\DataMapper\PosQuery\Request\PayTrPosQueryRequestDataMapper;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\RequestValueFormatterFactory;
use Mews\Pos\Factory\RequestValueMapperFactory;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\PayTrPos;
use Mews\Pos\Model\Account\PayTrPosAccount;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(PayTrPosQueryRequestDataMapper::class)]
#[CoversClass(AbstractQueryRequestDataMapper::class)]
class PayTrPosQueryRequestDataMapperTest extends TestCase
{
    private PayTrPosAccount $account;

    private PayTrPosQueryRequestDataMapper $mapper;

    /** @var CryptInterface & MockObject */
    private MockObject $cryptMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createPayTrPosAccount('paytr', '123456', 'wWwU8buJp6jo1r25', 'YEUaNcdHXqyt7hjt');

        $this->cryptMock = $this->createMock(CryptInterface::class);
        $this->mapper    = new PayTrPosQueryRequestDataMapper(
            RequestValueMapperFactory::createForGateway(PayTrPos::class),
            RequestValueFormatterFactory::createForGateway(PayTrPos::class),
            $this->cryptMock
        );
    }

    public function testSupports(): void
    {
        $this->assertTrue(PayTrPosQueryRequestDataMapper::supports(PayTrPos::class));
        $this->assertFalse(PayTrPosQueryRequestDataMapper::supports(AssecoPos::class));
    }

    public function testIsTestMode(): void
    {
        $this->assertFalse($this->mapper->isTestMode());
        $this->mapper->setTestMode(true);
        $this->assertTrue($this->mapper->isTestMode());
    }

    public function testCreateCustomQueryRequestDataWithoutToken(): void
    {
        $input              = ['custom_field' => 'value'];
        $expectedBeforeHash = ['custom_field' => 'value', 'merchant_id' => '123456'];

        $this->cryptMock->expects(self::once())
            ->method('createHash')
            ->with($this->account, $expectedBeforeHash)
            ->willReturn('computed-token');

        $actual = $this->mapper->createCustomQueryRequestData($this->account, $input);

        $this->assertSame(\array_merge($expectedBeforeHash, ['paytr_token' => 'computed-token']), $actual);
    }

    public function testCreateCustomQueryRequestDataWithExistingToken(): void
    {
        $input = ['merchant_id' => 'override', 'paytr_token' => 'existing-token'];

        $this->cryptMock->expects(self::never())->method('createHash');

        $actual = $this->mapper->createCustomQueryRequestData($this->account, $input);

        $this->assertSame($input, $actual);
    }

    #[DataProvider('createHistoryRequestDataDataProvider')]
    public function testCreateHistoryRequestData(bool $testMode, array $data, array $expectedWithoutToken): void
    {
        $this->mapper->setTestMode($testMode);

        $this->cryptMock->expects(self::once())
            ->method('createHash')
            ->with($this->account, $expectedWithoutToken)
            ->willReturn('mock-token');

        $actual = $this->mapper->createHistoryRequestData($this->account, $data);

        $this->assertSame(\array_merge($expectedWithoutToken, ['paytr_token' => 'mock-token']), $actual);
    }

    public function testCreateInstallmentRatesRequestData(): void
    {
        $expectedWithoutToken = [
            'merchant_id' => '123456',
            'request_id'  => 'RAND24',
        ];

        $this->cryptMock->expects(self::once())
            ->method('generateRandomString')
            ->willReturn('RAND24');

        $this->cryptMock->expects(self::once())
            ->method('createHash')
            ->with($this->account, $expectedWithoutToken)
            ->willReturn('mock-token');

        $actual = $this->mapper->createInstallmentRatesRequestData($this->account, []);

        $this->assertSame(\array_merge($expectedWithoutToken, ['paytr_token' => 'mock-token']), $actual);
    }

    public function testCreateBinListRequestData(): void
    {
        $params = ['bin' => '415956'];

        $expectedWithoutToken = [
            'merchant_id' => '123456',
            'bin_number'  => '415956',
        ];

        $this->cryptMock->expects(self::once())
            ->method('createHash')
            ->with($this->account, $expectedWithoutToken)
            ->willReturn('mock-bin-token');

        $actual = $this->mapper->createBinListRequestData($this->account, $params);

        $this->assertSame(\array_merge($expectedWithoutToken, ['paytr_token' => 'mock-bin-token']), $actual);
    }

    public static function createHistoryRequestDataDataProvider(): Generator
    {
        $startDate = new DateTimeImmutable('2026-06-01 00:00:00');
        $endDate   = new DateTimeImmutable('2026-06-03 23:59:59');

        yield 'production_mode' => [
            'testMode'             => false,
            'data'                 => ['start_date' => $startDate, 'end_date' => $endDate],
            'expectedWithoutToken' => [
                'merchant_id' => '123456',
                'start_date'  => '2026-06-01 00:00:00',
                'end_date'    => '2026-06-03 23:59:59',
            ],
        ];

        yield 'test_mode' => [
            'testMode'             => true,
            'data'                 => ['start_date' => $startDate, 'end_date' => $endDate],
            'expectedWithoutToken' => [
                'merchant_id' => '123456',
                'start_date'  => '2026-06-01 00:00:00',
                'end_date'    => '2026-06-03 23:59:59',
                'dummy'       => 1,
            ],
        ];
    }
}
