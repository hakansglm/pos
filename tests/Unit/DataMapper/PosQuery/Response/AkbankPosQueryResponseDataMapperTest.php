<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\PosQuery\Response;

use Generator;
use Mews\Pos\DataMapper\PosQuery\Response\AbstractQueryResponseDataMapper;
use Mews\Pos\DataMapper\PosQuery\Response\AkbankPosQueryResponseDataMapper;
use Mews\Pos\Exception\UnsupportedTransactionTypeException;
use Mews\Pos\Factory\ResponseValueFormatterFactory;
use Mews\Pos\Factory\ResponseValueMapperFactory;
use Mews\Pos\Gateway\AkbankPos;
use Mews\Pos\Gateway\AssecoPos;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(AkbankPosQueryResponseDataMapper::class)]
#[CoversClass(AbstractQueryResponseDataMapper::class)]
class AkbankPosQueryResponseDataMapperTest extends TestCase
{
    private AkbankPosQueryResponseDataMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapper = new AkbankPosQueryResponseDataMapper(
            ResponseValueFormatterFactory::createForGateway(AkbankPos::class),
            ResponseValueMapperFactory::createForGateway(AkbankPos::class),
            new NullLogger()
        );
    }

    public function testSupports(): void
    {
        $this->assertTrue(AkbankPosQueryResponseDataMapper::supports(AkbankPos::class));
        $this->assertFalse(AkbankPosQueryResponseDataMapper::supports(AssecoPos::class));
    }

    public function testMapBinListResponseThrows(): void
    {
        $this->expectException(UnsupportedTransactionTypeException::class);

        $this->mapper->mapBinListResponse([]);
    }

    #[DataProvider('mapHistoryResponseDataProvider')]
    public function testMapHistoryResponse(array $responseData, array $expected): void
    {
        $actual = $this->mapper->mapHistoryResponse($responseData);

        $this->assertCount($expected['trans_count'], $actual['transactions']);
        $this->assertSame($expected['proc_return_code'], $actual['proc_return_code']);
        $this->assertSame($expected['status'], $actual['status']);
        $this->assertSame($expected['error_code'], $actual['error_code']);
        $this->assertArrayHasKey('all', $actual);
    }

    public static function mapHistoryResponseDataProvider(): Generator
    {
        yield 'failed_response' => [
            'responseData' => [
                'requestId'       => 'VPS00020599126001999999999920240425095529000904',
                'responseMessage' => 'Gün aralığı 1 günden fazla girilemez',
                'responseCode'    => 'VPS-2229',
            ],
            'expected' => [
                'proc_return_code' => 'VPS-2229',
                'error_code'       => 'VPS-2229',
                'status'           => 'declined',
                'trans_count'      => 0,
            ],
        ];

        $fixture = \json_decode(
            \file_get_contents(__DIR__.'/../../../test_data/akbankpos/history/daily_history_2.json'),
            true
        );

        yield 'success_daily_history' => [
            'responseData' => $fixture,
            'expected' => [
                'proc_return_code' => 'VPS-0000',
                'error_code'       => null,
                'status'           => 'approved',
                'trans_count'      => 8,
            ],
        ];
    }
}
