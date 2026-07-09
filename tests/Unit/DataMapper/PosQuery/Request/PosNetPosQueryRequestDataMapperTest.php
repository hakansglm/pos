<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\PosQuery\Request;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\PosQuery\Request\AbstractQueryRequestDataMapper;
use Mews\Pos\DataMapper\PosQuery\Request\PosNetPosQueryRequestDataMapper;
use Mews\Pos\Exception\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\RequestValueFormatterFactory;
use Mews\Pos\Factory\RequestValueMapperFactory;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\PosNetPos;
use Mews\Pos\Model\Account\PosNetPosAccount;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(PosNetPosQueryRequestDataMapper::class)]
#[CoversClass(AbstractQueryRequestDataMapper::class)]
class PosNetPosQueryRequestDataMapperTest extends TestCase
{
    private PosNetPosAccount $account;

    private PosNetPosQueryRequestDataMapper $mapper;

    /** @var CryptInterface & MockObject */
    private MockObject $cryptMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account   = AccountFactory::createPosNetPosAccount(
            'yapikredi',
            '6706598320',
            '67005551',
            '27426',
            '10,10,10,10,10,10,10,10'
        );
        $this->cryptMock = $this->createMock(CryptInterface::class);
        $this->mapper    = new PosNetPosQueryRequestDataMapper(
            RequestValueMapperFactory::createForGateway(PosNetPos::class),
            RequestValueFormatterFactory::createForGateway(PosNetPos::class),
            $this->cryptMock
        );
    }

    public function testSupports(): void
    {
        $this->assertTrue(PosNetPosQueryRequestDataMapper::supports(PosNetPos::class));
        $this->assertFalse(PosNetPosQueryRequestDataMapper::supports(AssecoPos::class));
    }

    public function testCreateCustomQueryRequestData(): void
    {
        $actual = $this->mapper->createCustomQueryRequestData($this->account, ['amount' => '1000']);

        $this->assertSame('6706598320', $actual['mid']);
        $this->assertSame('67005551', $actual['tid']);
        $this->assertSame('1000', $actual['amount']);
    }

    public function testCreateCustomQueryRequestDataDoesNotOverwriteExistingFields(): void
    {
        $actual = $this->mapper->createCustomQueryRequestData(
            $this->account,
            ['mid' => 'CUSTOMMID', 'tid' => 'CUSTOMTID']
        );

        $this->assertSame('CUSTOMMID', $actual['mid']);
        $this->assertSame('CUSTOMTID', $actual['tid']);
    }

    public function testCreateHistoryRequestDataThrows(): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);

        $this->mapper->createHistoryRequestData($this->account, []);
    }
}
