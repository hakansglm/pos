<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Functional;

use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\Event\Before3DFormHashCalculatedEvent;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateway\GarantiPos;
use Mews\Pos\PosInterface;
use Monolog\Test\TestCase;
use PHPUnit\Framework\Attributes\Depends;
use Symfony\Component\EventDispatcher\EventDispatcher;

class GarantiPosTest extends TestCase
{
    use PaymentTestTrait;

    private CreditCardInterface $card;

    private EventDispatcher $eventDispatcher;

    /** @var GarantiPos */
    private PosInterface $pos;

    protected function setUp(): void
    {
        parent::setUp();

        $config = require __DIR__.'/../../config/pos_test.php';

        $account = AccountFactory::createGarantiPosAccount(
            'garanti',
            (string) getenv('GARANTI_MERCHANT_ID'),
            (string) getenv('GARANTI_USERNAME'),
            (string) getenv('GARANTI_PASSWORD'),
            (string) getenv('GARANTI_TERMINAL_ID'),
            PosInterface::MODEL_3D_SECURE,
            (string) getenv('GARANTI_STORE_KEY'),
            (string) getenv('GARANTI_REFUND_USERNAME'),
            (string) getenv('GARANTI_REFUND_PASSWORD')
        );

        $this->eventDispatcher = new EventDispatcher();

        $this->pos = PosFactory::createPosGateway($account, $config, $this->eventDispatcher);

        $this->card = CreditCardFactory::createForGateway(
            $this->pos,
            '4282209004348015',
            '30',
            '08',
            '123',
            'John Doe',
            CreditCardInterface::CARD_TYPE_VISA
        );
    }

    public function testNonSecurePaymentSuccess(): array
    {
        $order = $this->createPaymentOrder(PosInterface::MODEL_NON_SECURE);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_PAY_AUTH, $requestDataPreparedEvent->getTxType());
                $this->assertCount(7, $requestDataPreparedEvent->getRequestData());
            }
        );

        $response = $this->pos->payment(
            PosInterface::MODEL_NON_SECURE,
            $order,
            PosInterface::TX_TYPE_PAY_AUTH,
            $this->card
        );

        $this->assertTrue($this->pos->isSuccess());

        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $response;
    }

    #[Depends('testNonSecurePaymentSuccess')]
    public function testStatusSuccess(array $lastResponse): array
    {
        $statusOrder = $this->createStatusOrder($this->pos::class, $lastResponse);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_STATUS, $requestDataPreparedEvent->getTxType());
                $this->assertCount(6, $requestDataPreparedEvent->getRequestData());
            }
        );

        $response = $this->pos->status($statusOrder);

        $this->assertTrue($this->pos->isSuccess());
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $lastResponse;
    }

    #[Depends('testNonSecurePaymentSuccess')]
    #[Depends('testStatusSuccess')]
    public function testCancelSuccess(array $lastResponse): void
    {
        $statusOrder = $this->createCancelOrder($this->pos::class, $lastResponse);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_CANCEL, $requestDataPreparedEvent->getTxType());
                $this->assertCount(6, $requestDataPreparedEvent->getRequestData());
            }
        );

        $response = $this->pos->cancel($statusOrder);

        $this->assertTrue($this->pos->isSuccess(), $response['error_message'] ?? '');
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);
    }

    public function testNonSecurePrePaymentSuccess(): array
    {
        $order = $this->createPaymentOrder(
            PosInterface::MODEL_NON_SECURE,
            PosInterface::CURRENCY_TRY,
            1.91,
            3
        );

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_PAY_PRE_AUTH, $requestDataPreparedEvent->getTxType());
                $this->assertCount(7, $requestDataPreparedEvent->getRequestData());
            }
        );

        $response = $this->pos->payment(
            PosInterface::MODEL_NON_SECURE,
            $order,
            PosInterface::TX_TYPE_PAY_PRE_AUTH,
            $this->card
        );

        $this->assertTrue($this->pos->isSuccess(), $response['error_message'] ?? '');
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $response;
    }

    #[Depends('testNonSecurePrePaymentSuccess')]
    public function testNonSecurePostPaymentSuccess(array $lastResponse): void
    {
        $order = $this->createPostPayOrder($this->pos::class, $lastResponse);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_PAY_POST_AUTH, $requestDataPreparedEvent->getTxType());
                $this->assertCount(6, $requestDataPreparedEvent->getRequestData());
            }
        );

        $response = $this->pos->payment(
            PosInterface::MODEL_NON_SECURE,
            $order,
            PosInterface::TX_TYPE_PAY_POST_AUTH
        );

        $this->assertTrue($this->pos->isSuccess());
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);
    }

    public function testGet3DFormData(): void
    {
        $order = $this->createPaymentOrder(PosInterface::MODEL_3D_SECURE);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            Before3DFormHashCalculatedEvent::class,
            function (Before3DFormHashCalculatedEvent $before3DFormHashCalculatedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertCount(19, $before3DFormHashCalculatedEvent->getFormInputs());
                $this->assertSame(PosInterface::TX_TYPE_PAY_AUTH, $before3DFormHashCalculatedEvent->getTxType());
                $formInputs = $before3DFormHashCalculatedEvent->getFormInputs();
                $formInputs['test_input'] = 'test_value';
                $before3DFormHashCalculatedEvent->setFormInputs($formInputs);
            }
        );

        $formData = $this->pos->get3DFormData(
            $order,
            PosInterface::MODEL_3D_PAY,
            PosInterface::TX_TYPE_PAY_AUTH,
            $this->card
        );
        $this->assertCount(21, $formData['inputs']);
        $this->assertArrayHasKey('test_input', $formData['inputs']);
        $this->assertTrue($eventIsThrown);
    }

    #[Depends('testNonSecurePaymentSuccess')]
    public function testOrderHistorySuccess(array $lastResponse): void
    {
        $historyOrder = $this->createOrderHistoryOrder($this->pos::class, $lastResponse);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_ORDER_HISTORY, $requestDataPreparedEvent->getTxType());
                $this->assertCount(6, $requestDataPreparedEvent->getRequestData());
            }
        );

        $response = $this->pos->orderHistory($historyOrder);

        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);
    }

    #[Depends('testStatusSuccess')]
    public function testHistorySuccess(): void
    {
        $historyOrder = $this->createHistoryOrder($this->pos::class, [], '127.0.0.1');

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_HISTORY, $requestDataPreparedEvent->getTxType());
                $this->assertCount(6, $requestDataPreparedEvent->getRequestData());
            }
        );

        $response = $this->pos->history($historyOrder);

        $this->assertIsArray($response);
        $this->assertTrue($eventIsThrown);
        $this->assertNotEmpty($response['transactions']);
    }

    public function testCustomQuery(): void
    {
        $customQuery = [
            'Version'     => 'v0.00',
            'Customer'    => [
                'IPAddress'    => '1.1.111.111',
                'EmailAddress' => 'Cem@cem.com',
            ],
            'Order'       => [
                'OrderID'     => 'SISTD5A61F1682E745B28871872383ABBEB1',
                'GroupID'     => '',
                'Description' => '',
            ],
            'Transaction' => [
                'Type'   => 'bininq',
                'Amount' => '1',
                'BINInq' => [
                    'Group'    => 'A',
                    'CardType' => 'A',
                ],
            ],
        ];

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_CUSTOM_QUERY, $requestDataPreparedEvent->getTxType());
                $this->assertCount(6, $requestDataPreparedEvent->getRequestData());
            }
        );

        $response = $this->pos->customQuery($customQuery);

        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertArrayHasKey('Transaction', $response);
        $this->assertTrue($eventIsThrown);
    }
}
