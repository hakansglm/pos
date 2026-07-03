<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\PosQuery\Response;

use Mews\Pos\DataMapper\PosQuery\Response\AbstractQueryResponseDataMapper;
use Mews\Pos\DataMapper\PosQuery\Response\VakifKatilimPosQueryResponseDataMapper;
use Mews\Pos\Factory\ResponseValueFormatterFactory;
use Mews\Pos\Factory\ResponseValueMapperFactory;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\VakifKatilimPos;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(VakifKatilimPosQueryResponseDataMapper::class)]
#[CoversClass(AbstractQueryResponseDataMapper::class)]
class VakifKatilimPosQueryResponseDataMapperTest extends TestCase
{
    private VakifKatilimPosQueryResponseDataMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapper = new VakifKatilimPosQueryResponseDataMapper(
            ResponseValueFormatterFactory::createForGateway(VakifKatilimPos::class),
            ResponseValueMapperFactory::createForGateway(VakifKatilimPos::class),
            new NullLogger()
        );
    }

    public function testSupports(): void
    {
        $this->assertTrue(VakifKatilimPosQueryResponseDataMapper::supports(VakifKatilimPos::class));
        $this->assertFalse(VakifKatilimPosQueryResponseDataMapper::supports(AssecoPos::class));
    }

    #[DataProvider('mapHistoryResponseDataProvider')]
    public function testMapHistoryResponse(array $responseData, array $expected): void
    {
        $actual = $this->mapper->mapHistoryResponse($responseData);

        $this->assertCount($expected['trans_count'], $actual['transactions']);
        $this->assertSame($expected['proc_return_code'], $actual['proc_return_code']);
        $this->assertSame($expected['status'], $actual['status']);
        $this->assertArrayHasKey('all', $actual);
    }

    public static function mapHistoryResponseDataProvider(): \Generator
    {
        yield 'failed' => [
            'responseData' => [
                'ResponseCode'    => 'E001',
                'ResponseMessage' => 'Hata',
            ],
            'expected' => [
                'proc_return_code' => 'E001',
                'status'           => 'declined',
                'trans_count'      => 0,
            ],
        ];

        $fixture = \json_decode(
            \file_get_contents(__DIR__.'/../../../test_data/vakifkatilimpos/history/success_history.json'),
            true
        );

        yield 'success' => [
            'responseData' => $fixture,
            'expected'     => [
                'proc_return_code' => '00',
                'status'           => 'approved',
                'trans_count'      => \count($fixture['VPosOrderData']['OrderContract'] ?? []),
            ],
        ];
    }
}
