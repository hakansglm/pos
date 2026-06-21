<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Functional;

use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\PosInterface;
use Monolog\Test\TestCase;
use PHPUnit\Framework\Attributes\Depends;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * NOT: sadece Turkiye IPsiyle istek gonderince cevap alabiliyoruz.
 */
class KuveytPosTest extends TestCase
{
    use \Mews\Pos\Tests\Functional\PaymentTestTrait;

    private CreditCardInterface $card;

    private EventDispatcher $eventDispatcher;

    /** @var \Mews\Pos\Gateway\KuveytPos */
    private PosInterface $pos;

    protected function setUp(): void
    {
        parent::setUp();

        $config = require __DIR__.'/../../config/pos_test.php';

        $account = \Mews\Pos\Factory\AccountFactory::createBoaPosAccount(
            'kuveytpos',
            (string) getenv('KUVEYTPOS_MERCHANT_ID'),
            (string) getenv('KUVEYTPOS_USERNAME'),
            (string) getenv('KUVEYTPOS_CUSTOMER_NUMBER'),
            (string) getenv('KUVEYTPOS_PASSWORD'),
            PosInterface::MODEL_3D_SECURE
        );

        $this->eventDispatcher = new EventDispatcher();

        $this->pos        = PosFactory::createPosGateway($account, $config, $this->eventDispatcher);

        $this->card = CreditCardFactory::createForGateway(
            $this->pos,
            '5188961939192544',
            '29',
            '06',
            '588',
            'John Doe',
            CreditCardInterface::CARD_TYPE_MASTERCARD
        );
    }

    /**
     * @return void
     */
    public function testCreate3DFormData(): void
    {
        $order = $this->createPaymentOrder(PosInterface::MODEL_3D_SECURE);

        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_PAY_AUTH, $requestDataPreparedEvent->getTxType());
                $this->assertCount(22, $requestDataPreparedEvent->getRequestData());
            }
        );

        $formData = $this->pos->get3DFormData(
            $order,
            PosInterface::MODEL_3D_SECURE,
            PosInterface::TX_TYPE_PAY_AUTH,
            $this->card,
        );

        $this->assertIsString($formData);
        $this->assertNotEmpty($formData);
        $this->assertTrue($eventIsThrown);
    }

    public function testNonSecurePaymentSuccess(): array
    {
        $order = $this->createPaymentOrder(PosInterface::MODEL_NON_SECURE);

        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_PAY_AUTH, $requestDataPreparedEvent->getTxType());
                $this->assertCount(17, $requestDataPreparedEvent->getRequestData());
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
                $this->assertCount(1, $requestDataPreparedEvent->getRequestData());
            }
        );

        $response = $this->pos->cancel($statusOrder);

        $this->assertTrue($this->pos->isSuccess(), $response['error_message'] ?? '');
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $lastResponse;
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
                $this->assertCount(1, $requestDataPreparedEvent->getRequestData());
            }
        );

        $response = $this->pos->status($statusOrder);

        $this->assertTrue($this->pos->isSuccess());
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $lastResponse;
    }

    public function testNonSecurePaymentSuccessForRefundTest(): array
    {
        $order = $this->createPaymentOrder(PosInterface::MODEL_NON_SECURE);

        $response = $this->pos->payment(
            PosInterface::MODEL_NON_SECURE,
            $order,
            PosInterface::TX_TYPE_PAY_AUTH,
            $this->card
        );

        $this->assertTrue($this->pos->isSuccess(), $response['error_message'] ?? '');

        return $response;
    }

    #[Depends('testNonSecurePaymentSuccessForRefundTest')]
    public function testFullRefundFail(array $lastResponse): array
    {
        $refundOrder = $this->createRefundOrder($this->pos::class, $lastResponse);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_REFUND, $requestDataPreparedEvent->getTxType());
                $this->assertCount(1, $requestDataPreparedEvent->getRequestData());
            }
        );

        $response = $this->pos->refund($refundOrder);

        $this->assertFalse($this->pos->isSuccess());
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);
        $this->assertSame(
            'İade işlemi, satışla aynı gün içerisinde yapılamaz. İptal işlemi yapabilirsiniz.',
            $response['error_message']
        );

        return $lastResponse;
    }

    #[Depends('testNonSecurePaymentSuccessForRefundTest')]
    public function testPartialRefundSuccess(array $lastResponse): array
    {
        $refundOrder           = $this->createRefundOrder(
            $this->pos::class,
            $lastResponse,
            $lastResponse['amount'] - 3,
        );

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_REFUND_PARTIAL, $requestDataPreparedEvent->getTxType());
                $this->assertCount(1, $requestDataPreparedEvent->getRequestData());
            }
        );

        $response = $this->pos->refund($refundOrder);

        $this->assertTrue($this->pos->isSuccess(), $response['error_message'] ?? 'error');
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $lastResponse;
    }
}
