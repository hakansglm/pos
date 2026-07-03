<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\PosQuery\Request;

use Generator;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\PosQuery\Request\AbstractQueryRequestDataMapper;
use Mews\Pos\DataMapper\PosQuery\Request\InterPosQueryRequestDataMapper;
use Mews\Pos\Exception\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\RequestValueFormatterFactory;
use Mews\Pos\Factory\RequestValueMapperFactory;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\InterPos;
use Mews\Pos\Model\Account\InterPosAccount;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(InterPosQueryRequestDataMapper::class)]
#[CoversClass(AbstractQueryRequestDataMapper::class)]
class InterPosQueryRequestDataMapperTest extends TestCase
{
    private InterPosAccount $account;

    private InterPosQueryRequestDataMapper $mapper;

    /** @var CryptInterface & MockObject */
    private MockObject $cryptMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createInterPosAccount(
            'denizbank',
            '3123',
            'InterTestApi',
            '3',
            'gDg1N'
        );

        $this->cryptMock = $this->createMock(CryptInterface::class);
        $this->mapper    = new InterPosQueryRequestDataMapper(
            RequestValueMapperFactory::createForGateway(InterPos::class),
            RequestValueFormatterFactory::createForGateway(InterPos::class),
            $this->cryptMock
        );
    }

    public function testSupports(): void
    {
        $this->assertTrue(InterPosQueryRequestDataMapper::supports(InterPos::class));
        $this->assertFalse(InterPosQueryRequestDataMapper::supports(AssecoPos::class));
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

    public static function createCustomQueryRequestDataDataProvider(): Generator
    {
        yield 'without_account_data' => [
            'request_data' => [
                'PurchAmount' => '100',
                'Currency'    => '949',
            ],
            'expected' => [
                'UserCode'    => 'InterTestApi',
                'UserPass'    => '3',
                'ShopCode'    => '3123',
                'PurchAmount' => '100',
                'Currency'    => '949',
            ],
        ];

        yield 'with_account_data_already_set' => [
            'request_data' => [
                'UserCode'    => 'CUSTOM',
                'UserPass'    => 'CUSTOMPASS',
                'ShopCode'    => 'CUSTOMSHOP',
                'PurchAmount' => '100',
                'Currency'    => '949',
            ],
            'expected' => [
                'UserCode'    => 'CUSTOM',
                'UserPass'    => 'CUSTOMPASS',
                'ShopCode'    => 'CUSTOMSHOP',
                'PurchAmount' => '100',
                'Currency'    => '949',
            ],
        ];
    }
}
