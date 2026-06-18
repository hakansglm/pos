<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Functional;

use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\IyzicoPos;
use Mews\Pos\PosInterface;
use Monolog\Test\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

class IyzicoPosTest extends TestCase
{
    use PaymentTestTrait;

    private CreditCardInterface $card;

    private EventDispatcher $eventDispatcher;

    /** @var IyzicoPos */
    private PosInterface $pos;

    protected function setUp(): void
    {
        parent::setUp();

        $config = require __DIR__.'/../../config/pos_test.php';

        $account = AccountFactory::createIyzicoPosAccount(
            'iyzico',
            (string) getenv('IYZICO_API_KEY'),
            (string) getenv('IYZICO_SECRET_KEY'),
        );

        $this->eventDispatcher = new EventDispatcher();

        $this->pos = PosFactory::createPosGateway($account, $config, $this->eventDispatcher);

        $this->card = CreditCardFactory::createForGateway(
            $this->pos,
            '4603450000000000',
            '26',
            '12',
            '000',
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

    public function testNonSecurePaymentWithUsdSuccess(): array
    {
        $usdCard = CreditCardFactory::createForGateway(
            $this->pos,
            '5400010000000004',
            '26',
            '12',
            '000',
            'John Doe',
            CreditCardInterface::CARD_TYPE_MASTERCARD
        );

        $order = $this->createPaymentOrder(PosInterface::MODEL_NON_SECURE, PosInterface::CURRENCY_USD, 10.01);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_PAY_AUTH, $requestDataPreparedEvent->getTxType());
            }
        );

        $response = $this->pos->payment(
            PosInterface::MODEL_NON_SECURE,
            $order,
            PosInterface::TX_TYPE_PAY_AUTH,
            $usdCard
        );

