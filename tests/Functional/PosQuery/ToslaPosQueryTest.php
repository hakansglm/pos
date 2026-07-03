<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Functional\PosQuery;

use Mews\Pos\Event\PosQueryRequestDataPreparedEvent;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\PosQueryFactory;
use Mews\Pos\PosQuery\PosQueryInterface;
use Mews\Pos\PosQuery\ToslaPosQuery;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[CoversNothing]
class ToslaPosQueryTest extends TestCase
{
    private EventDispatcher $eventDispatcher;

    /** @var ToslaPosQuery */
    private PosQueryInterface $posQuery;

    protected function setUp(): void
    {
        parent::setUp();

        $config = require __DIR__.'/../../../config/pos_test.php';

        $account = AccountFactory::createToslaPosAccount(
            'tosla',
            (string) getenv('TOSLA_MERCHANT_ID'),
            (string) getenv('TOSLA_USERNAME'),
            (string) getenv('TOSLA_PASSWORD'),
        );

        $this->eventDispatcher = new EventDispatcher();
        $this->posQuery        = PosQueryFactory::create($account, $config, $this->eventDispatcher);
    }

    public function testGetInstallmentRates(): void
    {
        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            PosQueryRequestDataPreparedEvent::class,
            function (PosQueryRequestDataPreparedEvent $event) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosQueryInterface::QUERY_TYPE_INSTALLMENT_RATES, $event->getTxType());
            }
        );

        $response = $this->posQuery->getInstallmentRates(['bin' => 415956]);
        $this->assertTrue($eventIsThrown);
        $this->assertTrue($this->posQuery->isSuccess(), $response['error_message'] ?? '');
        $this->assertNotEmpty($response['installment_rates']);
        $firstGroup = $response['installment_rates'][0];
        $this->assertSame('415956', $firstGroup['card_prefix']);
        $this->assertNull($firstGroup['card_family']);
        $this->assertNotEmpty($firstGroup['rates']);
        $this->assertArrayHasKey('installment', $firstGroup['rates'][0]);
        $this->assertArrayHasKey('rate', $firstGroup['rates'][0]);
        $this->assertArrayHasKey('constant', $firstGroup['rates'][0]);
        $this->assertGreaterThanOrEqual(2, $firstGroup['rates'][0]['installment']);
    }

    public function testGetInstallmentPrices(): void
    {
        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            PosQueryRequestDataPreparedEvent::class,
            function (PosQueryRequestDataPreparedEvent $event) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosQueryInterface::QUERY_TYPE_INSTALLMENT_PRICES, $event->getTxType());
            }
        );

        $response = $this->posQuery->getInstallmentPrices(['amount' => 10000]);
        $this->assertTrue($eventIsThrown);
        $this->assertTrue($this->posQuery->isSuccess(), $response['error_message'] ?? '');
        $this->assertNotEmpty($response['installment_prices']);
        $firstGroup = $response['installment_prices'][0];
        $this->assertArrayHasKey('prices', $firstGroup);
        $this->assertNotEmpty($firstGroup['prices']);
        $firstEntry = $firstGroup['prices'][0];
        $this->assertArrayHasKey('installment', $firstEntry);
        $this->assertArrayHasKey('installment_price', $firstEntry);
        $this->assertArrayHasKey('total_price', $firstEntry);
        $this->assertIsInt($firstEntry['installment']);
        $this->assertIsFloat($firstEntry['installment_price']);
        $this->assertIsFloat($firstEntry['total_price']);
    }

    public function testCustomQuery(): void
    {
        $customQuery = [
            'bin' => 415956,
        ];

        $apiUrl = 'https://prepentegrasyon.tosla.com/api/Payment/GetCommissionAndInstallmentInfo';

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            PosQueryRequestDataPreparedEvent::class,
            function (PosQueryRequestDataPreparedEvent $event) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosQueryInterface::QUERY_TYPE_CUSTOM_QUERY, $event->getTxType());
            }
        );

        $response = $this->posQuery->customQuery($customQuery, $apiUrl);

        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertArrayHasKey('BankCode', $response);
        $this->assertTrue($eventIsThrown);
    }
}
