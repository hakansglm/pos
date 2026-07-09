<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\PosQuery\Request;

use DateTimeImmutable;
use Generator;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\PosQuery\Request\AbstractQueryRequestDataMapper;
use Mews\Pos\DataMapper\PosQuery\Request\IyzicoPosQueryRequestDataMapper;
use Mews\Pos\Exception\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\RequestValueFormatterFactory;
use Mews\Pos\Factory\RequestValueMapperFactory;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\IyzicoPos;
use Mews\Pos\Model\Account\IyzicoPosAccount;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(IyzicoPosQueryRequestDataMapper::class)]
#[CoversClass(AbstractQueryRequestDataMapper::class)]
class IyzicoPosQueryRequestDataMapperTest extends TestCase
{
    private IyzicoPosAccount $account;

    private IyzicoPosQueryRequestDataMapper $mapper;

    /** @var CryptInterface & MockObject */
    private MockObject $cryptMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createIyzicoPosAccount('iyzico', 'sandbox-apiKey', 'sandbox-secretKey');

        $this->cryptMock = $this->createMock(CryptInterface::class);
        $this->mapper    = new IyzicoPosQueryRequestDataMapper(
            RequestValueMapperFactory::createForGateway(IyzicoPos::class),
            RequestValueFormatterFactory::createForGateway(IyzicoPos::class),
            $this->cryptMock,
            PosInterface::LANG_TR
        );
    }

    public function testSupports(): void
    {
        $this->assertTrue(IyzicoPosQueryRequestDataMapper::supports(IyzicoPos::class));
        $this->assertFalse(IyzicoPosQueryRequestDataMapper::supports(AssecoPos::class));
    }

    public function testIsTestMode(): void
    {
        $this->assertFalse($this->mapper->isTestMode());
        $this->mapper->setTestMode(true);
        $this->assertTrue($this->mapper->isTestMode());
    }

    public function testCreateCustomQueryRequestDataIsPassthrough(): void
    {
        $requestData = ['locale' => 'tr', 'conversationId' => 'abc123'];
        $this->assertSame($requestData, $this->mapper->createCustomQueryRequestData($this->account, $requestData));
    }

    #[DataProvider('createHistoryRequestDataDataProvider')]
    public function testCreateHistoryRequestData(array $data, array $expected): void
    {
        $actual = $this->mapper->createHistoryRequestData($this->account, $data);

        \ksort($actual);
        \ksort($expected);
        $this->assertSame($expected, $actual);
    }

    public static function createHistoryRequestDataDataProvider(): Generator
    {
        yield 'default_lang_page_1' => [
            'data'     => [
                'transaction_date' => new DateTimeImmutable('2022-05-18'),
            ],
            'expected' => [
                'locale'          => 'tr',
                'transactionDate' => '2022-05-18',
                'page'            => 1,
            ],
        ];

        yield 'explicit_lang_en_page_2' => [
            'data'     => [
                'transaction_date' => new DateTimeImmutable('2022-05-18'),
                'lang'             => PosInterface::LANG_EN,
                'page'             => 2,
            ],
            'expected' => [
                'locale'          => 'en',
                'transactionDate' => '2022-05-18',
                'page'            => 2,
            ],
        ];
    }

    public function testCreateInstallmentRatesRequestDataThrows(): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);
        $this->mapper->createInstallmentRatesRequestData($this->account, []);
    }

    #[DataProvider('createBinListRequestDataDataProvider')]
    public function testCreateBinListRequestData(array $params, array $expected): void
    {
        $this->cryptMock->expects(self::once())
            ->method('generateRandomString')
            ->willReturn('rand-conv-id');

        $actual = $this->mapper->createBinListRequestData($this->account, $params);

        \ksort($actual);
        \ksort($expected);
        $this->assertSame($expected, $actual);
    }

    public static function createBinListRequestDataDataProvider(): Generator
    {
        yield 'with_bin' => [
            'params'   => ['bin' => '415956'],
            'expected' => [
                'locale'         => 'tr',
                'conversationId' => 'rand-conv-id',
                'binNumber'      => '415956',
            ],
        ];

        yield 'with_bin_and_explicit_lang' => [
            'params'   => ['bin' => '415956', 'lang' => PosInterface::LANG_EN],
            'expected' => [
                'locale'         => 'en',
                'conversationId' => 'rand-conv-id',
                'binNumber'      => '415956',
            ],
        ];
    }

    #[DataProvider('createInstallmentPricesRequestDataDataProvider')]
    public function testCreateInstallmentPricesRequestData(array $params, array $expected): void
    {
        $this->cryptMock->expects(self::once())
            ->method('generateRandomString')
            ->willReturn('rand-conv-id');

        $actual = $this->mapper->createInstallmentPricesRequestData($this->account, $params);

        \ksort($actual);
        \ksort($expected);
        $this->assertSame($expected, $actual);
    }

    public static function createInstallmentPricesRequestDataDataProvider(): Generator
    {
        yield 'without_bin' => [
            'params'   => ['amount' => 100.0],
            'expected' => [
                'locale'         => 'tr',
                'conversationId' => 'rand-conv-id',
                'price'          => 100.0,
            ],
        ];

        yield 'with_bin' => [
            'params'   => ['amount' => 100.0, 'bin' => '54308100'],
            'expected' => [
                'locale'         => 'tr',
                'conversationId' => 'rand-conv-id',
                'price'          => 100.0,
                'binNumber'      => '54308100',
            ],
        ];

        yield 'with_explicit_lang' => [
            'params'   => ['amount' => 250.0, 'lang' => PosInterface::LANG_EN],
            'expected' => [
                'locale'         => 'en',
                'conversationId' => 'rand-conv-id',
                'price'          => 250.0,
            ],
        ];
    }
}
