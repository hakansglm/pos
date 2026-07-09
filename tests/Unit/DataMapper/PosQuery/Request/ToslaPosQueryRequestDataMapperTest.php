<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\PosQuery\Request;

use DateTimeImmutable;
use Generator;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\PosQuery\Request\AbstractQueryRequestDataMapper;
use Mews\Pos\DataMapper\PosQuery\Request\ToslaPosQueryRequestDataMapper;
use Mews\Pos\Exception\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\RequestValueFormatterFactory;
use Mews\Pos\Factory\RequestValueMapperFactory;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\ToslaPos;
use Mews\Pos\Model\Account\ToslaPosAccount;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(ToslaPosQueryRequestDataMapper::class)]
#[CoversClass(AbstractQueryRequestDataMapper::class)]
class ToslaPosQueryRequestDataMapperTest extends TestCase
{
    private ToslaPosAccount $account;

    private ToslaPosQueryRequestDataMapper $mapper;

    /** @var CryptInterface & MockObject */
    private MockObject $cryptMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createToslaPosAccount(
            'tosla',
            '1000000494',
            'POS_ENT_Test_001',
            'POS_ENT_Test_001!*!*'
        );

        $this->cryptMock = $this->createMock(CryptInterface::class);
        $this->mapper    = new ToslaPosQueryRequestDataMapper(
            RequestValueMapperFactory::createForGateway(ToslaPos::class),
            RequestValueFormatterFactory::createForGateway(ToslaPos::class),
            $this->cryptMock
        );
    }

    public function testSupports(): void
    {
        $this->assertTrue(ToslaPosQueryRequestDataMapper::supports(ToslaPos::class));
        $this->assertFalse(ToslaPosQueryRequestDataMapper::supports(AssecoPos::class));
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
        if (!isset($requestData['rnd'])) {
            $this->cryptMock->expects(self::once())
                ->method('generateRandomString')
                ->willReturn($expected['rnd']);
        }

        if (!isset($requestData['hash'])) {
            $this->cryptMock->expects(self::once())
                ->method('createHash')
                ->willReturn($expected['hash']);
        }

        $actual = $this->mapper->createCustomQueryRequestData($this->account, $requestData);

        $this->assertSame(14, \strlen((string) $actual['timeSpan']));
        unset($actual['timeSpan'], $expected['timeSpan']);

        \ksort($actual);
        \ksort($expected);
        $this->assertSame($expected, $actual);
    }

    public function testCreateHistoryRequestDataThrows(): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);

        $this->mapper->createHistoryRequestData($this->account, []);
    }

    public function testCreateInstallmentRatesRequestData(): void
    {
        $this->cryptMock->expects(self::once())
            ->method('generateRandomString')
            ->willReturn('generated_rnd');

        $this->cryptMock->expects(self::once())
            ->method('createHash')
            ->willReturn('generated_hash');

        $actual = $this->mapper->createInstallmentRatesRequestData($this->account, ['bin' => '415956']);

        $this->assertSame(14, \strlen((string) $actual['timeSpan']));
        $this->assertSame('1000000494', $actual['clientId']);
        $this->assertSame('POS_ENT_Test_001', $actual['apiUser']);
        $this->assertSame('generated_rnd', $actual['rnd']);
        $this->assertSame('415956', $actual['bin']);
        $this->assertSame('generated_hash', $actual['hash']);
    }

    public function testCreateInstallmentPricesRequestData(): void
    {
        $this->cryptMock->expects(self::once())
            ->method('generateRandomString')
            ->willReturn('generated_rnd');

        $this->cryptMock->expects(self::once())
            ->method('createHash')
            ->willReturn('generated_hash');

        $actual = $this->mapper->createInstallmentPricesRequestData($this->account, ['amount' => 500.0]);

        $this->assertSame(14, \strlen((string) $actual['timeSpan']));
        $this->assertSame('1000000494', $actual['clientId']);
        $this->assertSame('POS_ENT_Test_001', $actual['apiUser']);
        $this->assertSame('generated_rnd', $actual['rnd']);
        $this->assertSame(500.0, $actual['amount']);
        $this->assertSame(1, $actual['isCommission']);
        $this->assertSame('generated_hash', $actual['hash']);
    }

    public static function createCustomQueryRequestDataDataProvider(): Generator
    {
        yield 'without_account_data' => [
            'request_data' => [
                'bin' => 415956,
            ],
            'expected' => [
                'apiUser'  => 'POS_ENT_Test_001',
                'bin'      => 415956,
                'clientId' => '1000000494',
                'hash'     => 'generated_hash',
                'rnd'      => 'generated_rnd',
                'timeSpan' => new DateTimeImmutable(),
            ],
        ];

        yield 'with_all_fields_pre_filled' => [
            'request_data' => [
                'apiUser'  => 'CUSTOMUSER',
                'bin'      => 415956,
                'clientId' => 'CUSTOMCLIENT',
                'hash'     => 'pre_filled_hash',
                'rnd'      => 'pre_filled_rnd',
                'timeSpan' => '20241103144302',
            ],
            'expected' => [
                'apiUser'  => 'CUSTOMUSER',
                'bin'      => 415956,
                'clientId' => 'CUSTOMCLIENT',
                'hash'     => 'pre_filled_hash',
                'rnd'      => 'pre_filled_rnd',
                'timeSpan' => '20241103144302',
            ],
        ];
    }
}
