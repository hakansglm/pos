<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\PosQuery\Request;

use DateTime;
use Generator;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\PosQuery\Request\AbstractQueryRequestDataMapper;
use Mews\Pos\DataMapper\PosQuery\Request\PayForPosQueryRequestDataMapper;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\RequestValueFormatterFactory;
use Mews\Pos\Factory\RequestValueMapperFactory;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\PayForPos;
use Mews\Pos\Model\Account\PayForPosAccount;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(PayForPosQueryRequestDataMapper::class)]
#[CoversClass(AbstractQueryRequestDataMapper::class)]
class PayForPosQueryRequestDataMapperTest extends TestCase
{
    private PayForPosAccount $account;

    private PayForPosQueryRequestDataMapper $mapper;

    /** @var CryptInterface & MockObject */
    private MockObject $cryptMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createPayForPosAccount(
            'qnbfinansbank-payfor',
            '085300000009704',
            'QNB_API_KULLANICI_3DPAY',
            'UcBN0',
            '12345678',
            PayForPosAccount::MBR_ID_FINANSBANK
        );

        $this->cryptMock = $this->createMock(CryptInterface::class);
        $this->mapper    = new PayForPosQueryRequestDataMapper(
            RequestValueMapperFactory::createForGateway(PayForPos::class),
            RequestValueFormatterFactory::createForGateway(PayForPos::class),
            $this->cryptMock,
            PosInterface::LANG_EN
        );
    }

    public function testSupports(): void
    {
        $this->assertTrue(PayForPosQueryRequestDataMapper::supports(PayForPos::class));
        $this->assertFalse(PayForPosQueryRequestDataMapper::supports(AssecoPos::class));
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

    #[DataProvider('createHistoryRequestDataDataProvider')]
    public function testCreateHistoryRequestData(array $data, array $expected): void
    {
        $actual = $this->mapper->createHistoryRequestData($this->account, $data);

        \ksort($actual);
        \ksort($expected);
        $this->assertSame($expected, $actual);
    }

    public static function createCustomQueryRequestDataDataProvider(): Generator
    {
        yield 'without_account_data' => [
            'request_data' => [
                'Type'   => 'Query',
                'Number' => '4111111111111111',
            ],
            'expected' => [
                'MerchantId' => '085300000009704',
                'UserCode'   => 'QNB_API_KULLANICI_3DPAY',
                'UserPass'   => 'UcBN0',
                'MbrId'      => '5',
                'Type'       => 'Query',
                'Number'     => '4111111111111111',
            ],
        ];

        yield 'with_account_data_already_set' => [
            'request_data' => [
                'MerchantId' => 'CUSTOM',
                'UserCode'   => 'CUSTOMUSER',
                'UserPass'   => 'CUSTOMPASS',
                'MbrId'      => '99',
                'Type'       => 'Query',
            ],
            'expected' => [
                'MerchantId' => 'CUSTOM',
                'UserCode'   => 'CUSTOMUSER',
                'UserPass'   => 'CUSTOMPASS',
                'MbrId'      => '99',
                'Type'       => 'Query',
            ],
        ];
    }

    public static function createHistoryRequestDataDataProvider(): Generator
    {
        yield 'finansbank_default_lang' => [
            'data' => [
                'transaction_date' => new DateTime('2022-05-18'),
            ],
            'expected' => [
                'MerchantId' => '085300000009704',
                'UserCode'   => 'QNB_API_KULLANICI_3DPAY',
                'UserPass'   => 'UcBN0',
                'MbrId'      => '5',
                'SecureType' => 'Report',
                'TxnType'    => 'TxnHistory',
                'Lang'       => 'EN',
                'ReqDate'    => '20220518',
            ],
        ];

        yield 'with_explicit_lang_tr' => [
            'data' => [
                'transaction_date' => new DateTime('2022-05-18'),
                'lang'             => PosInterface::LANG_TR,
            ],
            'expected' => [
                'MerchantId' => '085300000009704',
                'UserCode'   => 'QNB_API_KULLANICI_3DPAY',
                'UserPass'   => 'UcBN0',
                'MbrId'      => '5',
                'SecureType' => 'Report',
                'TxnType'    => 'TxnHistory',
                'Lang'       => 'TR',
                'ReqDate'    => '20220518',
            ],
        ];
    }
}
