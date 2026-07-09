<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\PosQuery\Request;

use DateTime;
use Generator;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\PosQuery\Request\AbstractQueryRequestDataMapper;
use Mews\Pos\DataMapper\PosQuery\Request\GarantiPosQueryRequestDataMapper;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\RequestValueFormatterFactory;
use Mews\Pos\Factory\RequestValueMapperFactory;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\GarantiPos;
use Mews\Pos\Model\Account\GarantiPosAccount;
use Mews\Pos\Model\Card\CreditCardInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(GarantiPosQueryRequestDataMapper::class)]
#[CoversClass(AbstractQueryRequestDataMapper::class)]
class GarantiPosQueryRequestDataMapperTest extends TestCase
{
    private GarantiPosAccount $account;

    private GarantiPosQueryRequestDataMapper $mapper;

    /** @var CryptInterface & MockObject */
    private MockObject $cryptMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createGarantiPosAccount(
            'garanti',
            '7000679',
            'PROVAUT',
            '123qweASD/',
            '30691298',
            '12345678',
            'PROVRFN',
            '123qweASD/'
        );

        $this->cryptMock = $this->createMock(CryptInterface::class);
        $this->mapper    = new GarantiPosQueryRequestDataMapper(
            RequestValueMapperFactory::createForGateway(GarantiPos::class),
            RequestValueFormatterFactory::createForGateway(GarantiPos::class),
            $this->cryptMock
        );
        $this->mapper->setTestMode(true);
    }

    public function testSupports(): void
    {
        $this->assertTrue(GarantiPosQueryRequestDataMapper::supports(GarantiPos::class));
        $this->assertFalse(GarantiPosQueryRequestDataMapper::supports(AssecoPos::class));
    }

    public function testIsTestMode(): void
    {
        $this->assertTrue($this->mapper->isTestMode());
        $this->mapper->setTestMode(false);
        $this->assertFalse($this->mapper->isTestMode());
    }

    #[DataProvider('createCustomQueryRequestDataDataProvider')]
    public function testCreateCustomQueryRequestData(array $requestData, array $expected): void
    {
        if (!isset($requestData['Terminal']['HashData'])) {
            $this->cryptMock->expects(self::once())
                ->method('createHash')
                ->willReturn($expected['Terminal']['HashData']);
        }

        $actual = $this->mapper->createCustomQueryRequestData($this->account, $requestData);

        \ksort($actual['Terminal']);
        \ksort($expected['Terminal']);
        \ksort($actual);
        \ksort($expected);
        $this->assertSame($expected, $actual);
    }

    #[DataProvider('createHistoryRequestDataDataProvider')]
    public function testCreateHistoryRequestData(array $data, array $expected): void
    {
        $hashInput                         = $expected;
        $hashInput['Terminal']['HashData'] = '';

        $this->cryptMock->expects(self::once())
            ->method('createHash')
            ->with($this->account, $hashInput)
            ->willReturn($expected['Terminal']['HashData']);

        $actual = $this->mapper->createHistoryRequestData($this->account, $data);

        \ksort($actual);
        \ksort($expected);
        $this->assertSame($expected, $actual);
    }

    #[DataProvider('createBinListRequestDataDataProvider')]
    public function testCreateBinListRequestData(array $params, array $expected): void
    {
        $hashInput                         = $expected;
        $hashInput['Terminal']['HashData'] = '';

        $this->cryptMock->expects(self::once())
            ->method('createHash')
            ->with($this->account, $hashInput)
            ->willReturn($expected['Terminal']['HashData']);

        $actual = $this->mapper->createBinListRequestData($this->account, $params);

        \ksort($actual);
        \ksort($expected);
        $this->assertSame($expected, $actual);
    }

    public static function createBinListRequestDataDataProvider(): Generator
    {
        yield 'no_bin_filter' => [
            'params'   => ['ip' => '127.0.0.1'],
            'expected' => [
                'Customer'    => ['IPAddress' => '127.0.0.1'],
                'Mode'        => 'TEST',
                'Order'       => ['OrderID' => ''],
                'Terminal'    => [
                    'ProvUserID' => 'PROVAUT',
                    'UserID'     => 'PROVAUT',
                    'HashData'   => 'mocked_bin_hash',
                    'ID'         => '30691298',
                    'MerchantID' => '7000679',
                ],
                'Transaction' => [
                    'Type'   => 'bininq',
                    'Amount' => 100,
                    'BINInq' => [
                        'Group'    => 'A',
                        'CardType' => 'A',
                    ],
                ],
                'Version'     => 'v0.1',
            ],
        ];

        yield 'with_bin_filter' => [
            'params'   => ['ip' => '127.0.0.1', 'bin' => '415956'],
            'expected' => [
                'Customer'    => ['IPAddress' => '127.0.0.1'],
                'Mode'        => 'TEST',
                'Order'       => ['OrderID' => ''],
                'Terminal'    => [
                    'ProvUserID' => 'PROVAUT',
                    'UserID'     => 'PROVAUT',
                    'HashData'   => 'mocked_bin_hash',
                    'ID'         => '30691298',
                    'MerchantID' => '7000679',
                ],
                'Transaction' => [
                    'Type'   => 'bininq',
                    'Amount' => 100,
                    'BINInq' => [
                        'Group'    => 'A',
                        'CardType' => 'A',
                        'BINNum'   => '415956',
                    ],
                ],
                'Version'     => 'v0.1',
            ],
        ];

        yield 'with_card_class_credit' => [
            'params'   => ['ip' => '127.0.0.1', 'card_class' => CreditCardInterface::CARD_CLASS_CREDIT],
            'expected' => [
                'Customer'    => ['IPAddress' => '127.0.0.1'],
                'Mode'        => 'TEST',
                'Order'       => ['OrderID' => ''],
                'Terminal'    => [
                    'ProvUserID' => 'PROVAUT',
                    'UserID'     => 'PROVAUT',
                    'HashData'   => 'mocked_bin_hash',
                    'ID'         => '30691298',
                    'MerchantID' => '7000679',
                ],
                'Transaction' => [
                    'Type'   => 'bininq',
                    'Amount' => 100,
                    'BINInq' => [
                        'Group'    => 'A',
                        'CardType' => 'C',
                    ],
                ],
                'Version'     => 'v0.1',
            ],
        ];

        yield 'with_bin_and_card_class_debit' => [
            'params'   => ['ip' => '127.0.0.1', 'bin' => '415956', 'card_class' => CreditCardInterface::CARD_CLASS_DEBIT],
            'expected' => [
                'Customer'    => ['IPAddress' => '127.0.0.1'],
                'Mode'        => 'TEST',
                'Order'       => ['OrderID' => ''],
                'Terminal'    => [
                    'ProvUserID' => 'PROVAUT',
                    'UserID'     => 'PROVAUT',
                    'HashData'   => 'mocked_bin_hash',
                    'ID'         => '30691298',
                    'MerchantID' => '7000679',
                ],
                'Transaction' => [
                    'Type'   => 'bininq',
                    'Amount' => 100,
                    'BINInq' => [
                        'Group'    => 'A',
                        'CardType' => 'D',
                        'BINNum'   => '415956',
                    ],
                ],
                'Version'     => 'v0.1',
            ],
        ];
    }

    public static function createCustomQueryRequestDataDataProvider(): Generator
    {
        yield 'without_terminal_data' => [
            'request_data' => [
                'Version'     => 'v0.00',
                'Customer'    => ['IPAddress' => '1.1.111.111'],
                'Order'       => ['OrderID' => 'ORDER123'],
                'Transaction' => ['Type' => 'bininq', 'Amount' => '1'],
            ],
            'expected' => [
                'Customer'    => ['IPAddress' => '1.1.111.111'],
                'Mode'        => 'TEST',
                'Order'       => ['OrderID' => 'ORDER123'],
                'Terminal'    => [
                    'ProvUserID' => 'PROVAUT',
                    'UserID'     => 'PROVAUT',
                    'HashData'   => 'generated_hash',
                    'ID'         => '30691298',
                    'MerchantID' => '7000679',
                ],
                'Transaction' => ['Type' => 'bininq', 'Amount' => '1'],
                'Version'     => 'v0.00',
            ],
        ];

        yield 'with_terminal_and_hash_already_set' => [
            'request_data' => [
                'Mode'        => 'TEST',
                'Version'     => 'v0.00',
                'Customer'    => ['IPAddress' => '1.1.111.111'],
                'Order'       => ['OrderID' => 'ORDER123'],
                'Terminal'    => [
                    'ProvUserID' => 'CUSTOM_USER',
                    'UserID'     => 'CUSTOM_USER',
                    'HashData'   => 'pre_set_hash',
                    'ID'         => 'CUSTOM_TERMINAL',
                    'MerchantID' => 'CUSTOM_MERCHANT',
                ],
                'Transaction' => ['Type' => 'bininq', 'Amount' => '1'],
            ],
            'expected' => [
                'Customer'    => ['IPAddress' => '1.1.111.111'],
                'Mode'        => 'TEST',
                'Order'       => ['OrderID' => 'ORDER123'],
                'Terminal'    => [
                    'ProvUserID' => 'CUSTOM_USER',
                    'UserID'     => 'CUSTOM_USER',
                    'HashData'   => 'pre_set_hash',
                    'ID'         => 'CUSTOM_TERMINAL',
                    'MerchantID' => 'CUSTOM_MERCHANT',
                ],
                'Transaction' => ['Type' => 'bininq', 'Amount' => '1'],
                'Version'     => 'v0.00',
            ],
        ];
    }

    public static function createHistoryRequestDataDataProvider(): Generator
    {
        yield 'date_range' => [
            'data'     => [
                'start_date' => new DateTime('2022-05-18 00:00:00'),
                'end_date'   => new DateTime('2022-05-18 23:59:59'),
                'ip'         => '127.0.0.1',
            ],
            'expected' => [
                'Customer'    => ['IPAddress' => '127.0.0.1'],
                'Mode'        => 'TEST',
                'Order'       => [
                    'OrderID'     => null,
                    'GroupID'     => null,
                    'Description' => null,
                    'StartDate'   => '18/05/2022 00:00',
                    'EndDate'     => '18/05/2022 23:59',
                    'ListPageNum' => 1,
                ],
                'Terminal'    => [
                    'ProvUserID' => 'PROVAUT',
                    'UserID'     => 'PROVAUT',
                    'HashData'   => 'mocked_hash',
                    'ID'         => '30691298',
                    'MerchantID' => '7000679',
                ],
                'Transaction' => [
                    'Type'                  => 'orderlistinq',
                    'Amount'                => 100,
                    'CurrencyCode'          => '949',
                    'CardholderPresentCode' => '0',
                    'MotoInd'               => 'N',
                ],
                'Version'     => '512',
            ],
        ];

        yield 'date_range_with_page' => [
            'data'     => [
                'start_date' => new DateTime('2022-05-18 00:00:00'),
                'end_date'   => new DateTime('2022-05-18 23:59:59'),
                'ip'         => '127.0.0.1',
                'page'       => 2,
            ],
            'expected' => [
                'Customer'    => ['IPAddress' => '127.0.0.1'],
                'Mode'        => 'TEST',
                'Order'       => [
                    'OrderID'     => null,
                    'GroupID'     => null,
                    'Description' => null,
                    'StartDate'   => '18/05/2022 00:00',
                    'EndDate'     => '18/05/2022 23:59',
                    'ListPageNum' => 2,
                ],
                'Terminal'    => [
                    'ProvUserID' => 'PROVAUT',
                    'UserID'     => 'PROVAUT',
                    'HashData'   => 'mocked_hash',
                    'ID'         => '30691298',
                    'MerchantID' => '7000679',
                ],
                'Transaction' => [
                    'Type'                  => 'orderlistinq',
                    'Amount'                => 100,
                    'CurrencyCode'          => '949',
                    'CardholderPresentCode' => '0',
                    'MotoInd'               => 'N',
                ],
                'Version'     => '512',
            ],
        ];
    }
}
