<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\PosQuery\Request;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\PosQuery\Request\AbstractQueryRequestDataMapper;
use Mews\Pos\DataMapper\PosQuery\Request\PosNetV1PosQueryRequestDataMapper;
use Mews\Pos\Exception\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\RequestValueFormatterFactory;
use Mews\Pos\Factory\RequestValueMapperFactory;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\PosNetV1Pos;
use Mews\Pos\Model\Account\PosNetPosAccount;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(PosNetV1PosQueryRequestDataMapper::class)]
#[CoversClass(AbstractQueryRequestDataMapper::class)]
class PosNetV1PosQueryRequestDataMapperTest extends TestCase
{
    private PosNetPosAccount $account;

    private PosNetV1PosQueryRequestDataMapper $mapper;

    /** @var CryptInterface & MockObject */
    private MockObject $cryptMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account   = AccountFactory::createPosNetPosAccount(
            'albaraka',
            '6700950031',
            '67540050',
            '1010028724242434',
            '10,10,10,10,10,10,10,10'
        );
        $this->cryptMock = $this->createMock(CryptInterface::class);
        $this->mapper    = new PosNetV1PosQueryRequestDataMapper(
            RequestValueMapperFactory::createForGateway(PosNetV1Pos::class),
            RequestValueFormatterFactory::createForGateway(PosNetV1Pos::class),
            $this->cryptMock
        );
    }

    public function testSupports(): void
    {
        $this->assertTrue(PosNetV1PosQueryRequestDataMapper::supports(PosNetV1Pos::class));
        $this->assertFalse(PosNetV1PosQueryRequestDataMapper::supports(AssecoPos::class));
    }

    public function testCreateCustomQueryRequestDataWithMACParams(): void
    {
        $this->cryptMock->expects(self::once())
            ->method('hashFromParams')
            ->willReturn('generated_mac');

        $requestData = [
            'TranType'  => 'Sale',
            'MACParams' => 'MerchantNo:TerminalNo:TranType',
        ];

        $actual = $this->mapper->createCustomQueryRequestData($this->account, $requestData);

        $this->assertSame('JSON', $actual['ApiType']);
        $this->assertSame('V100', $actual['ApiVersion']);
        $this->assertSame('6700950031', $actual['MerchantNo']);
        $this->assertSame('67540050', $actual['TerminalNo']);
        $this->assertSame('generated_mac', $actual['MAC']);
        $this->assertSame('Sale', $actual['TranType']);
    }

    public function testCreateCustomQueryRequestDataWithPresetMAC(): void
    {
        $this->cryptMock->expects(self::never())->method('hashFromParams');

        $requestData = [
            'MACParams' => 'MerchantNo:TerminalNo',
            'MAC'       => 'pre_set_mac',
        ];

        $actual = $this->mapper->createCustomQueryRequestData($this->account, $requestData);

        $this->assertSame('pre_set_mac', $actual['MAC']);
    }

    public function testCreateHistoryRequestDataThrows(): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);

        $this->mapper->createHistoryRequestData($this->account, []);
    }
}
