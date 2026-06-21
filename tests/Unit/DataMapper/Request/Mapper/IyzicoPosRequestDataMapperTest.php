<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\Request\Mapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\Request\Mapper\AbstractRequestDataMapper;
use Mews\Pos\DataMapper\Request\Mapper\IyzicoPosRequestDataMapper;
use Mews\Pos\DataMapper\Request\ValueFormatter\IyzicoPosRequestValueFormatter;
use Mews\Pos\DataMapper\Request\ValueMapper\IyzicoPosRequestValueMapper;
use Mews\Pos\Entity\Account\IyzicoPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\Gateways\IyzicoPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[CoversClass(IyzicoPosRequestDataMapper::class)]
#[CoversClass(AbstractRequestDataMapper::class)]
class IyzicoPosRequestDataMapperTest extends TestCase
{
    private IyzicoPosAccount $account;

    private CreditCardInterface $card;

    private IyzicoPosRequestDataMapper $requestDataMapper;

    /** @var CryptInterface & MockObject */
    private MockObject $cryptMock;

    /** @var EventDispatcherInterface & MockObject */
    private MockObject $dispatcherMock;

    /** @var array<string, mixed> */
    private array $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createIyzicoPosAccount(
            'iyzico',
            'sandbox-apiKey',
            'sandbox-secretKey'
        );

        $this->dispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->cryptMock      = $this->createMock(CryptInterface::class);
        $valueFormatter = new IyzicoPosRequestValueFormatter();
        $valueMapper    = new IyzicoPosRequestValueMapper();

        $this->requestDataMapper = new IyzicoPosRequestDataMapper(
            $valueMapper,
            $valueFormatter,
            $this->dispatcherMock,
            $this->cryptMock,
            PosInterface::LANG_TR
        );

        $this->card = CreditCardFactory::create(
            '5555444433332222',
            '26',
            '12',
            '123',
            'John Doe'
        );

