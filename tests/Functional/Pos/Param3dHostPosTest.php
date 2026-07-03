<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Functional\Pos;

use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateway\Param3DHostPos;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\PosInterface;
use Monolog\Test\TestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[CoversNothing]
class Param3dHostPosTest extends TestCase
{
    use PaymentTestTrait;

    private CreditCardInterface $card;

    private EventDispatcher $eventDispatcher;

    /** @var Param3DHostPos */
    private PosInterface $pos;

    protected function setUp(): void
    {
        parent::setUp();

        $config = require __DIR__.'/../../../config/pos_test.php';

        $account = AccountFactory::createParamPosAccount(
            'param-3d-host-pos',
            (string) getenv('PARAMPOS_3DHOST_MERCHANT_ID'),
            (string) getenv('PARAMPOS_3DHOST_USERNAME'),
            (string) getenv('PARAMPOS_3DHOST_PASSWORD'),
            (string) getenv('PARAMPOS_3DHOST_CLIENT_SECRET'),
            (string) getenv('PARAMPOS_3DHOST_TERMINAL_ID'),
        );

        $this->eventDispatcher = new EventDispatcher();

        $this->pos = PosFactory::createPosGateway($account, $config, $this->eventDispatcher);

        $this->card = CreditCardFactory::createForGateway(
            $this->pos,
            '5456165456165454',
            '26',
            '12',
            '000',
            'John Doe'
        );
    }

    public function testGet3DHostFormData(): void
    {
        $order = $this->createPaymentOrder(PosInterface::MODEL_3D_HOST);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            RequestDataPreparedEvent::class,
            function (RequestDataPreparedEvent $requestDataPreparedEvent) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertCount(1, $requestDataPreparedEvent->getRequestData()['soap:Body']);
                $this->assertCount(1, $requestDataPreparedEvent->getRequestData()['soap:Header']);
                $this->assertSame(PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD, $requestDataPreparedEvent->getTxType());
            }
        );
        $formData = $this->pos->get3DFormData(
            $order,
            PosInterface::MODEL_3D_HOST,
            PosInterface::TX_TYPE_PAY_AUTH
        );

        $this->assertIsArray($formData);
        $this->assertArrayHasKey('inputs', $formData);
        $this->assertNotEmpty($formData['inputs']);
        $this->assertTrue($eventIsThrown);
    }
}
