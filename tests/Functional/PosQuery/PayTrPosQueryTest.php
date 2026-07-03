<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Functional\PosQuery;

use DateTimeImmutable;
use Mews\Pos\Event\PosQueryRequestDataPreparedEvent;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\PosQueryFactory;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\PosQuery\PosQueryInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[CoversNothing]
class PayTrPosQueryTest extends TestCase
{
    private EventDispatcher $eventDispatcher;

    private PosQueryInterface $posQuery;

    protected function setUp(): void
    {
        parent::setUp();

        $config = require __DIR__.'/../../../config/pos_test.php';

        $account = AccountFactory::createPayTrPosAccount(
            'paytr',
            (string) getenv('PAYTR_MERCHANT_ID'),
            (string) getenv('PAYTR_MERCHANT_SALT'),
            (string) getenv('PAYTR_MERCHANT_KEY'),
        );

        $this->eventDispatcher = new EventDispatcher();
        $this->posQuery        = PosQueryFactory::create($account, $config, $this->eventDispatcher);
    }

    #[TestWith(['435508', CreditCardInterface::CARD_TYPE_VISA])]
    #[TestWith(['540667', CreditCardInterface::CARD_TYPE_MASTERCARD])]
    #[TestWith(['374111', CreditCardInterface::CARD_TYPE_AMEX])]
    #[TestWith(['979203', CreditCardInterface::CARD_TYPE_TROY])]
    public function testGetBinList(string $bin, string $expectedCardType): void
    {
        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            PosQueryRequestDataPreparedEvent::class,
            function (PosQueryRequestDataPreparedEvent $event) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosQueryInterface::QUERY_TYPE_BIN_LIST, $event->getTxType());
            }
        );

        $response = $this->posQuery->getBinList(['bin' => $bin]);

        $this->assertTrue($eventIsThrown);
        $this->assertTrue($this->posQuery->isSuccess(), $response['error_message'] ?? '');
        $this->assertNotEmpty($response['bin_list']);
        $firstResult = $response['bin_list'][0];
        $this->assertSame($expectedCardType, $firstResult['card_type']);
        $this->assertContains($firstResult['card_class'], [
            CreditCardInterface::CARD_CLASS_CREDIT,
            CreditCardInterface::CARD_CLASS_DEBIT,
            CreditCardInterface::CARD_CLASS_PREPAID,
            null,
        ]);
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

        $response = $this->posQuery->getInstallmentRates([]);

        $this->assertTrue($eventIsThrown);
        $this->assertTrue($this->posQuery->isSuccess(), $response['error_message'] ?? '');
        $this->assertNotEmpty($response['installment_rates']);
        $firstGroup = $response['installment_rates'][0];
        $this->assertNull($firstGroup['card_prefix']);
        $this->assertNotEmpty($firstGroup['card_family']);
        $this->assertNotEmpty($firstGroup['rates']);
        $firstRate = $firstGroup['rates'][0];
        $this->assertArrayHasKey('installment', $firstRate);
        $this->assertArrayHasKey('rate', $firstRate);
        $this->assertArrayHasKey('constant', $firstRate);
        $this->assertGreaterThanOrEqual(2, $firstRate['installment']);
        $this->assertIsFloat($firstRate['rate']);
        $this->assertIsFloat($firstRate['constant']);
    }

    public function testHistorySuccess(): void
    {
        $txTime = new DateTimeImmutable();

        $historyData = [
            'start_date' => $txTime->modify('-23 hour'),
            'end_date'   => $txTime,
        ];

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            PosQueryRequestDataPreparedEvent::class,
            function (PosQueryRequestDataPreparedEvent $event) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosQueryInterface::QUERY_TYPE_HISTORY, $event->getTxType());
            }
        );

        $response = $this->posQuery->history($historyData);

        $this->assertIsArray($response);
        $this->assertTrue($eventIsThrown);
        $this->assertArrayHasKey('transactions', $response);
    }

    public function testCustomQuery(): void
    {
        $customQuery = [
            'request_id' => date('Ymd').strtoupper(substr(uniqid(sha1((string) time()), true), 0, 10)),
        ];

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            PosQueryRequestDataPreparedEvent::class,
            function (PosQueryRequestDataPreparedEvent $event) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosQueryInterface::QUERY_TYPE_CUSTOM_QUERY, $event->getTxType());
            }
        );

        $response = $this->posQuery->customQuery($customQuery);

        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);
    }
}
