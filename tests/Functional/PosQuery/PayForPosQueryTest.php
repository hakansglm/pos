<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Functional\PosQuery;

use DateTimeImmutable;
use Mews\Pos\Event\PosQueryRequestDataPreparedEvent;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\PosQueryFactory;
use Mews\Pos\PosQuery\PosQueryInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[CoversNothing]
class PayForPosQueryTest extends TestCase
{
    private EventDispatcher $eventDispatcher;

    private PosQueryInterface $posQuery;

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
        );

        $this->eventDispatcher = new EventDispatcher();
        $this->posQuery        = PosQueryFactory::create($account, $config, $this->eventDispatcher);
    }

    public function testCustomQuery(): void
    {
        $customQuery = [
            'SecureType' => 'Inquiry',
            'TxnType'    => 'ParaPuanInquiry',
            'Pan'        => '4155650100416111',
            'Expiry'     => '0125',
            'Cvv2'       => '123',
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
        $this->assertArrayHasKey('ProcReturnCode', $response);
        $this->assertTrue($eventIsThrown);
    }

    public function testHistorySuccess(): void
    {
        $historyData = [
            'transaction_date' => new DateTimeImmutable(),
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
        $this->assertNotEmpty($response['transactions']);
    }
}
