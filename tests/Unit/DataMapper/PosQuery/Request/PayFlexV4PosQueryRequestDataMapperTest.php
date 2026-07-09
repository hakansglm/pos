<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\PosQuery\Request;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\PosQuery\Request\AbstractQueryRequestDataMapper;
use Mews\Pos\DataMapper\PosQuery\Request\PayFlexV4PosQueryRequestDataMapper;
use Mews\Pos\Exception\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\RequestValueFormatterFactory;
use Mews\Pos\Factory\RequestValueMapperFactory;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\PayFlexV4Pos;
use Mews\Pos\Model\Account\PayFlexPosAccount;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(PayFlexV4PosQueryRequestDataMapper::class)]
#[CoversClass(AbstractQueryRequestDataMapper::class)]
class PayFlexV4PosQueryRequestDataMapperTest extends TestCase
{
    private PayFlexPosAccount $account;

    private PayFlexV4PosQueryRequestDataMapper $mapper;

    /** @var CryptInterface & MockObject */
    private MockObject $cryptMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account   = AccountFactory::createPayFlexPosAccount(
            'payflex-mpi-v4',
            '000000000111111',
            '3XTgER89as',
            '3XTgER89as'
        );
        $this->cryptMock = $this->createMock(CryptInterface::class);
        $this->mapper    = new PayFlexV4PosQueryRequestDataMapper(
            RequestValueMapperFactory::createForGateway(PayFlexV4Pos::class),
            RequestValueFormatterFactory::createForGateway(PayFlexV4Pos::class),
            $this->cryptMock
        );
    }

    public function testSupports(): void
    {
        $this->assertTrue(PayFlexV4PosQueryRequestDataMapper::supports(PayFlexV4Pos::class));
        $this->assertFalse(PayFlexV4PosQueryRequestDataMapper::supports(AssecoPos::class));
    }

    public function testCreateCustomQueryRequestData(): void
    {
        $requestData = ['TransactionDate' => '2024-01-01', 'OrderId' => 'ORDER123'];

        $actual = $this->mapper->createCustomQueryRequestData($this->account, $requestData);

        $this->assertSame('000000000111111', $actual['MerchantId']);
        $this->assertSame('3XTgER89as', $actual['Password']);
        $this->assertSame('3XTgER89as', $actual['TerminalNo']);
        $this->assertSame('2024-01-01', $actual['TransactionDate']);
        $this->assertSame('ORDER123', $actual['OrderId']);
    }

    public function testCreateCustomQueryRequestDataDoesNotOverwriteExistingFields(): void
    {
        $requestData = [
            'MerchantId' => 'CUSTOM_MERCHANT',
            'Password'   => 'CUSTOM_PASS',
            'TerminalNo' => 'CUSTOM_TERMINAL',
        ];

        $actual = $this->mapper->createCustomQueryRequestData($this->account, $requestData);

        $this->assertSame('CUSTOM_MERCHANT', $actual['MerchantId']);
        $this->assertSame('CUSTOM_PASS', $actual['Password']);
        $this->assertSame('CUSTOM_TERMINAL', $actual['TerminalNo']);
    }

    public function testCreateHistoryRequestDataThrows(): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);

        $this->mapper->createHistoryRequestData($this->account, []);
    }
}
