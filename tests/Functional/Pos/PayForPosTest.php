<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Functional\Pos;

use Mews\Pos\Event\Before3DFormHashCalculatedEvent;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateway\PayForPos;
use Mews\Pos\Model\Account\PayForPosAccount;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\PosInterface;
use Monolog\Test\TestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[CoversNothing]
class PayForPosTest extends TestCase
{
    use PaymentTestTrait;

    private CreditCardInterface $card;

    private EventDispatcher $eventDispatcher;

    /** @var PayForPos */
    private PosInterface $pos;

    protected function setUp(): void
    {
        parent::setUp();

        $config = require __DIR__.'/../../../config/pos_test.php';

        $account = AccountFactory::createPayForPosAccount(
            'qnbfinansbank-payfor',
            (string) getenv('FINANSBANK_MERCHANT_ID'),
            (string) getenv('FINANSBANK_USERNAME'),
            (string) getenv('FINANSBANK_PASSWORD'),
            (string) getenv('FINANSBANK_STORE_KEY'),
            PayForPosAccount::MBR_ID_FINANSBANK
        );
        $this->eventDispatcher = new EventDispatcher();

        $this->pos = PosFactory::create($account, $config['banks'][$account->getBankName()], $this->eventDispatcher);

        $this->card = CreditCardFactory::createForGateway(
            $this->pos,
            '4155650100416111',
            '28',
            '1',
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
                $this->assertCount(16, $requestDataPreparedEvent->getRequestData());
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

    #[Depends('testNonSecurePostPaymentSuccess')]
    public function testStatusSuccess(array $lastResponse): array
    {
        $statusOrder = $this->createStatusOrder($this->pos::class, $lastResponse);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_STATUS, $requestDataPreparedEvent->getTxType());
                $this->assertCount(8, $requestDataPreparedEvent->getRequestData());
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
    public function testCancelSuccess(array $lastResponse): array
    {
        $statusOrder = $this->createCancelOrder($this->pos::class, $lastResponse);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_CANCEL, $requestDataPreparedEvent->getTxType());
                $this->assertCount(9, $requestDataPreparedEvent->getRequestData());
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
                $this->assertCount(8, $requestDataPreparedEvent->getRequestData());
            }
        );

        $response = $this->pos->orderHistory($historyOrder);

        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);
    }

    public function testNonSecurePrePaymentSuccess(): array
    {
        $order = $this->createPaymentOrder(
            PosInterface::MODEL_NON_SECURE,
            PosInterface::CURRENCY_TRY,
            2.01,
            3
        );

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_PAY_PRE_AUTH, $requestDataPreparedEvent->getTxType());
                $this->assertCount(16, $requestDataPreparedEvent->getRequestData());
            }
        );

        $response = $this->pos->payment(
            PosInterface::MODEL_NON_SECURE,
            $order,
            PosInterface::TX_TYPE_PAY_PRE_AUTH,
            $this->card
        );

        $this->assertTrue($this->pos->isSuccess());
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $response;
    }

    #[Depends('testNonSecurePrePaymentSuccess')]
    public function testNonSecurePostPaymentSuccess(array $lastResponse): array
    {
        $order = $this->createPostPayOrder($this->pos::class, $lastResponse);
        $order['amount'] += .02;
        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_PAY_POST_AUTH, $requestDataPreparedEvent->getTxType());
                $this->assertCount(10, $requestDataPreparedEvent->getRequestData());
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

        return $response;
    }

    public function testGet3DFormData(): void
    {
        $order = $this->createPaymentOrder(PosInterface::MODEL_3D_SECURE);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            Before3DFormHashCalculatedEvent::class,
            function (Before3DFormHashCalculatedEvent $before3DFormHashCalculatedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertCount(17, $before3DFormHashCalculatedEvent->getFormInputs());
                $this->assertSame(PosInterface::TX_TYPE_PAY_AUTH, $before3DFormHashCalculatedEvent->getTxType());
                $formInputs = $before3DFormHashCalculatedEvent->getFormInputs();
                $formInputs['test_input'] = 'test_value';
                $before3DFormHashCalculatedEvent->setFormInputs($formInputs);
            }
        );

        $formData = $this->pos->get3DFormData(
            $order,
            PosInterface::MODEL_3D_SECURE,
            PosInterface::TX_TYPE_PAY_AUTH,
            $this->card
        );
        $this->assertCount(19, $formData['inputs']);
        $this->assertArrayHasKey('test_input', $formData['inputs']);
        $this->assertTrue($eventIsThrown);
    }

    #[TestWith([PosInterface::MODEL_3D_SECURE])]
    #[TestWith([PosInterface::MODEL_3D_PAY])]
    public function testGet3DFormDataAsHtml(string $paymentModel): void
    {
        $order = $this->createPaymentOrder($paymentModel);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD, $requestDataPreparedEvent->getTxType());
                $this->assertCount(18, $requestDataPreparedEvent->getRequestData());
            }
        );

        $formData = $this->pos->get3DFormData(
            $order,
            $paymentModel,
            PosInterface::TX_TYPE_PAY_AUTH,
            PosInterface::MODEL_3D_HOST === $paymentModel ? null : $this->card,
            false,
            PosInterface::FORM_FORMAT_HTML
        );

        $this->assertIsString($formData);
        $this->assertNotEmpty($formData);
        $this->assertTrue($eventIsThrown);
    }

    #[Depends('testNonSecurePostPaymentSuccess')]
    public function testRefundFail(array $lastResponse): array
    {
        $refundOrder = $this->createRefundOrder($this->pos::class, $lastResponse);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_REFUND, $requestDataPreparedEvent->getTxType());
                $this->assertCount(10, $requestDataPreparedEvent->getRequestData());
            }
        );

        $response = $this->pos->refund($refundOrder);

        $this->assertFalse($this->pos->isSuccess());
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertSame('V014', $response['proc_return_code']);
        $this->assertTrue($eventIsThrown);

        return $lastResponse;
    }
}
