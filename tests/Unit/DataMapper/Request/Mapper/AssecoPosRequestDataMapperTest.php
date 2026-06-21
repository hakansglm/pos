<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\Request\Mapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\Request\Mapper\AbstractRequestDataMapper;
use Mews\Pos\DataMapper\Request\Mapper\AssecoPosRequestDataMapper;
use Mews\Pos\DataMapper\Request\ValueFormatter\AssecoPosRequestValueFormatter;
use Mews\Pos\DataMapper\Request\ValueMapper\AssecoPosRequestValueMapper;
use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Model\Account\AssecoPosAccount;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\Event\Before3DFormHashCalculatedEvent;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Gateway\AkbankPos;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[CoversClass(AssecoPosRequestDataMapper::class)]
#[CoversClass(AbstractRequestDataMapper::class)]
class AssecoPosRequestDataMapperTest extends TestCase
{
    private AssecoPosAccount $account;

    private CreditCardInterface $card;

    private AssecoPosRequestDataMapper $requestDataMapper;

    /** @var CryptInterface & MockObject */
    private MockObject $crypt;

    /** @var EventDispatcherInterface & MockObject */
    private MockObject $dispatcher;

    private AssecoPosRequestValueFormatter $valueFormatter;

    private AssecoPosRequestValueMapper $valueMapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createAssecoPosAccount(
            'payten_v3_hash',
            '190100000',
            'ZIRAATAPI',
            'ZIRAAT19',
            PosInterface::MODEL_3D_SECURE,
            '123456'
        );

        $this->dispatcher     = $this->createMock(EventDispatcherInterface::class);
        $this->crypt          = $this->createMock(CryptInterface::class);
        $this->valueFormatter = new AssecoPosRequestValueFormatter();
        $this->valueMapper    = new AssecoPosRequestValueMapper();

        $this->requestDataMapper = new AssecoPosRequestDataMapper(
            $this->valueMapper,
            $this->valueFormatter,
            $this->dispatcher,
            $this->crypt,
            PosInterface::LANG_EN
        );

