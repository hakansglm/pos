<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Functional;

use Mews\Pos\Gateway\AkbankPos;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\PosInterface;
use Monolog\Test\TestCase;
use PHPUnit\Framework\Attributes\Depends;
use Symfony\Component\EventDispatcher\EventDispatcher;

class AkbankPosTest extends TestCase
{
    use PaymentTestTrait;

    private CreditCardInterface $card;

    private EventDispatcher $eventDispatcher;

    /** @var AkbankPos */
    private PosInterface $pos;

    /** @var AkbankPos */
    private PosInterface $recurringPos;

    protected function setUp(): void
    {
        parent::setUp();

        $config = require __DIR__.'/../../config/pos_test.php';

        $account = AccountFactory::createAkbankPosAccount(
            'akbank-pos',
            (string) getenv('AKBANKPOS_MERCHANT_ID'),
            (string) getenv('AKBANKPOS_TERMINAL_ID'),
            (string) getenv('AKBANKPOS_API_KEY'),
        );

        $recurringAccount = AccountFactory::createAkbankPosAccount(
            'akbank-pos',
            (string) getenv('AKBANKPOS_RECURRING_MERCHANT_ID'),
            (string) getenv('AKBANKPOS_RECURRING_TERMINAL_ID'),
            (string) getenv('AKBANKPOS_RECURRING_API_KEY'),
        );

        $this->eventDispatcher = new EventDispatcher();

        $this->pos          = PosFactory::createPosGateway($account, $config, $this->eventDispatcher);
        $this->recurringPos = PosFactory::createPosGateway($recurringAccount, $config, $this->eventDispatcher);

        $this->card = CreditCardFactory::createForGateway(
            $this->pos,
            '4355093000315232',
            '28',
            '01',
            '264',
        );
    }

