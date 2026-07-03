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
class IyzicoPosQueryTest extends TestCase
{
    private EventDispatcher $eventDispatcher;

    private PosQueryInterface $posQuery;

    protected function setUp(): void
    {
        parent::setUp();

        $config = require __DIR__.'/../../../config/pos_test.php';

        $account = AccountFactory::createIyzicoPosAccount(
            'iyzico',
            (string) getenv('IYZICO_API_KEY'),
            (string) getenv('IYZICO_SECRET_KEY'),
        );

        $this->eventDispatcher = new EventDispatcher();
        $this->posQuery        = PosQueryFactory::create($account, $config, $this->eventDispatcher);
    }

    #[TestWith(['460345', CreditCardInterface::CARD_TYPE_VISA])]
    #[TestWith(['540036', CreditCardInterface::CARD_TYPE_MASTERCARD])]
    #[TestWith(['374427', CreditCardInterface::CARD_TYPE_AMEX])]
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
        $this->assertSame($bin, $firstResult['bin']);
        $this->assertSame($expectedCardType, $firstResult['card_type']);
        $this->assertContains($firstResult['card_class'], [
            CreditCardInterface::CARD_CLASS_CREDIT,
            CreditCardInterface::CARD_CLASS_DEBIT,
            CreditCardInterface::CARD_CLASS_PREPAID,
            null,
        ]);
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

        $response = $this->posQuery->getInstallmentPrices([
            'bin'    => '54308100',
            'amount' => 100.0,
        ]);

        $this->assertTrue($eventIsThrown);
        $this->assertTrue($this->posQuery->isSuccess(), $response['error_message'] ?? '');
        $this->assertNotEmpty($response['installment_prices']);
        $firstGroup = $response['installment_prices'][0];
        $this->assertSame('54308100', $firstGroup['card_prefix']);
        $this->assertNotEmpty($firstGroup['prices']);
        $firstEntry = $firstGroup['prices'][0];
        $this->assertArrayHasKey('installment', $firstEntry);
        $this->assertArrayHasKey('installment_price', $firstEntry);
        $this->assertArrayHasKey('total_price', $firstEntry);
        $this->assertIsInt($firstEntry['installment']);
        $this->assertIsFloat($firstEntry['installment_price']);
    }

    public function testGetInstallmentPricesWithoutBin(): void
    {
        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            PosQueryRequestDataPreparedEvent::class,
            function (PosQueryRequestDataPreparedEvent $event) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosQueryInterface::QUERY_TYPE_INSTALLMENT_PRICES, $event->getTxType());
            }
        );

        $response = $this->posQuery->getInstallmentPrices([
            'amount' => 100.0,
        ]);

        $this->assertTrue($eventIsThrown);
        $this->assertTrue($this->posQuery->isSuccess(), $response['error_message'] ?? '');
        $this->assertGreaterThan(1, \count($response['installment_prices']));
        $firstGroup = $response['installment_prices'][0];
        $this->assertNull($firstGroup['card_prefix']);
        $this->assertNotEmpty($firstGroup['card_family']);
        $this->assertNotEmpty($firstGroup['prices']);
        $firstEntry = $firstGroup['prices'][0];
        $this->assertArrayHasKey('installment', $firstEntry);
        $this->assertArrayHasKey('installment_price', $firstEntry);
        $this->assertArrayHasKey('total_price', $firstEntry);
        $this->assertIsInt($firstEntry['installment']);
        $this->assertIsFloat($firstEntry['installment_price']);
    }

    public function testCustomQuery(): void
    {
        $customQuery = [
            'price'     => 100.0,
            'binNumber' => '54308100',
        ];

        $apiUrl = 'https://sandbox-api.iyzipay.com/payment/iyzipos/installment';

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
        $this->assertTrue($eventIsThrown);
    }

    public function testHistorySuccess(): void
    {
        $historyData = [
            'transaction_date' => new DateTimeImmutable(),
            'page'             => 1,
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
        $this->assertNotEmpty($response);
        $this->assertTrue($eventIsThrown);
    }
}
