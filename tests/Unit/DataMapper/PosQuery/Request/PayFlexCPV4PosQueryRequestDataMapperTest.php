<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\PosQuery\Request;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\PosQuery\Request\AbstractQueryRequestDataMapper;
use Mews\Pos\DataMapper\PosQuery\Request\PayFlexCPV4PosQueryRequestDataMapper;
use Mews\Pos\Exception\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\RequestValueFormatterFactory;
use Mews\Pos\Factory\RequestValueMapperFactory;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\PayFlexCPV4Pos;
use Mews\Pos\Model\Account\PayFlexPosAccount;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(PayFlexCPV4PosQueryRequestDataMapper::class)]
#[CoversClass(AbstractQueryRequestDataMapper::class)]
class PayFlexCPV4PosQueryRequestDataMapperTest extends TestCase
{
    private PayFlexPosAccount $account;

    private PayFlexCPV4PosQueryRequestDataMapper $mapper;

    /** @var CryptInterface & MockObject */
    private MockObject $cryptMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account   = AccountFactory::createPayFlexPosAccount('vakifbank-cp', '000000000111111', '3XTgER89as', 'VP999999');
        $this->cryptMock = $this->createMock(CryptInterface::class);
        $this->mapper    = new PayFlexCPV4PosQueryRequestDataMapper(
            RequestValueMapperFactory::createForGateway(PayFlexCPV4Pos::class),
            RequestValueFormatterFactory::createForGateway(PayFlexCPV4Pos::class),
            $this->cryptMock
        );
    }

    public function testSupports(): void
    {
        $this->assertTrue(PayFlexCPV4PosQueryRequestDataMapper::supports(PayFlexCPV4Pos::class));
        $this->assertFalse(PayFlexCPV4PosQueryRequestDataMapper::supports(AssecoPos::class));
    }

    public function testCreateCustomQueryRequestData(): void
    {
        $actual = $this->mapper->createCustomQueryRequestData($this->account, ['OrderId' => 'ORDER123']);

        $this->assertSame('000000000111111', $actual['HostMerchantId']);
        $this->assertSame('3XTgER89as', $actual['Password']);
        $this->assertSame('ORDER123', $actual['OrderId']);
    }

    public function testCreateCustomQueryRequestDataDoesNotOverwriteExistingFields(): void
    {
        $actual = $this->mapper->createCustomQueryRequestData(
            $this->account,
            ['HostMerchantId' => 'CUSTOM', 'Password' => 'CUSTOMPASS']
        );

        $this->assertSame('CUSTOM', $actual['HostMerchantId']);
        $this->assertSame('CUSTOMPASS', $actual['Password']);
    }

    public function testCreateHistoryRequestDataThrows(): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);

        $this->mapper->createHistoryRequestData($this->account, []);
    }
}
