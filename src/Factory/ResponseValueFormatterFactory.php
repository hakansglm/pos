<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Factory;

use DomainException;
use Mews\Pos\DataMapper\Response\ValueFormatter\BasicResponseValueFormatter;
use Mews\Pos\DataMapper\Response\ValueFormatter\BoaPosResponseValueFormatter;
use Mews\Pos\DataMapper\Response\ValueFormatter\IyzicoPosResponseValueFormatter;
use Mews\Pos\DataMapper\Response\ValueFormatter\AssecoPosResponseValueFormatter;
use Mews\Pos\DataMapper\Response\ValueFormatter\GarantiPosResponseValueFormatter;
use Mews\Pos\DataMapper\Response\ValueFormatter\InterPosResponseValueFormatter;
use Mews\Pos\DataMapper\Response\ValueFormatter\ParamPosResponseValueFormatter;
use Mews\Pos\DataMapper\Response\ValueFormatter\PosNetPosResponseValueFormatter;
use Mews\Pos\DataMapper\Response\ValueFormatter\ResponseValueFormatterInterface;
use Mews\Pos\DataMapper\Response\ValueFormatter\ToslaPosResponseValueFormatter;
use Mews\Pos\PosInterface;

/**
 * ResponseValueFormatterFactory
 */
class ResponseValueFormatterFactory
{
    /**
     * @var class-string<ResponseValueFormatterInterface>[]
     */
    private static array $valueFormatterClasses = [
        BasicResponseValueFormatter::class,
        AssecoPosResponseValueFormatter::class,
        GarantiPosResponseValueFormatter::class,
        InterPosResponseValueFormatter::class,
        IyzicoPosResponseValueFormatter::class,
        BoaPosResponseValueFormatter::class,
        ParamPosResponseValueFormatter::class,
        PosNetPosResponseValueFormatter::class,
        ToslaPosResponseValueFormatter::class,
        BoaPosResponseValueFormatter::class,
    ];

    /**
     * @param class-string<PosInterface> $gatewayClass
     *
     * @return ResponseValueFormatterInterface
     */
    public static function createForGateway(string $gatewayClass): ResponseValueFormatterInterface
    {
        /** @var class-string<ResponseValueFormatterInterface> $formatterClass */
        foreach (self::$valueFormatterClasses as $formatterClass) {
            if ($formatterClass::supports($gatewayClass)) {
                return new $formatterClass();
            }
        }

        throw new DomainException('unsupported gateway');
    }
}
