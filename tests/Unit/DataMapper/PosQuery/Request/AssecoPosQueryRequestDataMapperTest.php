<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\PosQuery\Request;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\PosQuery\Request\AbstractQueryRequestDataMapper;
use Mews\Pos\DataMapper\PosQuery\Request\AssecoPosQueryRequestDataMapper;
use Mews\Pos\Exception\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\RequestValueFormatterFactory;
use Mews\Pos\Factory\RequestValueMapperFactory;
use Mews\Pos\Gateway\AkbankPos;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Model\Account\AssecoPosAccount;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(AssecoPosQueryRequestDataMapper::class)]
#[CoversClass(AbstractQueryRequestDataMapper::class)]
class AssecoPosQueryRequestDataMapperTest extends TestCase
{
    private AssecoPosAccount $account;

    private AssecoPosQueryRequestDataMapper $mapper;

    /** @var CryptInterface & MockObject */
    private MockObject $cryptMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createAssecoPosAccount(
            'payten_v3_hash',
            '190100000',
            'ZIRAATAPI',
            'ZIRAAT19',
            '123456'
        );

        $this->cryptMock = $this->createMock(CryptInterface::class);
        $this->mapper    = new AssecoPosQueryRequestDataMapper(
            RequestValueMapperFactory::createForGateway(AssecoPos::class),
            RequestValueFormatterFactory::createForGateway(AssecoPos::class),
            $this->cryptMock
        );
    }

    public function testSupports(): void
    {
        $this->assertTrue(AssecoPosQueryRequestDataMapper::supports(AssecoPos::class));
        $this->assertFalse(AssecoPosQueryRequestDataMapper::supports(AkbankPos::class));
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
        $actual = $this->mapper->createCustomQueryRequestData($this->account, $requestData);

        \ksort($actual);
        \ksort($expected);
        $this->assertSame($expected, $actual);
    }

    public function testCreateHistoryRequestDataThrows(): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);

        $this->mapper->createHistoryRequestData($this->account, []);
    }

    public function testCreateInstallmentRatesRequestDataThrows(): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);

        $this->mapper->createInstallmentRatesRequestData($this->account, []);
    }

    public function testCreateInstallmentPricesRequestDataThrows(): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);

        $this->mapper->createInstallmentPricesRequestData($this->account, []);
    }

    public function testCreateBinListRequestDataThrows(): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);

        $this->mapper->createBinListRequestData($this->account, []);
    }

    public static function createCustomQueryRequestDataDataProvider(): \Generator
    {
        yield 'without_account_data' => [
            'request_data' => [
                'Type'     => 'Query',
                'Number'   => '4111111111111111',
                'Expires'  => '10.2025',
                'Extra'    => ['IMECECARDQUERY' => null],
            ],
            'expected' => [
                'Name'     => 'ZIRAATAPI',
                'Password' => 'ZIRAAT19',
                'ClientId' => '190100000',
                'Type'     => 'Query',
                'Number'   => '4111111111111111',
                'Expires'  => '10.2025',
                'Extra'    => ['IMECECARDQUERY' => null],
            ],
        ];

        yield 'with_account_data_already_set' => [
            'request_data' => [
                'Name'     => 'CUSTOMNAME',
                'Password' => 'CUSTOMPASS',
                'ClientId' => 'CUSTOMCLIENT',
                'Type'     => 'Query',
                'Number'   => '4111111111111111',
                'Expires'  => '10.2025',
                'Extra'    => ['IMECECARDQUERY' => null],
            ],
            'expected' => [
                'Name'     => 'CUSTOMNAME',
                'Password' => 'CUSTOMPASS',
                'ClientId' => 'CUSTOMCLIENT',
                'Type'     => 'Query',
                'Number'   => '4111111111111111',
                'Expires'  => '10.2025',
                'Extra'    => ['IMECECARDQUERY' => null],
            ],
        ];
    }
}