        $this->card = CreditCardFactory::create('5555444433332222', '22', '01', '123', 'ahmet', CreditCardInterface::CARD_TYPE_VISA);
    }

    public function testSupports(): void
    {
        $result = $this->requestDataMapper::supports(AssecoPos::class);
        $this->assertTrue($result);

        $result = $this->requestDataMapper::supports(AkbankPos::class);
        $this->assertFalse($result);
    }

    /**
     * @dataProvider threeDFormDataProvider
     */
    public function testGet3DFormData(
        array  $order,
        string $gatewayURL,
        string $txType,
        string $paymentModel,
        bool   $isWithCard,
        array  $expected
    ): void {
        $this->crypt->expects(self::once())
            ->method('create3DHash')
            ->willReturn($expected['inputs']['hash']);

        $this->crypt->expects(self::once())
            ->method('generateRandomString')
            ->willReturn($expected['inputs']['rnd']);

        $this->dispatcher->expects(self::once())
            ->method('dispatch')
            ->with($this->callback(static fn ($dispatchedEvent): bool => $dispatchedEvent instanceof Before3DFormHashCalculatedEvent
                && AssecoPos::class === $dispatchedEvent->getGatewayClass()
                && $txType === $dispatchedEvent->getTxType()
                && $paymentModel === $dispatchedEvent->getPaymentModel()
                && count($dispatchedEvent->getFormInputs()) > 3));

        $actual = $this->requestDataMapper->create3DFormData(
            $this->account,
            $order,
            $paymentModel,
            $txType,
            $gatewayURL,
            $isWithCard ? $this->card : null
        );

        \ksort($expected['inputs']);
        \ksort($actual['inputs']);

        $this->assertSame($expected, $actual);
    }

    public static function threeDFormDataProvider(): array
    {
        $order = [
            'id'          => 'order222',
            'ip'          => '127.0.0.1',
            'amount'      => '100.25',
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'lang'        => PosInterface::LANG_TR,
        ];

        return [
            'without_card' => [
                'order'        => $order,
                'gatewayUrl'   => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'isWithCard'   => false,
                'expected'     => [
                    'gateway' => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                    'method'  => 'POST',
                    'inputs'  => [
                        'clientid'      => '190100000',
                        'storetype'     => '3d',
                        'amount'        => '100.25',
                        'oid'           => 'order222',
                        'okUrl'         => 'https://domain.com/success',
                        'failUrl'       => 'https://domain.com/fail_url',
                        'rnd'           => 'rand-21212',
                        'lang'          => 'tr',
                        'currency'      => '949',
                        'taksit'        => '',
                        'TranType'      => 'Auth',
                        'hashAlgorithm' => 'ver3',
                        'hash'          => '7tt3i3SMhzR3jYjCMwNrolSn7ksY7eKz2kVsqt/nUGK6XNw9/dMMZPVqHK9pQROGEIW3PJWut6v1Xv6ZDtnuSA==',
                    ],
                ],
            ],
            'with_card'    => [
                'order'        => $order,
                'gatewayUrl'   => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_SECURE,
                'isWithCard'   => true,
                'expected'     => [
                    'gateway' => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                    'method'  => 'POST',
                    'inputs'  => [
                        'clientid'                        => '190100000',
                        'storetype'                       => '3d',
                        'amount'                          => '100.25',
                        'oid'                             => 'order222',
                        'okUrl'                           => 'https://domain.com/success',
                        'failUrl'                         => 'https://domain.com/fail_url',
                        'rnd'                             => 'rand-21212',
                        'lang'                            => 'tr',
                        'currency'                        => '949',
                        'taksit'                          => '',
                        'pan'                             => '5555444433332222',
                        'Ecom_Payment_Card_ExpDate_Month' => '01',
                        'Ecom_Payment_Card_ExpDate_Year'  => '22',
                        'cv2'                             => '123',
                        'TranType'                        => 'Auth',
                        'hashAlgorithm'                   => 'ver3',
                        'hash'                            => '3fvBzh0HT3UiKUTXis0Ke2NG3mAp9eBOwx26bstv+l6L946GrOF2JklXfqTNc6VBeqUSkuLxo4ErtwCWuPCzYw==',
                    ],
                ],
            ],
            '3d_host'      => [
                'order'        => $order,
                'gatewayUrl'   => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
                'paymentModel' => PosInterface::MODEL_3D_HOST,
                'isWithCard'   => false,
                'expected'     => [
                    'gateway' => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                    'method'  => 'POST',
                    'inputs'  => [
                        'clientid'      => '190100000',
                        'storetype'     => '3d_host',
                        'amount'        => '100.25',
                        'oid'           => 'order222',
                        'okUrl'         => 'https://domain.com/success',
                        'failUrl'       => 'https://domain.com/fail_url',
                        'rnd'           => 'rand-21212',
                        'lang'          => 'tr',
                        'currency'      => '949',
                        'taksit'        => '',
                        'TranType'      => 'Auth',
                        'hashAlgorithm' => 'ver3',
                        'hash'          => 'wlqP71Pwu5+zaCYCGxWpbqf1cAsbou5p5PDAds4YcejFO5AVTw0PjnzNiFnYX900ZL38rQw8Jt/YhMmZ5bJ/qA==',
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider postAuthRequestDataProvider
     */
    public function testCreateNonSecurePostAuthPaymentRequestData(array $order, array $expected): void
    {
        $actual = $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($this->account, $order);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider nonSecurePaymentRequestDataDataProvider
     */
    public function testCreateNonSecurePaymentRequestData(array $order, CreditCardInterface $card, string $txType, array $expectedData): void
    {
        $actual = $this->requestDataMapper->createNonSecurePaymentRequestData(
            $this->account,
            $order,
            $txType,
            $card
        );

        $this->assertSame($expectedData, $actual);
    }

    /**
     * @dataProvider createCancelRequestDataProvider
     */
    public function testCreateCancelRequestData(array $order, array $expected): void
    {
        $actual = $this->requestDataMapper->createCancelRequestData($this->account, $order);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider orderHistoryRequestDataProvider
     */
    public function testCreateOrderHistoryRequestData(array $order, array $expected): void
    {
        $actual = $this->requestDataMapper->createOrderHistoryRequestData($this->account, $order);
        $this->assertSame($expected, $actual);
    }

    public function testCreateHistoryRequestData(): void
    {
        $this->expectException(\Mews\Pos\Exception\NotImplementedException::class);
        $this->requestDataMapper->createHistoryRequestData($this->account);
    }

    /**
     * @dataProvider threeDPaymentRequestDataDataProvider
     */
    public function testCreate3DPaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, array $responseData, array $expected): void
    {
        $actual = $this->requestDataMapper->create3DPaymentRequestData($posAccount, $order, $txType, $responseData);
        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider statusRequestDataProvider
     */
    public function testCreateStatusRequestData(array $order, array $expected): void
    {
        $actualData = $this->requestDataMapper->createStatusRequestData($this->account, $order);

        $this->assertSame($expected, $actualData);
    }

    /**
     * @dataProvider refundRequestDataProvider
     */
    public function testCreateRefundRequestData(array $order, string $txType, array $expectedData): void
    {
        $actual = $this->requestDataMapper->createRefundRequestData($this->account, $order, $txType);

        \ksort($actual);
        \ksort($expectedData);
        $this->assertSame($expectedData, $actual);
    }

    /**
     * @dataProvider createCustomQueryRequestDataDataProvider
     */
    public function testCreateCustomQueryRequestData(array $requestData, array $expectedData): void
    {
        $actual = $this->requestDataMapper->createCustomQueryRequestData($this->account, $requestData);

        \ksort($actual);
        \ksort($expectedData);
        $this->assertSame($expectedData, $actual);
    }

    public static function createCustomQueryRequestDataDataProvider(): \Generator
    {
        yield 'without_account_data' => [
            'request_data' => [
                'Type'     => 'Query',
                'Number'   => '4111111111111111',
                'Expires'  => '10.2025',
                'Extra'    => [
                    'IMECECARDQUERY' => null,
                ],
            ],
            'expected' => [
                'Name'     => 'ZIRAATAPI',
                'Password' => 'ZIRAAT19',
                'ClientId' => '190100000',
                'Type'     => 'Query',
                'Number'   => '4111111111111111',
                'Expires'  => '10.2025',
                'Extra'    => [
                    'IMECECARDQUERY' => null,
                ],
            ],
        ];

        yield 'with_account_data' => [
            'request_data' => [
                'Name'     => 'ACCOUNTNAME',
                'Password' => 'ACCOUNTPASSWORD',
                'ClientId' => 'ACCCOUNTCLIENTID',
                'Type'     => 'Query',
                'Number'   => '4111111111111111',
                'Expires'  => '10.2025',
                'Extra'    => [
                    'IMECECARDQUERY' => null,
                ],
            ],
            'expected' => [
                'Name'     => 'ACCOUNTNAME',
                'Password' => 'ACCOUNTPASSWORD',
                'ClientId' => 'ACCCOUNTCLIENTID',
                'Type'     => 'Query',
                'Number'   => '4111111111111111',
                'Expires'  => '10.2025',
                'Extra'    => [
                    'IMECECARDQUERY' => null,
                ],
            ],
        ];
    }

    public static function threeDPaymentRequestDataDataProvider(): \Generator
    {
        $account = AccountFactory::createAssecoPosAccount(
            'akbank',
            '190100000',
            'ZIRAATAPI',
            'ZIRAAT19',
            PosInterface::MODEL_3D_SECURE,
            'TRPS0200'
        );

        $order = [
            'id'          => 'order222',
            'ip'          => '127.0.0.1',
            'amount'      => '100.25',
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'lang'        => 'tr',
        ];

        $responseData = [
            'md'   => '1',
            'xid'  => '100000005xid',
            'eci'  => '100000005eci',
            'cavv' => 'cavv',
        ];

        yield [
            'account'      => $account,
            'order'        => $order,
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
            'responseData' => $responseData,
            'expected'     => [
                'Name'                    => 'ZIRAATAPI',
                'Password'                => 'ZIRAAT19',
                'ClientId'                => '190100000',
                'Type'                    => 'Auth',
                'IPAddress'               => '127.0.0.1',
                'OrderId'                 => 'order222',
                'Total'                   => '100.25',
                'Currency'                => '949',
                'Taksit'                  => '',
                'Number'                  => '1',
                'PayerTxnId'              => '100000005xid',
                'PayerSecurityLevel'      => '100000005eci',
                'PayerAuthenticationCode' => 'cavv',
                'Mode'                    => 'P',
            ],
        ];

        $order['recurring']   = [
            'frequency'     => 2,
            'frequencyType' => 'MONTH',
            'installment'   => 3,
        ];
        $order['installment'] = 0;

        yield 'recurring_order' => [
            'account'      => $account,
            'order'        => $order,
            'txType'       => PosInterface::TX_TYPE_PAY_AUTH,
            'responseData' => $responseData,
            'expected'     => [
                'Name'                    => 'ZIRAATAPI',
                'Password'                => 'ZIRAAT19',
                'ClientId'                => '190100000',
                'Type'                    => 'Auth',
                'IPAddress'               => '127.0.0.1',
                'OrderId'                 => 'order222',
                'Total'                   => '100.25',
                'Currency'                => '949',
                'Taksit'                  => '',
                'Number'                  => '1',
                'PayerTxnId'              => '100000005xid',
                'PayerSecurityLevel'      => '100000005eci',
                'PayerAuthenticationCode' => 'cavv',
                'Mode'                    => 'P',
                'PbOrder'                 => [
                    'OrderType'              => '0',
                    'OrderFrequencyInterval' => '2',
                    'OrderFrequencyCycle'    => 'M',
                    'TotalNumberPayments'    => '3',
                ],
            ],
        ];
    }

    public static function postAuthRequestDataProvider(): array
    {
        return [
            'without_amount'       => [
                'order'    => [
                    'id' => '2020110828BC',
                ],
                'expected' => [
                    'Name'     => 'ZIRAATAPI',
                    'Password' => 'ZIRAAT19',
                    'ClientId' => '190100000',
                    'Type'     => 'PostAuth',
                    'OrderId'  => '2020110828BC',
                    'Total'    => null,
                ],
            ],
            'with_amount'          => [
                'order'    => [
                    'id'     => '2020110828BC',
                    'amount' => 1.0,
                ],
                'expected' => [
                    'Name'     => 'ZIRAATAPI',
                    'Password' => 'ZIRAAT19',
                    'ClientId' => '190100000',
                    'Type'     => 'PostAuth',
                    'OrderId'  => '2020110828BC',
                    'Total'    => '1',
                ],
            ],
            'with_pre_auth_amount' => [
                'order'    => [
                    'id'              => '2020110828BC',
                    'amount'          => 1.1,
                    'pre_auth_amount' => 1.0,
                ],
                'expected' => [
                    'Name'     => 'ZIRAATAPI',
                    'Password' => 'ZIRAAT19',
                    'ClientId' => '190100000',
                    'Type'     => 'PostAuth',
                    'OrderId'  => '2020110828BC',
                    'Total'    => '1.1',
                    'Extra'    => [
                        'PREAMT' => '1',
                    ],
                ],
            ],
        ];
    }

    public static function orderHistoryRequestDataProvider(): array
    {
        return [
            [
                'order'    => [
                    'id' => '2020110828BC',
                ],
                'expected' => [
                    'Name'     => 'ZIRAATAPI',
                    'Password' => 'ZIRAAT19',
                    'ClientId' => '190100000',
                    'OrderId'  => '2020110828BC',
                    'Extra'    => [
                        'ORDERHISTORY' => 'QUERY',
                    ],
                ],
            ],
        ];
    }

    public static function refundRequestDataProvider(): array
    {
        return [
            'full_refund'    => [
                'order'    => [
                    'id'       => 'order-123',
                    'currency' => PosInterface::CURRENCY_TRY,
                    'amount'   => 5,
                ],
                'txType'   => PosInterface::TX_TYPE_REFUND,
                'expected' => [
                    'ClientId' => '190100000',
                    'Currency' => '949',
                    'Name'     => 'ZIRAATAPI',
                    'OrderId'  => 'order-123',
                    'Password' => 'ZIRAAT19',
                    'Total'    => '5',
                    'Type'     => 'Credit',
                ],
            ],
            'partial_refund' => [
                'order'    => [
                    'id'       => 'order-123',
                    'currency' => PosInterface::CURRENCY_TRY,
                    'amount'   => 5,
                ],
                'txType'   => PosInterface::TX_TYPE_REFUND_PARTIAL,
                'expected' => [
                    'ClientId' => '190100000',
                    'Currency' => '949',
                    'Name'     => 'ZIRAATAPI',
                    'OrderId'  => 'order-123',
                    'Password' => 'ZIRAAT19',
                    'Total'    => '5',
                    'Type'     => 'Credit',
                ],
            ],
        ];
    }

    public static function createCancelRequestDataProvider(): array
    {
        return [
            [
                'order'    => [
                    'id' => '2020110828BC',
                ],
                'expected' => [
                    'Name'     => 'ZIRAATAPI',
                    'Password' => 'ZIRAAT19',
                    'ClientId' => '190100000',
                    'OrderId'  => '2020110828BC',
                    'Type'     => 'Void',
                ],
            ],
            [
                'order'    => [
                    'id'                              => '2020110828BC',
                    'recurringOrderInstallmentNumber' => '2',
                ],
                'expected' => [
                    'Name'     => 'ZIRAATAPI',
                    'Password' => 'ZIRAAT19',
                    'ClientId' => '190100000',
                    'Extra'    => [
                        'RECORDTYPE'         => 'Order',
                        'RECURRINGOPERATION' => 'Cancel',
                        'RECORDID'           => '2020110828BC-2',
                    ],
                ],
            ],
        ];
    }

    public static function nonSecurePaymentRequestDataDataProvider(): array
    {
        $card = CreditCardFactory::create(
            '5555444433332222',
            '22',
            '01',
            '123',
            'ahmet',
        );

        return [
            'basic'     => [
                'order'    => [
                    'id'     => 'order222',
                    'amount' => 10.01,
                    'ip'     => '127.0.0.1',
                ],
                'card'     => $card,
                'txType'   => PosInterface::TX_TYPE_PAY_AUTH,
                'expected' => [
                    'Name'      => 'ZIRAATAPI',
                    'Password'  => 'ZIRAAT19',
                    'ClientId'  => '190100000',
                    'Type'      => 'Auth',
                    'IPAddress' => '127.0.0.1',
                    'OrderId'   => 'order222',
                    'Total'     => '10.01',
                    'Currency'  => '949',
                    'Taksit'    => '',
                    'Number'    => '5555444433332222',
                    'Expires'   => '01/22',
                    'Cvv2Val'   => '123',
                    'Mode'      => 'P',
                ],
            ],
            'recurring' => [
                'order'    => [
                    'id'        => 'order222',
                    'amount'    => 10.01,
                    'ip'        => '127.0.0.1',
                    'recurring' => [
                        'frequency'     => 3,
                        'frequencyType' => 'MONTH',
                        'installment'   => 4,
                    ],
                ],
                'card'     => $card,
                'txType'   => PosInterface::TX_TYPE_PAY_AUTH,
                'expected' => [
                    'Name'      => 'ZIRAATAPI',
                    'Password'  => 'ZIRAAT19',
                    'ClientId'  => '190100000',
                    'Type'      => 'Auth',
                    'IPAddress' => '127.0.0.1',
                    'OrderId'   => 'order222',
                    'Total'     => '10.01',
                    'Currency'  => '949',
                    'Taksit'    => '',
                    'Number'    => '5555444433332222',
                    'Expires'   => '01/22',
                    'Cvv2Val'   => '123',
                    'Mode'      => 'P',
                    'PbOrder'   => [
                        'OrderType'              => '0',
                        'OrderFrequencyInterval' => '3',
                        'OrderFrequencyCycle'    => 'M',
                        'TotalNumberPayments'    => '4',
                    ],
                ],
            ],
        ];
    }

    public static function statusRequestDataProvider(): array
    {
        return [
            [
                'order'    => [
                    'id' => '2020110828BC',
                ],
                'expected' => [
                    'Name'     => 'ZIRAATAPI',
                    'Password' => 'ZIRAAT19',
                    'ClientId' => '190100000',
                    'Extra'    => [
                        'ORDERSTATUS' => 'QUERY',
                    ],
                    'OrderId'  => '2020110828BC',
                ],
            ],
            [
                'order'    => [
                    'recurringId' => '22303O8EA19252',
                ],
                'expected' => [
                    'Name'     => 'ZIRAATAPI',
                    'Password' => 'ZIRAAT19',
                    'ClientId' => '190100000',
                    'Extra'    => [
                        'ORDERSTATUS' => 'QUERY',
                        'RECURRINGID' => '22303O8EA19252',
                    ],
                ],
            ],
        ];
    }
}
