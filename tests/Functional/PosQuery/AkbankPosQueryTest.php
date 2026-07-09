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
class AkbankPosQueryTest extends TestCase
{
    private EventDispatcher $eventDispatcher;

    private PosQueryInterface $posQuery;

    protected function setUp(): void
    {
        parent::setUp();

        $config = require __DIR__.'/../../../config/pos_test.php';

        $account = AccountFactory::createAkbankPosAccount(
            'akbank-pos',
            (string) getenv('AKBANKPOS_MERCHANT_ID'),
            (string) getenv('AKBANKPOS_TERMINAL_ID'),
            (string) getenv('AKBANKPOS_API_KEY'),
        );

        $this->eventDispatcher = new EventDispatcher();
        $this->posQuery        = PosQueryFactory::create($account, $config['banks'][$account->getBankName()], $this->eventDispatcher);
    }

    public function testCustomQuery(): void
    {
        $customQuery = [
            'txnCode' => '1020',
            'order'   => [
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
            PosQueryRequestDataPreparedEvent::class,
            function (PosQueryRequestDataPreparedEvent $event) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosQueryInterface::QUERY_TYPE_CUSTOM_QUERY, $event->getTxType());
            }
        );

        $response = $this->posQuery->customQuery($customQuery);

        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertArrayHasKey('responseCode', $response);
        $this->assertTrue($eventIsThrown);
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
        $this->assertNotEmpty($response['transactions']);
    }
}