        $this->assertTrue($this->pos->isSuccess(), $response['error_message'] ?? '');
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $response;
    }

    public function testNonSecurePaymentFailWithIncorrectTotalAmount(): void
    {
        $order = $this->createPaymentOrder(PosInterface::MODEL_NON_SECURE);
        $order['amount'] -= 1;

        $response = $this->pos->payment(
            PosInterface::MODEL_NON_SECURE,
            $order,
            PosInterface::TX_TYPE_PAY_AUTH,
            $this->card
        );

        $this->assertFalse($this->pos->isSuccess());
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
    }

    /**
     * @depends testNonSecurePaymentSuccess
     */
    public function testStatusSuccess(array $lastResponse): array
    {
        $statusOrder = $this->createStatusOrder($this->pos::class, $lastResponse);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_STATUS, $requestDataPreparedEvent->getTxType());
            }
        );

        $response = $this->pos->status($statusOrder);

        $this->assertTrue($this->pos->isSuccess(), $response['error_message'] ?? '');
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $lastResponse;
    }

    /**
     * @depends testCancelSuccess
     */
    public function testStatusCanceledOrder(array $lastResponse): void
    {
        $statusOrder = $this->createStatusOrder($this->pos::class, $lastResponse);

        $response = $this->pos->status($statusOrder);

        $this->assertTrue($this->pos->isSuccess(), $response['error_message'] ?? '');
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
    }

    public function testStatusWithNonExistingOrder(): void
    {
        $lastResponse = [
            'order_id' => 'nonexistent-order-99999',
            'currency' => PosInterface::CURRENCY_TRY,
        ];
        $statusOrder = $this->createStatusOrder($this->pos::class, $lastResponse);

        $response = $this->pos->status($statusOrder);
        $this->assertFalse($this->pos->isSuccess());
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
    }

    /**
     * @depends testNonSecurePaymentSuccess
     * @depends testStatusSuccess
     */
    public function testCancelSuccess(array $lastResponse): array
    {
        $cancelOrder = $this->createCancelOrder($this->pos::class, $lastResponse);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_CANCEL, $requestDataPreparedEvent->getTxType());
            }
        );

        $response = $this->pos->cancel($cancelOrder);

        $this->assertTrue($this->pos->isSuccess(), $response['error_message'] ?? '');
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $lastResponse;
    }

    /**
     * @depends testCancelSuccess
     */
    public function testCancelForCanceledOrder(array $lastResponse): void
    {
        $cancelOrder = $this->createCancelOrder($this->pos::class, $lastResponse);

        $response = $this->pos->cancel($cancelOrder);
        $this->assertFalse($this->pos->isSuccess(), $response['error_message'] ?? '');
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
    }

    public function testNonSecurePrePaymentSuccess(): array
    {
        $order = $this->createPaymentOrder(PosInterface::MODEL_NON_SECURE);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_PAY_PRE_AUTH, $requestDataPreparedEvent->getTxType());
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

    /**
     * @depends testNonSecurePrePaymentSuccess
     */
    public function testNonSecurePostPaymentSuccess(array $lastResponse): array
    {
        $order = $this->createPostPayOrder(
            $this->pos::class,
            $lastResponse,
            $lastResponse['amount'] + 0.20
        );

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_PAY_POST_AUTH, $requestDataPreparedEvent->getTxType());
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

        return $response;
    }

    /**
     * @depends testNonSecurePostPaymentSuccess
     */
    public function testNonSecurePostPaymentFailWithAlreadyCompletedPostOrder(array $lastResponse): void
    {
        $order = $this->createPostPayOrder(
            $this->pos::class,
            $lastResponse,
            $lastResponse['amount']
        );

        $response = $this->pos->payment(
            PosInterface::MODEL_NON_SECURE,
            $order,
            PosInterface::TX_TYPE_PAY_POST_AUTH
        );

        $this->assertFalse($this->pos->isSuccess());
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
    }

    /**
     * @depends testNonSecurePostPaymentSuccess
     */
    public function testRefundSuccess(array $lastResponse): array
    {
        $refundOrder           = $this->createRefundOrder($this->pos::class, $lastResponse);
        $refundOrder['amount'] = 1.0;

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_REFUND_PARTIAL, $requestDataPreparedEvent->getTxType());
            }
        );

        $response = $this->pos->refund($refundOrder);

        $this->assertTrue($this->pos->isSuccess(), $response['error_message'] ?? '');
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $lastResponse;
    }

    /**
     * @depends testCancelSuccess
     */
    public function testRefundFailAlreadyCanceledOrder(array $lastResponse): void
    {
        $refundOrder           = $this->createRefundOrder($this->pos::class, $lastResponse);
        $response = $this->pos->refund($refundOrder);

        $this->assertFalse($this->pos->isSuccess());
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
    }


    /**
     * @depends testCancelSuccess
     */
    public function testOrderHistorySuccess(array $lastResponse): void
    {
        $historyOrder = $this->createOrderHistoryOrder($this->pos::class, $lastResponse);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_ORDER_HISTORY, $requestDataPreparedEvent->getTxType());
            }
        );

        $response = $this->pos->orderHistory($historyOrder);

        $this->assertTrue($this->pos->isSuccess(), $response['error_message'] ?? '');
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);
    }

    public function testOrderHistorySuccessNonExistentOrder(): void
    {
        $historyOrder = [
            'id' => 'nonexistent-order-99999',
        ];

        $response = $this->pos->orderHistory($historyOrder);

        $this->assertTrue($this->pos->isSuccess(), $response['error_message'] ?? '');
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
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
            }
        );

        $response = $this->pos->history($historyOrder);

        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);
    }

    public function testGet3DFormData3DSecure(): void
    {
        $order = $this->createPaymentOrder(PosInterface::MODEL_3D_SECURE);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_PAY_AUTH, $requestDataPreparedEvent->getTxType());
            }
        );

        $formData = $this->pos->get3DFormData(
            $order,
            PosInterface::MODEL_3D_SECURE,
            PosInterface::TX_TYPE_PAY_AUTH,
            $this->card
        );

        $this->assertIsString($formData);
        $this->assertNotEmpty($formData);
        $this->assertTrue($eventIsThrown);
    }

    public function testGet3DFormData3DHost(): void
    {
        $order = $this->createPaymentOrder(PosInterface::MODEL_3D_HOST);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_PAY_AUTH, $requestDataPreparedEvent->getTxType());
            }
        );

        $formData = $this->pos->get3DFormData(
            $order,
            PosInterface::MODEL_3D_HOST,
            PosInterface::TX_TYPE_PAY_AUTH,
        );

        $this->assertIsString($formData);
        $this->assertNotEmpty($formData);
        $this->assertTrue($eventIsThrown);
    }

    public function testCustomQuery(): void
    {
        // installment options query by BIN number
        $customQuery = [
            'price'     => 100.0,
            'binNumber' => '54308100',
        ];

        $apiUrl = 'https://sandbox-api.iyzipay.com/payment/iyzipos/installment';

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_CUSTOM_QUERY, $requestDataPreparedEvent->getTxType());
            }
        );

        $response = $this->pos->customQuery($customQuery, $apiUrl);

        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);
    }
}
