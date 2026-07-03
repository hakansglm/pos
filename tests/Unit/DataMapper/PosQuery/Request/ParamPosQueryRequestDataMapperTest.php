<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\PosQuery\Request;

use DateTimeImmutable;
use Generator;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\PosQuery\Request\AbstractQueryRequestDataMapper;
use Mews\Pos\DataMapper\PosQuery\Request\ParamPosQueryRequestDataMapper;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\RequestValueFormatterFactory;
use Mews\Pos\Factory\RequestValueMapperFactory;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\ParamPos;
use Mews\Pos\Model\Account\ParamPosAccount;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(ParamPosQueryRequestDataMapper::class)]
#[CoversClass(AbstractQueryRequestDataMapper::class)]
class ParamPosQueryRequestDataMapperTest extends TestCase
{
    private ParamPosAccount $account;

    private ParamPosQueryRequestDataMapper $mapper;

    /** @var CryptInterface & MockObject */
    private MockObject $cryptMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createParamPosAccount(
            'param-pos',
            '10738',
            'Test1',
            'Test2',
            '0c13d406-873b-403b-9c09-a5766840d98c'
        );

        $this->cryptMock = $this->createMock(CryptInterface::class);
        $this->mapper    = new ParamPosQueryRequestDataMapper(
            RequestValueMapperFactory::createForGateway(ParamPos::class),
            RequestValueFormatterFactory::createForGateway(ParamPos::class),
            $this->cryptMock
        );
    }

    public function testSupports(): void
    {
        $this->assertTrue(ParamPosQueryRequestDataMapper::supports(ParamPos::class));
        $this->assertFalse(ParamPosQueryRequestDataMapper::supports(AssecoPos::class));
    }

    public function testIsTestMode(): void
    {
        $this->assertFalse($this->mapper->isTestMode());
        $this->mapper->setTestMode(true);
        $this->assertTrue($this->mapper->isTestMode());
    }

    public function testCreateCustomQueryRequestData(): void
    {
        $requestData = [
            'TP_Islem_Izleme' => [
                'SomeField' => 'value',
            ],
        ];

        $actual = $this->mapper->createCustomQueryRequestData($this->account, $requestData);

        $this->assertArrayHasKey('soap:Body', $actual);
        $body = $actual['soap:Body']['TP_Islem_Izleme'];
        $this->assertSame('value', $body['SomeField']);
        $this->assertSame('10738', $body['G']['CLIENT_CODE']);
        $this->assertSame('Test1', $body['G']['CLIENT_USERNAME']);
        $this->assertSame('Test2', $body['G']['CLIENT_PASSWORD']);
        $this->assertSame('0c13d406-873b-403b-9c09-a5766840d98c', $body['GUID']);
    }

    public function testCreateInstallmentRatesRequestData(): void
    {
        $this->cryptMock->expects(self::never())->method(self::anything());

        $actual = $this->mapper->createInstallmentRatesRequestData($this->account, []);

        $this->assertArrayHasKey('soap:Body', $actual);
        $body = $actual['soap:Body']['TP_Ozel_Oran_SK_Liste'];
        $this->assertSame('https://turkpos.com.tr/', $body['@xmlns']);
        $this->assertSame('10738', $body['G']['CLIENT_CODE']);
        $this->assertSame('Test1', $body['G']['CLIENT_USERNAME']);
        $this->assertSame('Test2', $body['G']['CLIENT_PASSWORD']);
        $this->assertSame('0c13d406-873b-403b-9c09-a5766840d98c', $body['GUID']);
    }

    #[DataProvider('createBinListRequestDataDataProvider')]
    public function testCreateBinListRequestData(array $params, array $expected): void
    {
        $this->cryptMock->expects(self::never())->method(self::anything());

        $this->assertSame($expected, $this->mapper->createBinListRequestData($this->account, $params));
    }

    public static function createBinListRequestDataDataProvider(): array
    {
        $baseBody = [
            '@xmlns' => 'https://turkpos.com.tr/',
            'G'      => [
                'CLIENT_CODE'     => '10738',
                'CLIENT_USERNAME' => 'Test1',
                'CLIENT_PASSWORD' => 'Test2',
            ],
            'GUID'   => '0c13d406-873b-403b-9c09-a5766840d98c',
        ];

        return [
            'without_bin' => [
                'params'   => [],
                'expected' => ['soap:Body' => ['BIN_SanalPos' => $baseBody]],
            ],
            'with_bin' => [
                'params'   => ['bin' => '415956'],
                'expected' => ['soap:Body' => ['BIN_SanalPos' => $baseBody + ['BIN' => '415956']]],
            ],
        ];
    }

    #[DataProvider('createHistoryRequestDataDataProvider')]
    public function testCreateHistoryRequestData(array $data, array $expected): void
    {
        $actual = $this->mapper->createHistoryRequestData($this->account, $data);

        $this->assertSame($expected, $actual);
    }

    public static function createHistoryRequestDataDataProvider(): Generator
    {
        yield 'with_date_range' => [
            'data'     => [
                'start_date' => new DateTimeImmutable('2024-04-13 13:00:00'),
                'end_date'   => new DateTimeImmutable('2024-04-14 13:00:00'),
            ],
            'expected' => [
                'soap:Body' => [
                    'TP_Islem_Izleme' => [
                        'G'         => [
                            'CLIENT_CODE'     => '10738',
                            'CLIENT_USERNAME' => 'Test1',
                            'CLIENT_PASSWORD' => 'Test2',
                        ],
                        'GUID'      => '0c13d406-873b-403b-9c09-a5766840d98c',
                        '@xmlns'    => 'https://turkpos.com.tr/',
                        'Tarih_Bas' => '13.04.2024 13:00:00',
                        'Tarih_Bit' => '14.04.2024 13:00:00',
                    ],
                ],
            ],
        ];

        yield 'with_date_range_and_pay_auth_filter' => [
            'data'     => [
                'start_date'       => new DateTimeImmutable('2024-04-13 13:00:00'),
                'end_date'         => new DateTimeImmutable('2024-04-14 13:00:00'),
                'order_status'     => 'Başarılı',
                'transaction_type' => PosInterface::TX_TYPE_PAY_AUTH,
            ],
            'expected' => [
                'soap:Body' => [
                    'TP_Islem_Izleme' => [
                        'G'           => [
                            'CLIENT_CODE'     => '10738',
                            'CLIENT_USERNAME' => 'Test1',
                            'CLIENT_PASSWORD' => 'Test2',
                        ],
                        'GUID'        => '0c13d406-873b-403b-9c09-a5766840d98c',
                        '@xmlns'      => 'https://turkpos.com.tr/',
                        'Tarih_Bas'   => '13.04.2024 13:00:00',
                        'Tarih_Bit'   => '14.04.2024 13:00:00',
                        'Islem_Durum' => 'Başarılı',
                        'Islem_Tip'   => 'Satış',
                    ],
                ],
            ],
        ];

        yield 'with_cancel_filter' => [
            'data'     => [
                'start_date'       => new DateTimeImmutable('2024-04-13 13:00:00'),
                'end_date'         => new DateTimeImmutable('2024-04-14 13:00:00'),
                'transaction_type' => PosInterface::TX_TYPE_CANCEL,
            ],
            'expected' => [
                'soap:Body' => [
                    'TP_Islem_Izleme' => [
                        'G'         => [
                            'CLIENT_CODE'     => '10738',
                            'CLIENT_USERNAME' => 'Test1',
                            'CLIENT_PASSWORD' => 'Test2',
                        ],
                        'GUID'      => '0c13d406-873b-403b-9c09-a5766840d98c',
                        '@xmlns'    => 'https://turkpos.com.tr/',
                        'Tarih_Bas' => '13.04.2024 13:00:00',
                        'Tarih_Bit' => '14.04.2024 13:00:00',
                        'Islem_Tip' => 'İptal',
                    ],
                ],
            ],
        ];

        yield 'with_refund_filter' => [
            'data'     => [
                'start_date'       => new DateTimeImmutable('2024-04-13 13:00:00'),
                'end_date'         => new DateTimeImmutable('2024-04-14 13:00:00'),
                'transaction_type' => PosInterface::TX_TYPE_REFUND,
            ],
            'expected' => [
                'soap:Body' => [
                    'TP_Islem_Izleme' => [
                        'G'         => [
                            'CLIENT_CODE'     => '10738',
                            'CLIENT_USERNAME' => 'Test1',
                            'CLIENT_PASSWORD' => 'Test2',
                        ],
                        'GUID'      => '0c13d406-873b-403b-9c09-a5766840d98c',
                        '@xmlns'    => 'https://turkpos.com.tr/',
                        'Tarih_Bas' => '13.04.2024 13:00:00',
                        'Tarih_Bit' => '14.04.2024 13:00:00',
                        'Islem_Tip' => 'İade',
                    ],
                ],
            ],
        ];
    }
}
