<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\Request\Mapper;

use InvalidArgumentException;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\Request\Mapper\AbstractRequestDataMapper;
use Mews\Pos\DataMapper\Request\Mapper\PayTrPosRequestDataMapper;
use Mews\Pos\DataMapper\Request\ValueFormatter\PayTrPosRequestValueFormatter;
use Mews\Pos\DataMapper\Request\ValueMapper\PayTrPosRequestValueMapper;
use Mews\Pos\Exception\NotImplementedException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Gateway\AkbankPos;
use Mews\Pos\Gateway\PayTrPos;
use Mews\Pos\Model\Account\PayTrPosAccount;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[CoversClass(PayTrPosRequestDataMapper::class)]
#[CoversClass(AbstractRequestDataMapper::class)]
class PayTrPosRequestDataMapperTest extends TestCase
{
    private PayTrPosAccount $account;

    private CreditCardInterface $card;

    /** @var CryptInterface & MockObject */
    private MockObject $cryptMock;

    /** @var EventDispatcherInterface & MockObject */
    private MockObject $dispatcherMock;

    private PayTrPosRequestDataMapper $requestDataMapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createPayTrPosAccount(
            'paytr',
            '123456',
            'wWwU8buJp6jo1r25',
            'YEUaNcdHXqyt7hjt',
        );

        $this->dispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->cryptMock      = $this->createMock(CryptInterface::class);

        $this->requestDataMapper = new PayTrPosRequestDataMapper(
            new PayTrPosRequestValueMapper(),
            new PayTrPosRequestValueFormatter(),
            $this->dispatcherMock,
            $this->cryptMock,
            PosInterface::LANG_TR
        );

