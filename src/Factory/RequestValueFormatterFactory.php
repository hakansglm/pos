<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Factory;

use DomainException;
use Mews\Pos\DataMapper\Request\ValueFormatter\AkbankPosRequestValueFormatter;
use Mews\Pos\DataMapper\Request\ValueFormatter\AssecoPosRequestValueFormatter;
use Mews\Pos\DataMapper\Request\ValueFormatter\IyzicoPosRequestValueFormatter;
use Mews\Pos\DataMapper\Request\ValueFormatter\GarantiPosRequestValueFormatter;
use Mews\Pos\DataMapper\Request\ValueFormatter\InterPosRequestValueFormatter;
use Mews\Pos\DataMapper\Request\ValueFormatter\KuveytPosRequestValueFormatter;
use Mews\Pos\DataMapper\Request\ValueFormatter\ParamPosRequestValueFormatter;
use Mews\Pos\DataMapper\Request\ValueFormatter\PayFlexCPV4PosRequestValueFormatter;
use Mews\Pos\DataMapper\Request\ValueFormatter\PayFlexV4PosRequestValueFormatter;
use Mews\Pos\DataMapper\Request\ValueFormatter\PayForPosRequestValueFormatter;
use Mews\Pos\DataMapper\Request\ValueFormatter\PosNetPosRequestValueFormatter;
use Mews\Pos\DataMapper\Request\ValueFormatter\PosNetV1PosRequestValueFormatter;
use Mews\Pos\DataMapper\Request\ValueFormatter\RequestValueFormatterInterface;
use Mews\Pos\DataMapper\Request\ValueFormatter\PayTrPosRequestValueFormatter;
use Mews\Pos\DataMapper\Request\ValueFormatter\ToslaPosRequestValueFormatter;
use Mews\Pos\DataMapper\Request\ValueFormatter\VakifKatilimPosRequestValueFormatter;
use Mews\Pos\PosInterface;

/**
 * RequestValueFormatterFactory
 */
class RequestValueFormatterFactory
{
    /**
     * @var class-string<RequestValueFormatterInterface>[]
     */
    private static array $requestValueFormatterClasses = [
        ToslaPosRequestValueFormatter::class,
        AkbankPosRequestValueFormatter::class,
        AssecoPosRequestValueFormatter::class,
        GarantiPosRequestValueFormatter::class,
        InterPosRequestValueFormatter::class,
        IyzicoPosRequestValueFormatter::class,
        KuveytPosRequestValueFormatter::class,
        VakifKatilimPosRequestValueFormatter::class,
        ParamPosRequestValueFormatter::class,
        PayForPosRequestValueFormatter::class,
        PosNetPosRequestValueFormatter::class,
        PosNetV1PosRequestValueFormatter::class,
        PayFlexCPV4PosRequestValueFormatter::class,
        PayFlexV4PosRequestValueFormatter::class,
        PayTrPosRequestValueFormatter::class,
    ];

    /**
     * @param class-string<PosInterface> $gatewayClass
     *
     * @return RequestValueFormatterInterface
     */
    public static function createForGateway(string $gatewayClass): RequestValueFormatterInterface
    {
        /** @var class-string<RequestValueFormatterInterface> $valueFormatterClass */
        foreach (self::$requestValueFormatterClasses as $valueFormatterClass) {
            if ($valueFormatterClass::supports($gatewayClass)) {
                return new $valueFormatterClass();
            }
        }

        throw new DomainException('unsupported gateway');
    }
}
