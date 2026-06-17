<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Factory;

use DomainException;
use Mews\Pos\DataMapper\ResponseValueMapper\AkbankPosResponseValueMapper;
use Mews\Pos\DataMapper\ResponseValueMapper\IyzicoPosResponseValueMapper;
use Mews\Pos\DataMapper\ResponseValueMapper\KuveytPosResponseValueMapper;
use Mews\Pos\DataMapper\ResponseValueMapper\AssecoPosResponseValueMapper;
use Mews\Pos\DataMapper\ResponseValueMapper\GarantiPosResponseValueMapper;
use Mews\Pos\DataMapper\ResponseValueMapper\InterPosResponseValueMapper;
use Mews\Pos\DataMapper\ResponseValueMapper\ParamPosResponseValueMapper;
use Mews\Pos\DataMapper\ResponseValueMapper\PayFlexCPV4PosResponseValueMapper;
use Mews\Pos\DataMapper\ResponseValueMapper\PayFlexV4PosResponseValueMapper;
use Mews\Pos\DataMapper\ResponseValueMapper\PayForPosResponseValueMapper;
use Mews\Pos\DataMapper\ResponseValueMapper\PosNetPosResponseValueMapper;
use Mews\Pos\DataMapper\ResponseValueMapper\PosNetV1PosResponseValueMapper;
use Mews\Pos\DataMapper\ResponseValueMapper\ResponseValueMapperInterface;
use Mews\Pos\DataMapper\ResponseValueMapper\ToslaPosResponseValueMapper;
use Mews\Pos\DataMapper\ResponseValueMapper\VakifKatilimPosResponseValueMapper;
use Mews\Pos\PosInterface;

class ResponseValueMapperFactory
{
    /**
     * @var class-string<ResponseValueMapperInterface>[]
     */
    private static array $responseValueMapperClasses = [
        AkbankPosResponseValueMapper::class,
        AssecoPosResponseValueMapper::class,
        GarantiPosResponseValueMapper::class,
        InterPosResponseValueMapper::class,
        IyzicoPosResponseValueMapper::class,
        KuveytPosResponseValueMapper::class,
        ParamPosResponseValueMapper::class,
        PayFlexCPV4PosResponseValueMapper::class,
        PayFlexV4PosResponseValueMapper::class,
        PayForPosResponseValueMapper::class,
        PosNetPosResponseValueMapper::class,
        PosNetV1PosResponseValueMapper::class,
        ToslaPosResponseValueMapper::class,
        VakifKatilimPosResponseValueMapper::class,
    ];

    /**
     * @param class-string<PosInterface> $gatewayClass
     *
     * @return ResponseValueMapperInterface
     */
    public static function createForGateway(string $gatewayClass): ResponseValueMapperInterface
    {
        foreach (self::$responseValueMapperClasses as $valueMapperClass) {
            if ($valueMapperClass::supports($gatewayClass)) {
                return new $valueMapperClass();
            }
        }

        throw new DomainException('unsupported gateway');
    }
}