    public function testNonSecurePaymentSuccess(): array
    {
        $order = $this->createPaymentOrder(PosInterface::MODEL_NON_SECURE);

        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_PAY_AUTH, $requestDataPreparedEvent->getTxType());
                $this->assertCount(9, $requestDataPreparedEvent->getRequestData());
            }
        );

        $response = $this->pos->payment(
            PosInterface::MODEL_NON_SECURE,
            $order,
            PosInterface::TX_TYPE_PAY_AUTH,
            $this->card
        );

        $this->assertTrue($this->pos->isSuccess(), $response['error_message'] ?? '');

        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $response;
    }

    #[Depends('testNonSecurePaymentSuccess')]
    public function testCancelSuccess(array $lastResponse): array
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

        return $lastResponse;
    }

    #[Depends('testCancelSuccess')]
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

        $this->assertTrue($this->pos->isSuccess(), $response['error_message'] ?? '');
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);
    }

    public function testHistorySuccess(): void
    {
        $historyOrder = $this->createHistoryOrder($this->pos::class, [], '127.0.0.1');

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_HISTORY, $requestDataPreparedEvent->getTxType());
                $this->assertCount(3, $requestDataPreparedEvent->getRequestData());
            }
        );

        $response = $this->pos->history($historyOrder);

        $this->assertIsArray($response);
        $this->assertTrue($eventIsThrown);
        $this->assertNotEmpty($response['transactions']);
    }

    public function testNonSecurePrePaymentSuccess(): array
    {
        $order = $this->createPaymentOrder(
            PosInterface::MODEL_NON_SECURE,
            PosInterface::CURRENCY_TRY,
            30.0,
            3
        );

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_PAY_PRE_AUTH, $requestDataPreparedEvent->getTxType());
                $this->assertCount(9, $requestDataPreparedEvent->getRequestData());
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
    public function testNonSecurePostPaymentSuccess(array $lastResponse): array
    {
        $order         = $this->createPostPayOrder($this->pos::class, $lastResponse);
        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_PAY_POST_AUTH, $requestDataPreparedEvent->getTxType());
                $this->assertCount(8, $requestDataPreparedEvent->getRequestData());
            }
        );

        $response = $this->pos->payment(
            PosInterface::MODEL_NON_SECURE,
            $order,
            PosInterface::TX_TYPE_PAY_POST_AUTH
        );

        $this->assertTrue($this->pos->isSuccess(), $response['error_message'] ?? '');
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $lastResponse;
    }

    #[Depends('testNonSecurePostPaymentSuccess')]
    public function testRefundSuccess(array $lastResponse): array
    {
        $refundOrder = $this->createRefundOrder($this->pos::class, $lastResponse);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_REFUND, $requestDataPreparedEvent->getTxType());
                $this->assertCount(7, $requestDataPreparedEvent->getRequestData());
            }
        );

        $response = $this->pos->refund($refundOrder);

        $this->assertTrue($this->pos->isSuccess(), $response['error_message'] ?? '');
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $lastResponse;
    }

    public function testNonSecurePaymentRecurringSuccess(): array
    {
        $order = $this->createPaymentOrder(
            PosInterface::MODEL_NON_SECURE,
            PosInterface::CURRENCY_TRY,
            5,
            3,
            true,
        );

        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_PAY_AUTH, $requestDataPreparedEvent->getTxType());
                $this->assertCount(10, $requestDataPreparedEvent->getRequestData());
            }
        );

        $response = $this->recurringPos->payment(
            PosInterface::MODEL_NON_SECURE,
            $order,
            PosInterface::TX_TYPE_PAY_AUTH,
            $this->card
        );

        $this->assertTrue($this->recurringPos->isSuccess(), $response['error_message'] ?? '');
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $response;
    }

    #[Depends('testNonSecurePaymentRecurringSuccess')]
    public function testCancelRecurringOrder(array $lastResponse): array
    {
        $statusOrder = [
            'recurring_id'                    => $lastResponse['recurring_id'],
            'recurringOrderInstallmentNumber' => 1,
        ];

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_CANCEL, $requestDataPreparedEvent->getTxType());
                $this->assertCount(7, $requestDataPreparedEvent->getRequestData());
            }
        );

        $response = $this->recurringPos->cancel($statusOrder);

        $this->assertTrue($this->recurringPos->isSuccess(), $response['error_message'] ?? '');
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $response;
    }

    #[Depends('testNonSecurePaymentRecurringSuccess')]
    public function testCancelPendingRecurringOrder(array $lastResponse): array
    {
        $statusOrder = [
            'recurring_id'                    => $lastResponse['recurring_id'],
            'recurringOrderInstallmentNumber' => 2,
            'recurring_payment_is_pending'    => true,
        ];

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_CANCEL, $requestDataPreparedEvent->getTxType());
                $this->assertCount(7, $requestDataPreparedEvent->getRequestData());
            }
        );

        $response = $this->recurringPos->cancel($statusOrder);

        $this->assertTrue($this->recurringPos->isSuccess(), $response['error_message'] ?? '');
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $response;
    }

    #[Depends('testCancelPendingRecurringOrder')]
    public function testCancelAllPendingRecurringOrder(array $lastResponse): array
    {
        $statusOrder = [
            'recurring_id'                    => $lastResponse['recurring_id'],
            'recurringOrderInstallmentNumber' => null,
            'recurring_payment_is_pending'    => true,
        ];

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_CANCEL, $requestDataPreparedEvent->getTxType());
                $this->assertCount(6, $requestDataPreparedEvent->getRequestData());
            }
        );

        $response = $this->recurringPos->cancel($statusOrder);

        $this->assertTrue($this->recurringPos->isSuccess(), $response['error_message'] ?? '');
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $response;
    }

    #[Depends('testCancelRecurringOrder')]
    public function testRecurringOrderHistorySuccess(array $lastResponse): void
    {
        $historyOrder = $this->createOrderHistoryOrder($this->recurringPos::class, $lastResponse);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_ORDER_HISTORY, $requestDataPreparedEvent->getTxType());
                $this->assertCount(6, $requestDataPreparedEvent->getRequestData());
            }
        );

        $response = $this->recurringPos->orderHistory($historyOrder);

        $this->assertTrue($this->recurringPos->isSuccess(), $response['error_message'] ?? '');
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);
    }

    #[Depends('testCancelRecurringOrder')]
    public function testRecurringHistorySuccess(): void
    {
        $historyOrder = $this->createHistoryOrder($this->pos::class, [], '127.0.0.1');

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_HISTORY, $requestDataPreparedEvent->getTxType());
                $this->assertCount(3, $requestDataPreparedEvent->getRequestData());
            }
        );

        $response = $this->recurringPos->history($historyOrder);

        $this->assertTrue($this->recurringPos->isSuccess(), $response['error_message'] ?? '');
        $this->assertIsArray($response);
        $this->assertTrue($eventIsThrown);
        $this->assertNotEmpty($response['transactions']);
    }

    public function testCustomQuery(): void
    {
        $customQuery = [
            'txnCode'     => '1020',
            'order'       => [
                'orderTrackId' => 'ae15a6c8-467e-45de-b24c-b98821a42667',
            ],
            'payByLink'   => [
                'linkTxnCode'       => '3000',
                'linkTransferType'  => 'SMS',
                'mobilePhoneNumber' => '5321234567',
            ],
            'transaction' => [
                'amount'       => 1.00,
                'currencyCode' => 949,
                'motoInd'      => 0,
                'installCount' => 1,
            ],
        ];

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_CUSTOM_QUERY, $requestDataPreparedEvent->getTxType());
                $this->assertCount(8, $requestDataPreparedEvent->getRequestData());
            }
        );

        $response = $this->pos->customQuery($customQuery);

        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertArrayHasKey('responseCode', $response);
        $this->assertTrue($eventIsThrown);
    }
}
