<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Factory;

use DomainException;
use Mews\Pos\DataMapper\Response\ValueMapper\AkbankPosResponseValueMapper;
use Mews\Pos\DataMapper\Response\ValueMapper\IyzicoPosResponseValueMapper;
use Mews\Pos\DataMapper\Response\ValueMapper\KuveytPosResponseValueMapper;
use Mews\Pos\DataMapper\Response\ValueMapper\AssecoPosResponseValueMapper;
use Mews\Pos\DataMapper\Response\ValueMapper\GarantiPosResponseValueMapper;
use Mews\Pos\DataMapper\Response\ValueMapper\InterPosResponseValueMapper;
use Mews\Pos\DataMapper\Response\ValueMapper\ParamPosResponseValueMapper;
use Mews\Pos\DataMapper\Response\ValueMapper\PayFlexCPV4PosResponseValueMapper;
use Mews\Pos\DataMapper\Response\ValueMapper\PayFlexV4PosResponseValueMapper;
use Mews\Pos\DataMapper\Response\ValueMapper\PayForPosResponseValueMapper;
use Mews\Pos\DataMapper\Response\ValueMapper\PosNetPosResponseValueMapper;
use Mews\Pos\DataMapper\Response\ValueMapper\PosNetV1PosResponseValueMapper;
use Mews\Pos\DataMapper\Response\ValueMapper\ResponseValueMapperInterface;
use Mews\Pos\DataMapper\Response\ValueMapper\PayTrPosResponseValueMapper;
use Mews\Pos\DataMapper\Response\ValueMapper\ToslaPosResponseValueMapper;
use Mews\Pos\DataMapper\Response\ValueMapper\VakifKatilimPosResponseValueMapper;
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
        PayTrPosResponseValueMapper::class,
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
