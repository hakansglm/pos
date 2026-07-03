<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Factory;

use DomainException;
use Mews\Pos\Exception\GatewayClassNotConfiguredException;
use Mews\Pos\Exception\GatewayConfigNotFoundException;
use Mews\Pos\Factory\PosQueryFactory;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\ToslaPos;
use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\PosQuery\AssecoPosQuery;
use Mews\Pos\PosQuery\PosQueryInterface;
use Mews\Pos\PosQuery\ToslaPosQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[CoversClass(PosQueryFactory::class)]
class PosQueryFactoryTest extends TestCase
{
    private array $baseConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->baseConfig = [
            'banks' => [
                'akbank' => [
                    'name'              => 'Akbank',
                    'class'             => AssecoPos::class,
                    'gateway_endpoints' => [
                        'payment_api' => 'https://entegrasyon.asseco-see.com.tr/fim/api',
                        'gateway_3d'  => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                    ],
                ],
                'tosla' => [
                    'name'              => 'Tosla',
                    'class'             => ToslaPos::class,
                    'gateway_endpoints' => [
                        'payment_api' => 'https://prepentegrasyon.tosla.com/api/Payment',
                        'query_api'   => 'https://prepentegrasyon.tosla.com/api/Payment',
                    ],
                ],
            ],
        ];
    }

    public function testCreateReturnsCorrectPosQueryInstance(): void
    {
        $account = $this->createMock(AbstractPosAccount::class);
        $account->method('getBankName')->willReturn('akbank');

        $posQuery = PosQueryFactory::create(
            $account,
            $this->baseConfig,
            $this->createMock(EventDispatcherInterface::class)
        );

        $this->assertInstanceOf(PosQueryInterface::class, $posQuery);
        $this->assertInstanceOf(AssecoPosQuery::class, $posQuery);
    }

    public function testCreateReturnsCorrectInstanceForTosla(): void
    {
        $account = $this->createMock(AbstractPosAccount::class);
        $account->method('getBankName')->willReturn('tosla');

        $posQuery = PosQueryFactory::create(
            $account,
            $this->baseConfig,
            $this->createMock(EventDispatcherInterface::class)
        );

        $this->assertInstanceOf(ToslaPosQuery::class, $posQuery);
    }

    public function testCreateThrowsWhenBankNotInConfig(): void
    {
        $account = $this->createMock(AbstractPosAccount::class);
        $account->method('getBankName')->willReturn('nonexistent');

        $this->expectException(GatewayConfigNotFoundException::class);

        PosQueryFactory::create(
            $account,
            $this->baseConfig,
            $this->createMock(EventDispatcherInterface::class)
        );
    }

    public function testCreateThrowsWhenGatewayClassMissingFromConfig(): void
    {
        $config = [
            'banks' => [
                'akbank' => [
                    'name'              => 'Akbank',
                    'gateway_endpoints' => [
                        'payment_api' => 'https://entegrasyon.asseco-see.com.tr/fim/api',
                    ],
                ],
            ],
        ];

        $account = $this->createMock(AbstractPosAccount::class);
        $account->method('getBankName')->willReturn('akbank');

        $this->expectException(GatewayClassNotConfiguredException::class);

        PosQueryFactory::create(
            $account,
            $config,
            $this->createMock(EventDispatcherInterface::class)
        );
    }

    public function testCreateThrowsWhenNoPosQueryClassForGateway(): void
    {
        $config = [
            'banks' => [
                'unknown' => [
                    'name'              => 'Unknown',
                    'class'             => \stdClass::class,
                    'gateway_endpoints' => [
                        'payment_api' => 'https://example.com',
                    ],
                ],
            ],
        ];

        $account = $this->createMock(AbstractPosAccount::class);
        $account->method('getBankName')->willReturn('unknown');

        $this->expectException(DomainException::class);

        PosQueryFactory::create(
            $account,
            $config,
            $this->createMock(EventDispatcherInterface::class)
        );
    }
}
