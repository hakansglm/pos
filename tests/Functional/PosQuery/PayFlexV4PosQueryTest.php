<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Functional\PosQuery;

use Mews\Pos\Event\PosQueryRequestDataPreparedEvent;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\PosQueryFactory;
use Mews\Pos\PosQuery\PosQueryInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;

#[CoversNothing]
class PayFlexV4PosQueryTest extends TestCase
{
    private EventDispatcher $eventDispatcher;

    private PosQueryInterface $posQuery;

    protected function setUp(): void
    {
        parent::setUp();

        $config = require __DIR__.'/../../../config/pos_test.php';

        $account = AccountFactory::createPayFlexPosAccount(
            'vakifbank',
            (string) getenv('PAYFLEX_CP_MERCHANT_ID'),
            (string) getenv('PAYFLEX_CP_MERCHANT_PASSWORD'),
            (string) getenv('PAYFLEX_CP_TERMINAL_ID'),
        );

        $httpClient = new Psr18Client(HttpClient::create(['verify_peer' => false, 'verify_host' => false]));

        $this->eventDispatcher = new EventDispatcher();
        $this->posQuery        = PosQueryFactory::create(
            $account,
            $config['banks'][$account->getBankName()],
            $this->eventDispatcher,
            $httpClient
        );
    }

    public function testCustomQuery(): void
    {
        $customQuery = [
            'TransactionType' => 'CampaignSearch',
            'TransactionId'   => date('Ymd').strtoupper(substr(uniqid(sha1((string) time()), true), 0, 4)),
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