        $this->order = self::buildOrderStatic();
    }

    public function testSupports(): void
    {
        $this->assertTrue($this->requestDataMapper::supports(IyzicoPos::class));
        $this->assertFalse($this->requestDataMapper::supports(AkbankPos::class));
    }

    public function testGetCrypt(): void
    {
        $this->assertSame($this->cryptMock, $this->requestDataMapper->getCrypt());
    }

    public function testIsTestModeDefaultsFalse(): void
    {
        $this->assertFalse($this->requestDataMapper->isTestMode());
    }

    public function testSetTestMode(): void
    {
        $this->requestDataMapper->setTestMode(true);
        $this->assertTrue($this->requestDataMapper->isTestMode());

        $this->requestDataMapper->setTestMode(false);
        $this->assertFalse($this->requestDataMapper->isTestMode());
    }

    public function testCreate3DFormDataThrows(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->requestDataMapper->create3DFormData($this->account, [], PosInterface::MODEL_3D_SECURE, PosInterface::TX_TYPE_PAY_AUTH, 'https://example.com');
    }

    /**
     * @dataProvider createStatusRequestDataProvider
     */
    public function testCreateStatusRequestData(array $order, array $expected): void
    {
        $actual = $this->requestDataMapper->createStatusRequestData($this->account, $order);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider createOrderHistoryRequestDataProvider
     */
    public function testCreateOrderHistoryRequestData(array $order, array $expected): void
    {
        $actual = $this->requestDataMapper->createOrderHistoryRequestData($this->account, $order);

        $this->assertSame($expected, $actual);
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
     * @dataProvider refundRequestDataProvider
     */
    public function testCreateRefundRequestData(array $order, array $expected): void
    {
        $actual = $this->requestDataMapper->createRefundRequestData($this->account, $order, PosInterface::TX_TYPE_REFUND);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider create3DPaymentRequestDataProvider
     */
    public function testCreate3DPaymentRequestData(array $responseData, array $expected): void
    {
        $actual = $this->requestDataMapper->create3DPaymentRequestData(
            $this->account,
            $this->order,
            PosInterface::TX_TYPE_PAY_AUTH,
            $responseData
        );

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider createNonSecurePostAuthPaymentRequestDataProvider
     */
    public function testCreateNonSecurePostAuthPaymentRequestData(array $order, array $expected): void
    {
        $actual = $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($this->account, $order);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider createHistoryRequestDataProvider
     */
    public function testCreateHistoryRequestData(array $data, array $expected): void
    {
        $actual = $this->requestDataMapper->createHistoryRequestData($this->account, $data);

        $this->assertSame($expected, $actual);
    }

    public function testCreateCustomQueryRequestData(): void
    {
        $requestData = ['custom' => 'data'];
        $actual      = $this->requestDataMapper->createCustomQueryRequestData($this->account, $requestData);

        $this->assertSame($requestData, $actual);
    }

    /**
     * @dataProvider create3DHostPaymentStatusRequestDataProvider
     */
    public function testCreate3DHostPaymentStatusRequestData(array $responseData, array $order, array $expected): void
    {
        $actual = $this->requestDataMapper->create3DHostPaymentStatusRequestData($responseData, $order);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider nonSecurePaymentRequestDataProvider
     */
    public function testCreateNonSecurePaymentRequestData(
        IyzicoPosAccount $account,
        array $order,
        array $expected
    ): void {
        $actual = $this->requestDataMapper->createNonSecurePaymentRequestData(
            $account,
            $order,
            PosInterface::TX_TYPE_PAY_AUTH,
            $this->card
        );

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider enrollmentCheckRequestDataProvider
     */
    public function testCreate3DFormInitializeRequestData(
        string $paymentModel,
        array $orderExtra,
        array $expectedExtra
    ): void {
        $order  = array_merge($this->order, $orderExtra);
        $actual = $this->requestDataMapper->create3DFormInitializeRequestData(
            $this->account,
            $order,
            $paymentModel,
            PosInterface::TX_TYPE_PAY_AUTH,
            $this->card
        );

        $this->assertSame('tr', $actual['locale']);
        $this->assertSame((string) $order['id'], $actual['conversationId']);
        $this->assertSame($order['amount'], $actual['price']);
        $this->assertSame('TRY', $actual['currency']);

        foreach ($expectedExtra as $key => $value) {
            $this->assertArrayHasKey($key, $actual);
            $this->assertSame($value, $actual[$key]);
        }
    }

    public static function createStatusRequestDataProvider(): array
    {
        return [
            'with_order_id_only' => [
                'order'    => ['id' => 'order-123', 'lang' => PosInterface::LANG_TR],
                'expected' => [
                    'locale'                => 'tr',
                    'paymentConversationId' => 'order-123',
                    'conversationId'        => 'order-123',
                ],
            ],
            'with_transaction_id_only' => [
                'order'    => ['transaction_id' => 'tx-001'],
                'expected' => [
                    'locale'    => 'tr',
                    'paymentId' => 'tx-001',
                ],
            ],
            'with_both_ids' => [
                'order'    => ['id' => 'order-123', 'transaction_id' => 'tx-001'],
                'expected' => [
                    'locale'                => 'tr',
                    'paymentId'             => 'tx-001',
                    'paymentConversationId' => 'order-123',
                    'conversationId'        => 'order-123',
                ],
            ],
        ];
    }

    public static function createOrderHistoryRequestDataProvider(): array
    {
        return [
            'with_order_id_only' => [
                'order'    => ['id' => 'order-456'],
                'expected' => [
                    'locale'                => 'tr',
                    'paymentConversationId' => 'order-456',
                    'conversationId'        => 'order-456',
                ],
            ],
            'with_transaction_id_only' => [
                'order'    => ['transaction_id' => 'tx-002'],
                'expected' => [
                    'locale'    => 'tr',
                    'paymentId' => 'tx-002',
                ],
            ],
            'with_both_ids' => [
                'order'    => ['id' => 'order-456', 'transaction_id' => 'tx-002'],
                'expected' => [
                    'locale'                => 'tr',
                    'paymentId'             => 'tx-002',
                    'paymentConversationId' => 'order-456',
                    'conversationId'        => 'order-456',
                ],
            ],
        ];
    }

    public static function createCancelRequestDataProvider(): array
    {
        return [
            'with_ip_and_transaction_id' => [
                'order'    => [
                    'id'             => 'order-789',
                    'transaction_id' => 'tx-001',
                    'ip'             => '127.0.0.1',
                ],
                'expected' => [
                    'locale'         => 'tr',
                    'conversationId' => 'order-789',
                    'paymentId'      => 'tx-001',
                    'ip'             => '127.0.0.1',
                ],
            ],
        ];
    }

    public static function refundRequestDataProvider(): array
    {
        return [
            'full_refund' => [
                'order'    => [
                    'id'             => 'order-ref-1',
                    'transaction_id' => 'tx-ref-1',
                    'amount'         => 50.0,
                    'currency'       => PosInterface::CURRENCY_TRY,
                    'ip'             => '192.168.1.1',
                ],
                'expected' => [
                    'locale'         => 'tr',
                    'conversationId' => 'order-ref-1',
                    'paymentId'      => 'tx-ref-1',
                    'price'          => 50.0,
                    'currency'       => 'TRY',
                    'ip'             => '192.168.1.1',
                ],
            ],
        ];
    }

    public static function create3DPaymentRequestDataProvider(): array
    {
        $order = self::buildOrderStatic();

        return [
            'default' => [
                'responseData' => [
                    'conversationId' => 'conv-1',
                    'paymentId'      => 'pay-1',
                ],
                'expected'     => [
                    'locale'         => 'tr',
                    'conversationId' => 'conv-1',
                    'paymentId'      => 'pay-1',
                    'paidPrice'      => $order['amount'],
                    'basketId'       => (string) $order['id'],
                    'currency'       => 'TRY',
                ],
            ],
        ];
    }

    public static function createNonSecurePostAuthPaymentRequestDataProvider(): array
    {
        return [
            'default' => [
                'order'    => [
                    'id'             => 'order-1',
                    'transaction_id' => 'tx-1',
                    'amount'         => 100.0,
                    'currency'       => PosInterface::CURRENCY_TRY,
                    'ip'             => '127.0.0.1',
                ],
                'expected' => [
                    'locale'         => 'tr',
                    'conversationId' => 'order-1',
                    'paymentId'      => 'tx-1',
                    'paidPrice'      => 100.0,
                    'ip'             => '127.0.0.1',
                    'currency'       => 'TRY',
                ],
            ],
        ];
    }

    public static function createHistoryRequestDataProvider(): array
    {
        return [
            'default_page' => [
                'data'     => [
                    'transaction_date' => new \DateTimeImmutable('2024-06-01'),
                ],
                'expected' => [
                    'locale'          => 'tr',
                    'transactionDate' => '2024-06-01',
                    'page'            => 1,
                ],
            ],
            'custom_page'  => [
                'data'     => [
                    'transaction_date' => new \DateTimeImmutable('2024-06-01'),
                    'page'             => 3,
                ],
                'expected' => [
                    'locale'          => 'tr',
                    'transactionDate' => '2024-06-01',
                    'page'            => 3,
                ],
            ],
        ];
    }

    public static function create3DHostPaymentStatusRequestDataProvider(): array
    {
        return [
            'default' => [
                'responseData' => ['token' => 'tok-123'],
                'order'        => ['id' => 'order-1'],
                'expected'     => [
                    'locale'         => 'tr',
                    'conversationId' => 'order-1',
                    'token'          => 'tok-123',
                ],
            ],
        ];
    }

    public static function nonSecurePaymentRequestDataProvider(): \Generator
    {
        $account = AccountFactory::createIyzicoPosAccount('iyzico', 'key', 'secret');
        $order   = self::buildOrderStatic();
        $card    = CreditCardFactory::create('5555444433332222', '26', '12', '123', 'John Doe');

        $expectedBase = [
            'locale'          => 'tr',
            'conversationId'  => (string) $order['id'],
            'basketId'        => (string) $order['id'],
            'price'           => $order['amount'],
            'paidPrice'       => $order['amount'],
            'currency'        => 'TRY',
            'paymentGroup'    => 'PRODUCT',
            'buyer'           => [
                'id'                  => (string) $order['buyer']['id'],
                'name'                => (string) $order['buyer']['name'],
                'surname'             => (string) $order['buyer']['surname'],
                'identityNumber'      => (string) $order['buyer']['identity_number'],
                'email'               => (string) $order['buyer']['email'],
                'gsmNumber'           => (string) $order['buyer']['gsm_number'],
                'registrationAddress' => (string) $order['buyer']['registration_address'],
                'city'                => (string) $order['buyer']['city'],
                'country'             => (string) $order['buyer']['country'],
                'zipCode'             => (string) $order['buyer']['zip_code'],
                'ip'                  => (string) $order['buyer']['ip'],
                'registrationDate'    => '',
                'lastLoginDate'       => '',
            ],
            'shippingAddress' => [
                'contactName' => (string) $order['shipping_address']['contact_name'],
                'city'        => (string) $order['shipping_address']['city'],
                'country'     => (string) $order['shipping_address']['country'],
                'address'     => (string) $order['shipping_address']['address'],
                'zipCode'     => '',
            ],
            'billingAddress'  => [
                'contactName' => (string) $order['billing_address']['contact_name'],
                'city'        => (string) $order['billing_address']['city'],
                'country'     => (string) $order['billing_address']['country'],
                'address'     => (string) $order['billing_address']['address'],
                'zipCode'     => '',
            ],
            'basketItems'     => [
                [
                    'id'        => (string) $order['basket_items'][0]['id'],
                    'name'      => (string) $order['basket_items'][0]['name'],
                    'category1' => '',
                    'category2' => '',
                    'itemType'  => (string) $order['basket_items'][0]['item_type'],
                    'price'     => (float) $order['basket_items'][0]['price'],
                ],
            ],
            'paymentCard'     => [
                'cardHolderName' => $card->getHolderName(),
                'cardNumber'     => $card->getNumber(),
                'expireMonth'    => '12',
                'expireYear'     => '2026',
                'cvc'            => $card->getCvv(),
                'registerCard'   => 0,
            ],
            'paymentChannel'  => 'WEB',
            'installment'     => 1,
        ];

        yield 'without_sub_merchant' => [
            'account'  => $account,
            'order'    => $order,
            'expected' => $expectedBase,
        ];

        $accountWithSub  = AccountFactory::createIyzicoPosAccount('iyzico', 'key', 'secret', 'sub-key');
        $expectedWithSub = array_merge($expectedBase, ['subMerchantKey' => 'sub-key']);

        yield 'with_sub_merchant' => [
            'account'  => $accountWithSub,
            'order'    => $order,
            'expected' => $expectedWithSub,
        ];
    }

    public static function enrollmentCheckRequestDataProvider(): array
    {
        return [
            '3d_secure_with_card' => [
                'paymentModel'  => PosInterface::MODEL_3D_SECURE,
                'orderExtra'    => [],
                'expectedExtra' => [
                    'paymentChannel' => 'WEB',
                    'installment'    => 1,
                    'callbackUrl'    => 'https://example.com/success',
                ],
            ],
            '3d_host'             => [
                'paymentModel'  => PosInterface::MODEL_3D_HOST,
                'orderExtra'    => ['enabled_installments' => [1, 3, 6]],
                'expectedExtra' => [
                    'forceThreeDS'        => 1,
                    'enabledInstallments' => [1, 3, 6],
                    'callbackUrl'         => 'https://example.com/success',
                ],
            ],
        ];
    }

    private static function buildOrderStatic(): array
    {
        return [
            'id'               => 'order-001',
            'amount'           => 100.0,
            'currency'         => PosInterface::CURRENCY_TRY,
            'success_url'      => 'https://example.com/success',
            'fail_url'         => 'https://example.com/fail',
            'payment_channel'  => 'WEB',
            'buyer'            => [
                'id'                   => 'buyer-1',
                'name'                 => 'John',
                'surname'              => 'Doe',
                'identity_number'      => '11111111111',
                'email'                => 'john@example.com',
                'gsm_number'           => '+905350000000',
                'registration_address' => 'Address 1',
                'city'                 => 'Istanbul',
                'country'              => 'Turkey',
                'zip_code'             => '34000',
                'ip'                   => '127.0.0.1',
            ],
            'shipping_address' => [
                'contact_name' => 'John Doe',
                'city'         => 'Istanbul',
                'country'      => 'Turkey',
                'address'      => 'Shipping Address',
            ],
            'billing_address'  => [
                'contact_name' => 'John Doe',
                'city'         => 'Istanbul',
                'country'      => 'Turkey',
                'address'      => 'Billing Address',
            ],
            'basket_items'     => [
                [
                    'id'        => 'item-1',
                    'name'      => 'Product',
                    'item_type' => 'PHYSICAL',
                    'price'     => 100.0,
                ],
            ],
        ];
    }
}
