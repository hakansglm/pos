<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\PosQuery\Request;

use DateTime;
use Generator;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\PosQuery\Request\AbstractQueryRequestDataMapper;
use Mews\Pos\DataMapper\PosQuery\Request\VakifKatilimPosQueryRequestDataMapper;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\RequestValueFormatterFactory;
use Mews\Pos\Factory\RequestValueMapperFactory;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\VakifKatilimPos;
use Mews\Pos\Model\Account\BoaPosAccount;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(VakifKatilimPosQueryRequestDataMapper::class)]
#[CoversClass(AbstractQueryRequestDataMapper::class)]
class VakifKatilimPosQueryRequestDataMapperTest extends TestCase
{
    private BoaPosAccount $account;

    private VakifKatilimPosQueryRequestDataMapper $mapper;

    /** @var CryptInterface & MockObject */
    private MockObject $cryptMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createBoaPosAccount('vakif-katilim', '1', 'APIUSER', '11111', 'kdsnsksl');

        $this->cryptMock = $this->createMock(CryptInterface::class);
        $this->mapper    = new VakifKatilimPosQueryRequestDataMapper(
            RequestValueMapperFactory::createForGateway(VakifKatilimPos::class),
            RequestValueFormatterFactory::createForGateway(VakifKatilimPos::class),
            $this->cryptMock
        );
    }

    public function testSupports(): void
    {
        $this->assertTrue(VakifKatilimPosQueryRequestDataMapper::supports(VakifKatilimPos::class));
        $this->assertFalse(VakifKatilimPosQueryRequestDataMapper::supports(AssecoPos::class));
    }

    public function testIsTestMode(): void
    {
        $this->assertFalse($this->mapper->isTestMode());
        $this->mapper->setTestMode(true);
        $this->assertTrue($this->mapper->isTestMode());
    }

    public function testCreateCustomQueryRequestDataIsPassthrough(): void
    {
        $requestData = ['SomeField' => 'value'];
        $this->assertSame($requestData, $this->mapper->createCustomQueryRequestData($this->account, $requestData));
    }

    #[DataProvider('createHistoryRequestDataDataProvider')]
    public function testCreateHistoryRequestData(array $data, array $expected): void
    {
        $hashInput = $expected;
        unset($hashInput['HashData']);

        $this->cryptMock->expects(self::once())
            ->method('createHash')
            ->with($this->account, $hashInput)
            ->willReturn($expected['HashData']);

        $actual = $this->mapper->createHistoryRequestData($this->account, $data);

        $this->assertSame($expected, $actual);
    }

    public static function createHistoryRequestDataDataProvider(): Generator
    {
        yield 'default_page_and_size' => [
            'data'     => [
                'start_date' => new DateTime('2024-03-30'),
                'end_date'   => new DateTime('2024-03-31'),
                'page'       => 1,
                'page_size'  => 10,
            ],
            'expected' => [
                'MerchantId'    => '1',
                'CustomerId'    => '11111',
                'UserName'      => 'APIUSER',
                'SubMerchantId' => '0',
                'StartDate'     => '2024-03-30',
                'EndDate'       => '2024-03-31',
                'LowerLimit'    => 0,
                'UpperLimit'    => 10,
                'ProvNumber'    => null,
                'OrderStatus'   => null,
                'TranResult'    => null,
                'OrderNo'       => null,
                'HashData'      => 'mocked_hash',
            ],
        ];

        yield 'page_2' => [
            'data'     => [
                'start_date' => new DateTime('2024-03-30'),
                'end_date'   => new DateTime('2024-03-31'),
                'page'       => 2,
                'page_size'  => 5,
            ],
            'expected' => [
                'MerchantId'    => '1',
                'CustomerId'    => '11111',
                'UserName'      => 'APIUSER',
                'SubMerchantId' => '0',
                'StartDate'     => '2024-03-30',
                'EndDate'       => '2024-03-31',
                'LowerLimit'    => 5,
                'UpperLimit'    => 5,
                'ProvNumber'    => null,
                'OrderStatus'   => null,
                'TranResult'    => null,
                'OrderNo'       => null,
                'HashData'      => 'mocked_hash',
            ],
        ];
    }
}
