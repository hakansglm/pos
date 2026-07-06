<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Functional\Pos;

use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateway\ParamPos;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\PosInterface;
use Monolog\Test\TestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Depends;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[CoversNothing]
class ParamPosTest extends TestCase
{
    use PaymentTestTrait;

    private CreditCardInterface $card;

    private EventDispatcher $eventDispatcher;

    /** @var ParamPos */
    private PosInterface $pos;

    protected function setUp(): void
    {
        parent::setUp();

        $config = require __DIR__.'/../../../config/pos_test.php';

        $account = AccountFactory::createParamPosAccount(
            'param-pos',
            (string) getenv('PARAMPOS_MERCHANT_ID'),
            (string) getenv('PARAMPOS_USERNAME'),
            (string) getenv('PARAMPOS_PASSWORD'),
            (string) getenv('PARAMPOS_CLIENT_SECRET')
        );

        $this->eventDispatcher = new EventDispatcher();

        $this->pos = PosFactory::create($account, $config['banks'][$account->getBankName()], $this->eventDispatcher);

        $this->card = CreditCardFactory::createForGateway(
            $this->pos,
            '5456165456165454',
            '26',
            '12',
            '000',
            'John Doe'
        );
    }

    public function testNonSecurePaymentSuccess(): array
    {
        $card = CreditCardFactory::createForGateway(
            $this->pos,
            '5818775818772285',
            '26',
            '12',
            '001',
            'John Doe'
        );
        $order = $this->createPaymentOrder(PosInterface::MODEL_NON_SECURE);

        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_PAY_AUTH, $requestDataPreparedEvent->getTxType());
                $this->assertCount(1, $requestDataPreparedEvent->getRequestData()['soap:Body']);
            }
        );

        $response = $this->pos->payment(
            PosInterface::MODEL_NON_SECURE,
            $order,
            PosInterface::TX_TYPE_PAY_AUTH,
            $card
        );

        $this->assertTrue($this->pos->isSuccess(), $response['error_message'] ?? 'hata');
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $response;
    }

    public function testNonSecureForeignCurrencyPaymentSuccess(): array
    {
        $order = $this->createPaymentOrder(PosInterface::MODEL_NON_SECURE);
        $order['currency'] = PosInterface::CURRENCY_USD;

        $card = CreditCardFactory::createForGateway(
            $this->pos,
            '4546711234567894',
            '26',
            '12',
            '000',
            'John Doe'
        );

        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_PAY_AUTH, $requestDataPreparedEvent->getTxType());
                $this->assertCount(1, $requestDataPreparedEvent->getRequestData()['soap:Body']);
            }
        );

        $response = $this->pos->payment(
            PosInterface::MODEL_NON_SECURE,
            $order,
            PosInterface::TX_TYPE_PAY_AUTH,
            $card
        );

        $this->assertTrue($this->pos->isSuccess(), $response['error_message'] ?? 'hata');
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $response;
    }

    public function testNonSecurePaymentWithInstallment(): array
    {
        $order = $this->createPaymentOrder(PosInterface::MODEL_NON_SECURE);
        $order['installment'] = 2;

        $response = $this->pos->payment(
            PosInterface::MODEL_NON_SECURE,
            $order,
            PosInterface::TX_TYPE_PAY_AUTH,
            $this->card
        );

        $this->assertTrue($this->pos->isSuccess(), $response['error_message'] ?? 'hata');
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);

        return $response;
    }

    public function testNonSecurePrePaymentSuccess(): array
    {
        $order = $this->createPaymentOrder(PosInterface::MODEL_NON_SECURE);

        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_PAY_PRE_AUTH, $requestDataPreparedEvent->getTxType());
                $this->assertCount(1, $requestDataPreparedEvent->getRequestData()['soap:Body']);
            }
        );

        $response = $this->pos->payment(
            PosInterface::MODEL_NON_SECURE,
            $order,
            PosInterface::TX_TYPE_PAY_PRE_AUTH,
            $this->card
        );

        $this->assertTrue($this->pos->isSuccess(), $response['error_message'] ?? 'hata');
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $response;
    }

    #[Depends('testNonSecurePrePaymentSuccess')]
    public function testNonSecurePostPaymentSuccess(array $lastResponse): array
    {
        $order = $this->createPostPayOrder($this->pos::class, $lastResponse);

        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_PAY_POST_AUTH, $requestDataPreparedEvent->getTxType());
                $this->assertCount(1, $requestDataPreparedEvent->getRequestData()['soap:Body']);
            }
        );

        $response = $this->pos->payment(
            PosInterface::MODEL_NON_SECURE,
            $order,
            PosInterface::TX_TYPE_PAY_POST_AUTH,
            $this->card
        );

        $this->assertTrue($this->pos->isSuccess(), $response['error_message'] ?? 'hata');
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
                $this->assertCount(1, $requestDataPreparedEvent->getRequestData()['soap:Body']);
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
        $cancelOrder = $this->createCancelOrder($this->pos::class, $lastResponse);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_CANCEL, $requestDataPreparedEvent->getTxType());
                $this->assertCount(1, $requestDataPreparedEvent->getRequestData()['soap:Body']);
            }
        );

        $response = $this->pos->cancel($cancelOrder);

        $this->assertTrue($this->pos->isSuccess());
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $lastResponse;
    }

    public function testCancelPrePay(): void
    {
        $order = $this->createPaymentOrder(PosInterface::MODEL_NON_SECURE);

        $lastResponse = $this->pos->payment(
            PosInterface::MODEL_NON_SECURE,
            $order,
            PosInterface::TX_TYPE_PAY_PRE_AUTH,
            $this->card
        );

        $this->assertTrue($this->pos->isSuccess(), $response['error_message'] ?? 'hata');

        $cancelOrder = $this->createCancelOrder($this->pos::class, $lastResponse);

        $this->pos->cancel($cancelOrder);

        $this->assertTrue($this->pos->isSuccess());
    }

    public function testGet3DFormData(): void
    {
        $order = $this->createPaymentOrder(PosInterface::MODEL_3D_SECURE);
        $card = CreditCardFactory::createForGateway(
            $this->pos,
            '5818775818772285',
            '26',
            '12',
            '001',
            'John Doe'
        );

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertCount(1, $requestDataPreparedEvent->getRequestData()['soap:Body']);
                $this->assertSame(PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD, $requestDataPreparedEvent->getTxType());
            }
        );
        $formData = $this->pos->get3DFormData(
            $order,
            PosInterface::MODEL_3D_SECURE,
            PosInterface::TX_TYPE_PAY_AUTH,
            $card
        );

        $this->assertIsString($formData);
        $this->assertTrue($eventIsThrown);
    }

    public function testGet3DFormDataForeignCurrency(): void
    {
        $order = $this->createPaymentOrder(PosInterface::MODEL_3D_SECURE);
        $order['currency'] = PosInterface::CURRENCY_USD;
        $card = CreditCardFactory::createForGateway(
            $this->pos,
            '4546711234567894',
            '26',
            '12',
            '000',
            'John Doe'
        );

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertCount(1, $requestDataPreparedEvent->getRequestData()['soap:Body']);
                $this->assertSame(PosInterface::TX_TYPE_PAY_AUTH, $requestDataPreparedEvent->getTxType());
            }
        );
        $formData = $this->pos->get3DFormData(
            $order,
            PosInterface::MODEL_3D_SECURE,
            PosInterface::TX_TYPE_PAY_AUTH,
            $card
        );

        $this->assertIsArray($formData);
        $this->assertNotEmpty($formData['gateway']);
        $this->assertTrue($eventIsThrown);
    }

    public function testRefund(): array
    {
        $order = $this->createPaymentOrder(PosInterface::MODEL_NON_SECURE);

        $lastResponse = $this->pos->payment(
            PosInterface::MODEL_NON_SECURE,
            $order,
            PosInterface::TX_TYPE_PAY_AUTH,
            $this->card
        );

        $this->assertTrue($this->pos->isSuccess());
        $this->assertTrue($this->pos->isSuccess(), $lastResponse['error_message'] ?? 'hata');

        $refundOrder = $this->createRefundOrder($this->pos::class, $lastResponse);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosInterface::TX_TYPE_REFUND, $requestDataPreparedEvent->getTxType());
                $this->assertCount(1, $requestDataPreparedEvent->getRequestData()['soap:Body']);
            }
        );

        $response = $this->pos->refund($refundOrder);

        // fails with error: Failed, Bu işlem geri alınamaz, lüften asıl işlemi iptal edin.
        $this->assertFalse($this->pos->isSuccess(), $response['error_message'] ?? 'hata');
        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);

        return $lastResponse;
    }
}
