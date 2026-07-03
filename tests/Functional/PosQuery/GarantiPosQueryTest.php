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
class GarantiPosQueryTest extends TestCase
{
    private EventDispatcher $eventDispatcher;

    private PosQueryInterface $posQuery;

    protected function setUp(): void
    {
        parent::setUp();

        $config = require __DIR__.'/../../../config/pos_test.php';

        $account = AccountFactory::createGarantiPosAccount(
            'garanti',
            (string) getenv('GARANTI_MERCHANT_ID'),
            (string) getenv('GARANTI_USERNAME'),
            (string) getenv('GARANTI_PASSWORD'),
            (string) getenv('GARANTI_TERMINAL_ID'),
            (string) getenv('GARANTI_STORE_KEY')
        );

        $this->eventDispatcher = new EventDispatcher();
        $this->posQuery        = PosQueryFactory::create($account, $config, $this->eventDispatcher);
    }

    public function testGetBinList(): void
    {
        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            PosQueryRequestDataPreparedEvent::class,
            function (PosQueryRequestDataPreparedEvent $event) use (&$eventIsThrown): void {
                $eventIsThrown = true;
                $this->assertSame(PosQueryInterface::QUERY_TYPE_BIN_LIST, $event->getTxType());
            }
        );

        $response = $this->posQuery->getBinList(['ip' => '127.0.0.1']);

        $this->assertTrue($eventIsThrown);
        $this->assertTrue($this->posQuery->isSuccess(), $response['error_message'] ?? '');
        $this->assertNotEmpty($response['bin_list']);

        $firstEntry = $response['bin_list'][0];
        $this->assertArrayHasKey('bin', $firstEntry);
        $this->assertArrayHasKey('bank_code', $firstEntry);
        $this->assertArrayHasKey('bank_name', $firstEntry);
        $this->assertArrayHasKey('card_type', $firstEntry);
        $this->assertArrayHasKey('card_class', $firstEntry);
        $this->assertArrayHasKey('card_family', $firstEntry);
    }

    public function testCustomQuery(): void
    {
        $customQuery = [
            'Version'     => 'v0.00',
            'Customer'    => [
                'IPAddress'    => '1.1.111.111',
                'EmailAddress' => 'Cem@cem.com',
            ],
            'Order'       => [
                'OrderID'     => 'SISTD5A61F1682E745B28871872383ABBEB1',
                'GroupID'     => '',
                'Description' => '',
            ],
            'Transaction' => [
                'Type'   => 'bininq',
                'Amount' => '1',
                'BINInq' => [
                    'Group'    => 'A',
                    'CardType' => 'A',
                ],
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
        $this->assertArrayHasKey('Transaction', $response);
        $this->assertTrue($eventIsThrown);
    }

    public function testHistorySuccess(): void
    {
        $txTime = new DateTimeImmutable();

        $historyData = [
            'ip'         => '127.0.0.1',
            'page'       => 1,
            'start_date' => $txTime->modify('-1 day'),
            'end_date'   => $txTime->modify('+1 day'),
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
