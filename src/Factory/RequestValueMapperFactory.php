<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Factory;

use DomainException;
use Mews\Pos\DataMapper\Request\ValueMapper\AkbankPosRequestValueMapper;
use Mews\Pos\DataMapper\Request\ValueMapper\AssecoPosRequestValueMapper;
use Mews\Pos\DataMapper\Request\ValueMapper\IyzicoPosRequestValueMapper;
use Mews\Pos\DataMapper\Request\ValueMapper\GarantiPosRequestValueMapper;
use Mews\Pos\DataMapper\Request\ValueMapper\InterPosRequestValueMapper;
use Mews\Pos\DataMapper\Request\ValueMapper\KuveytPosRequestValueMapper;
use Mews\Pos\DataMapper\Request\ValueMapper\ParamPosRequestValueMapper;
use Mews\Pos\DataMapper\Request\ValueMapper\PayFlexCPV4PosRequestValueMapper;
use Mews\Pos\DataMapper\Request\ValueMapper\PayFlexV4PosRequestValueMapper;
use Mews\Pos\DataMapper\Request\ValueMapper\PayForPosRequestValueMapper;
use Mews\Pos\DataMapper\Request\ValueMapper\PosNetPosRequestValueMapper;
use Mews\Pos\DataMapper\Request\ValueMapper\PosNetV1PosRequestValueMapper;
use Mews\Pos\DataMapper\Request\ValueMapper\RequestValueMapperInterface;
use Mews\Pos\DataMapper\Request\ValueMapper\PayTrPosRequestValueMapper;
use Mews\Pos\DataMapper\Request\ValueMapper\ToslaPosRequestValueMapper;
use Mews\Pos\DataMapper\Request\ValueMapper\VakifKatilimPosRequestValueMapper;
use Mews\Pos\PosInterface;

/**
 * RequestValueMapperFactory
 */
class RequestValueMapperFactory
{
    /**
     * @var class-string<RequestValueMapperInterface>[]
     */
    private static array $requestValueMapperClasses = [
        ToslaPosRequestValueMapper::class,
        AkbankPosRequestValueMapper::class,
        AssecoPosRequestValueMapper::class,
        AssecoPosRequestValueMapper::class,
        GarantiPosRequestValueMapper::class,
        InterPosRequestValueMapper::class,
        IyzicoPosRequestValueMapper::class,
        KuveytPosRequestValueMapper::class,
        KuveytPosRequestValueMapper::class,
        VakifKatilimPosRequestValueMapper::class,
        PayForPosRequestValueMapper::class,
        PosNetPosRequestValueMapper::class,
        PosNetV1PosRequestValueMapper::class,
        ParamPosRequestValueMapper::class,
        PayFlexCPV4PosRequestValueMapper::class,
        PayFlexV4PosRequestValueMapper::class,
        PayTrPosRequestValueMapper::class,
    ];

    /**
     * @param class-string<PosInterface> $gatewayClass
     *
     * @return RequestValueMapperInterface
     */
    public static function createForGateway(string $gatewayClass): RequestValueMapperInterface
    {
        /** @var class-string<RequestValueMapperInterface> $valueMapperClass */
        foreach (self::$requestValueMapperClasses as $valueMapperClass) {
            if ($valueMapperClass::supports($gatewayClass)) {
                return new $valueMapperClass();
            }
        }

        throw new DomainException('unsupported gateway');
    }
}