        $this->card = CreditCardFactory::create(
            '4355084355084358',
            '30',
            '12',
            '000',
            'John Doe'
        );
    }

    public function testSupports(): void
    {
        $this->assertTrue($this->requestDataMapper::supports(PayTrPos::class));
        $this->assertFalse($this->requestDataMapper::supports(AkbankPos::class));
    }

    public function testIsTestModeDefaultsFalse(): void
    {
        $this->assertFalse($this->requestDataMapper->isTestMode());
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function testSetTestMode(bool $testMode): void
    {
        $this->requestDataMapper->setTestMode($testMode);
        $this->assertSame($testMode, $this->requestDataMapper->isTestMode());
    }

    #[DataProvider('create3DFormInitializeRequestDataProvider')]
    public function testCreate3DFormInitializeRequestData(
        bool  $testMode,
        array $order,
        array $expectedWithoutToken
    ): void {
        $this->requestDataMapper->setTestMode($testMode);

        $this->cryptMock->expects(self::once())
            ->method('createHash')
            ->with($this->account, $expectedWithoutToken)
            ->willReturn('mock-token');

        $actual = $this->requestDataMapper->create3DFormInitializeRequestData(
            $this->account,
            $order,
            PosInterface::MODEL_3D_HOST,
            PosInterface::TX_TYPE_PAY_AUTH
        );

        $this->assertSame(
            \array_merge($expectedWithoutToken, ['paytr_token' => 'mock-token']),
            $actual
        );
    }

    #[DataProvider('createNonSecurePaymentRequestDataProvider')]
    public function testCreateNonSecurePaymentRequestData(array $order, array $expectedWithoutToken): void
    {
        $this->cryptMock->expects(self::once())
            ->method('createHash')
            ->with($this->account, $expectedWithoutToken)
            ->willReturn('mock-token');

        $actual = $this->requestDataMapper->createNonSecurePaymentRequestData(
            $this->account,
            $order,
            PosInterface::TX_TYPE_PAY_AUTH,
            $this->card
        );

        $this->assertSame(
            \array_merge($expectedWithoutToken, ['paytr_token' => 'mock-token']),
            $actual
        );
    }

    public function testCreate3DPaymentRequestDataThrows(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->requestDataMapper->create3DPaymentRequestData($this->account, [], PosInterface::TX_TYPE_PAY_AUTH, []);
    }

    public function testCreateNonSecurePostAuthPaymentRequestDataThrows(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($this->account, []);
    }

    public function testCreateStatusRequestData(): void
    {
        $expectedWithoutToken = [
            'merchant_id'  => '123456',
            'merchant_oid' => 'order-999',
        ];

        $this->cryptMock->expects(self::once())
            ->method('createHash')
            ->with($this->account, $expectedWithoutToken)
            ->willReturn('mock-token');

        $actual = $this->requestDataMapper->createStatusRequestData(
            $this->account,
            ['id' => 'order-999']
        );

        $this->assertSame(
            \array_merge($expectedWithoutToken, ['paytr_token' => 'mock-token']),
            $actual
        );
    }

    public function testCreateCancelRequestDataThrows(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->requestDataMapper->createCancelRequestData($this->account, []);
    }

    public function testCreateOrderHistoryRequestDataThrows(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->requestDataMapper->createOrderHistoryRequestData($this->account, []);
    }

    #[DataProvider('createRefundRequestDataProvider')]
    public function testCreateRefundRequestData(array $order, array $expectedWithoutToken): void
    {
        $this->cryptMock->expects(self::once())
            ->method('createHash')
            ->with($this->account, $expectedWithoutToken)
            ->willReturn('mock-token');

        $actual = $this->requestDataMapper->createRefundRequestData(
            $this->account,
            $order,
            PosInterface::TX_TYPE_REFUND
        );

        $this->assertSame(
            \array_merge($expectedWithoutToken, ['paytr_token' => 'mock-token']),
            $actual
        );
    }

    public function testCreate3DFormDataForHostModel(): void
    {
        $extraData = ['token' => 'abc123'];

        $this->cryptMock->expects(self::never())
            ->method('createHash');

        $actual = $this->requestDataMapper->create3DFormData(
            $this->account,
            [],
            PosInterface::MODEL_3D_HOST,
            PosInterface::TX_TYPE_PAY_AUTH,
            'https://www.paytr.com/odeme/guvenli',
            null,
            $extraData
        );

        $this->assertSame('https://www.paytr.com/odeme/guvenli/abc123', $actual['gateway']);
        $this->assertSame('GET', $actual['method']);
        $this->assertSame([], $actual['inputs']);
    }

    public function testCreate3DFormDataForPayModel(): void
    {
        $order = [
            'id'              => 'order-123',
            'amount'          => 10.50,
            'ip'              => '127.0.0.1',
            'success_url'     => 'https://example.com/success',
            'fail_url'        => 'https://example.com/fail',
            'currency'        => PosInterface::CURRENCY_TRY,
            'lang'            => PosInterface::LANG_TR,
            'installment'     => 0,
            'buyer'           => ['email' => 'test@example.com', 'name' => 'John Doe', 'gsm_number' => '05001234567'],
            'billing_address' => ['address' => 'Test Sokak No:1 Istanbul'],
        ];

        $expectedInputsWithoutToken = [
            'merchant_id'       => '123456',
            'user_ip'           => '127.0.0.1',
            'merchant_oid'      => 'order-123',
            'email'             => 'test@example.com',
            'payment_amount'    => '10.50',
            'installment_count' => 0,
            'currency'          => 'TL',
            'non_3d'            => 0,
            'sync_mode'         => 0,
            'user_name'         => 'John Doe',
            'user_address'      => 'Test Sokak No:1 Istanbul',
            'user_phone'        => '05001234567',
            'test_mode'         => 0,
            'debug_on'          => 0,
            'client_lang'       => 'tr',
            'user_basket'       => 'W1sib3JkZXItMTIzIiwxMC41LDFdXQ==',
            'payment_type'      => 'card',
            'cc_owner'          => 'John Doe',
            'card_number'       => '4355084355084358',
            'expiry_month'      => '12',
            'expiry_year'       => '30',
            'cvv'               => '000',
            'merchant_ok_url'   => 'https://example.com/success',
            'merchant_fail_url' => 'https://example.com/fail',
        ];

        $this->cryptMock->expects(self::once())
            ->method('create3DHash')
            ->with($this->account, $expectedInputsWithoutToken)
            ->willReturn('3d-token');

        $actual = $this->requestDataMapper->create3DFormData(
            $this->account,
            $order,
            PosInterface::MODEL_3D_PAY,
            PosInterface::TX_TYPE_PAY_AUTH,
            'https://www.paytr.com/odeme',
            $this->card
        );

        $this->assertSame('https://www.paytr.com/odeme', $actual['gateway']);
        $this->assertSame('POST', $actual['method']);
        $this->assertSame(
            \array_merge($expectedInputsWithoutToken, ['paytr_token' => '3d-token']),
            $actual['inputs']
        );
    }

    public function testCreate3DFormDataForPayModelWithNullCardThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->requestDataMapper->create3DFormData(
            $this->account,
            ['id' => 'order-1', 'amount' => 1.0, 'ip' => '127.0.0.1', 'buyer' => [], 'billing_address' => []],
            PosInterface::MODEL_3D_PAY,
            PosInterface::TX_TYPE_PAY_AUTH,
            'https://www.paytr.com/odeme'
        );
    }

    public static function create3DFormInitializeRequestDataProvider(): array
    {
        $baseOrder = [
            'id'              => 'order-123',
            'amount'          => 10.50,
            'ip'              => '127.0.0.1',
            'success_url'     => 'https://example.com/success',
            'fail_url'        => 'https://example.com/fail',
            'currency'        => PosInterface::CURRENCY_TRY,
            'lang'            => PosInterface::LANG_TR,
            'buyer'           => ['email' => 'test@example.com', 'name' => 'John Doe', 'gsm_number' => '05001234567'],
            'billing_address' => ['address' => 'Test Sokak No:1 Istanbul'],
        ];

        return [
            // installment=0 → no_installment=1, max_installment=0
            'no_installment_test_mode_off'  => [
                'testMode'             => false,
                'order'                => \array_merge($baseOrder, ['installment' => 0]),
                'expectedWithoutToken' => [
                    'merchant_id'       => '123456',
                    'user_ip'           => '127.0.0.1',
                    'merchant_oid'      => 'order-123',
                    'email'             => 'test@example.com',
                    'payment_amount'    => 1050,
                    'currency'          => 'TL',
                    'no_installment'    => 1,
                    'max_installment'   => 0,
                    'test_mode'         => 0,
                    'lang'              => 'tr',
                    'user_basket'       => 'W1sib3JkZXItMTIzIiwxMC41LDFdXQ==',
                    'merchant_ok_url'   => 'https://example.com/success',
                    'merchant_fail_url' => 'https://example.com/fail',
                    'user_name'         => 'John Doe',
                    'user_address'      => 'Test Sokak No:1 Istanbul',
                    'user_phone'        => '05001234567',
                ],
            ],
            // installment=3 → no_installment=0, max_installment=3, test_mode=1
            'with_installment_test_mode_on' => [
                'testMode'             => true,
                'order'                => \array_merge($baseOrder, ['installment' => 3]),
                'expectedWithoutToken' => [
                    'merchant_id'       => '123456',
                    'user_ip'           => '127.0.0.1',
                    'merchant_oid'      => 'order-123',
                    'email'             => 'test@example.com',
                    'payment_amount'    => 1050,
                    'currency'          => 'TL',
                    'no_installment'    => 0,
                    'max_installment'   => 3,
                    'test_mode'         => 1,
                    'lang'              => 'tr',
                    'user_basket'       => 'W1sib3JkZXItMTIzIiwxMC41LDFdXQ==',
                    'merchant_ok_url'   => 'https://example.com/success',
                    'merchant_fail_url' => 'https://example.com/fail',
                    'user_name'         => 'John Doe',
                    'user_address'      => 'Test Sokak No:1 Istanbul',
                    'user_phone'        => '05001234567',
                ],
            ],
            // basket_items provided → basket built from items, not order id/amount
            'with_basket_items'             => [
                'testMode'             => false,
                'order'                => \array_merge($baseOrder, [
                    'installment'  => 0,
                    'basket_items' => [
                        ['name' => 'Binocular', 'item_count' => 1, 'price' => 0.3],
                        ['name' => 'Game code', 'item_count' => 1, 'price' => 9.71],
                    ],
                ]),
                'expectedWithoutToken' => [
                    'merchant_id'       => '123456',
                    'user_ip'           => '127.0.0.1',
                    'merchant_oid'      => 'order-123',
                    'email'             => 'test@example.com',
                    'payment_amount'    => 1050,
                    'currency'          => 'TL',
                    'no_installment'    => 1,
                    'max_installment'   => 0,
                    'test_mode'         => 0,
                    'lang'              => 'tr',
                    'user_basket'       => 'W1siQmlub2N1bGFyIiwwLjMsMV0sWyJHYW1lIGNvZGUiLDkuNzEsMV1d',
                    'merchant_ok_url'   => 'https://example.com/success',
                    'merchant_fail_url' => 'https://example.com/fail',
                    'user_name'         => 'John Doe',
                    'user_address'      => 'Test Sokak No:1 Istanbul',
                    'user_phone'        => '05001234567',
                ],
            ],
        ];
    }

    public static function createNonSecurePaymentRequestDataProvider(): array
    {
        return [
            'no_installment'   => [
                'order'                => [
                    'id'              => 'order-456',
                    'amount'          => 50.00,
                    'ip'              => '127.0.0.1',
                    'currency'        => PosInterface::CURRENCY_TRY,
                    'lang'            => PosInterface::LANG_TR,
                    'installment'     => 0,
                    'buyer'           => ['email' => 'test@example.com', 'name' => 'John Doe', 'gsm_number' => '05001234567'],
                    'billing_address' => ['address' => 'Test Sokak No:1 Istanbul'],
                ],
                'expectedWithoutToken' => [
                    'merchant_id'       => '123456',
                    'user_ip'           => '127.0.0.1',
                    'merchant_oid'      => 'order-456',
                    'email'             => 'test@example.com',
                    'payment_amount'    => '50.00',
                    'installment_count' => 0,
                    'currency'          => 'TL',
                    'non_3d'            => 1,
                    'sync_mode'         => 1,
                    'user_name'         => 'John Doe',
                    'user_address'      => 'Test Sokak No:1 Istanbul',
                    'user_phone'        => '05001234567',
                    'test_mode'         => 0,
                    'debug_on'          => 0,
                    'client_lang'       => 'tr',
                    'user_basket'       => 'W1sib3JkZXItNDU2Iiw1MCwxXV0=',
                    'payment_type'      => 'card',
                    'cc_owner'          => 'John Doe',
                    'card_number'       => '4355084355084358',
                    'expiry_month'      => '12',
                    'expiry_year'       => '30',
                    'cvv'               => '000',
                ],
            ],
            'with_installment' => [
                'order'                => [
                    'id'              => 'order-456',
                    'amount'          => 50.00,
                    'ip'              => '127.0.0.1',
                    'currency'        => PosInterface::CURRENCY_TRY,
                    'lang'            => PosInterface::LANG_TR,
                    'installment'     => 3,
                    'buyer'           => ['email' => 'test@example.com', 'name' => 'John Doe', 'gsm_number' => '05001234567'],
                    'billing_address' => ['address' => 'Test Sokak No:1 Istanbul'],
                ],
                'expectedWithoutToken' => [
                    'merchant_id'       => '123456',
                    'user_ip'           => '127.0.0.1',
                    'merchant_oid'      => 'order-456',
                    'email'             => 'test@example.com',
                    'payment_amount'    => '50.00',
                    'installment_count' => 3,
                    'currency'          => 'TL',
                    'non_3d'            => 1,
                    'sync_mode'         => 1,
                    'user_name'         => 'John Doe',
                    'user_address'      => 'Test Sokak No:1 Istanbul',
                    'user_phone'        => '05001234567',
                    'test_mode'         => 0,
                    'debug_on'          => 0,
                    'client_lang'       => 'tr',
                    'user_basket'       => 'W1sib3JkZXItNDU2Iiw1MCwxXV0=',
                    'payment_type'      => 'card',
                    'cc_owner'          => 'John Doe',
                    'card_number'       => '4355084355084358',
                    'expiry_month'      => '12',
                    'expiry_year'       => '30',
                    'cvv'               => '000',
                ],
            ],
        ];
    }

    public static function createRefundRequestDataProvider(): array
    {
        return [
            'full_refund' => [
                'order'                => ['id' => 'order-789', 'amount' => 10.50],
                'expectedWithoutToken' => [
                    'merchant_id'   => '123456',
                    'merchant_oid'  => 'order-789',
                    'return_amount' => '10.50',
                ],
            ],
        ];
    }
}
