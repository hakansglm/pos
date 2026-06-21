<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Factory;

use DomainException;
use Mews\Pos\DataMapper\Response\Mapper\AkbankPosResponseDataMapper;
use Mews\Pos\DataMapper\Response\Mapper\AssecoPosResponseDataMapper;
use Mews\Pos\DataMapper\Response\Mapper\IyzicoPosResponseDataMapper;
use Mews\Pos\DataMapper\Response\Mapper\GarantiPosResponseDataMapper;
use Mews\Pos\DataMapper\Response\Mapper\InterPosResponseDataMapper;
use Mews\Pos\DataMapper\Response\Mapper\KuveytPosResponseDataMapper;
use Mews\Pos\DataMapper\Response\Mapper\ParamPosResponseDataMapper;
use Mews\Pos\DataMapper\Response\Mapper\PayFlexCPV4PosResponseDataMapper;
use Mews\Pos\DataMapper\Response\Mapper\PayFlexV4PosResponseDataMapper;
use Mews\Pos\DataMapper\Response\Mapper\PayForPosResponseDataMapper;
use Mews\Pos\DataMapper\Response\Mapper\PosNetPosResponseDataMapper;
use Mews\Pos\DataMapper\Response\Mapper\PosNetV1PosResponseDataMapper;
use Mews\Pos\DataMapper\Response\Mapper\ResponseDataMapperInterface;
use Mews\Pos\DataMapper\Response\Mapper\ToslaPosResponseDataMapper;
use Mews\Pos\DataMapper\Response\Mapper\VakifKatilimPosResponseDataMapper;
use Mews\Pos\DataMapper\Response\ValueFormatter\ResponseValueFormatterInterface;
use Mews\Pos\DataMapper\Response\ValueMapper\ResponseValueMapperInterface;
use Mews\Pos\PosInterface;
use Psr\Log\LoggerInterface;

/**
 * ResponseDataMapperFactory
 */
class ResponseDataMapperFactory
{
    /**
     * @var class-string<ResponseDataMapperInterface>[]
     */
    private static array $responseDataMapperClasses = [
        AkbankPosResponseDataMapper::class,
        AssecoPosResponseDataMapper::class,
        GarantiPosResponseDataMapper::class,
        InterPosResponseDataMapper::class,
        IyzicoPosResponseDataMapper::class,
        KuveytPosResponseDataMapper::class,
        ParamPosResponseDataMapper::class,
        PayFlexCPV4PosResponseDataMapper::class,
        PayFlexV4PosResponseDataMapper::class,
        PayForPosResponseDataMapper::class,
        PosNetPosResponseDataMapper::class,
        PosNetV1PosResponseDataMapper::class,
        ToslaPosResponseDataMapper::class,
        VakifKatilimPosResponseDataMapper::class,
    ];

    /**
     * @param class-string<PosInterface>      $gatewayClass
     * @param ResponseValueFormatterInterface $valueFormatter
     * @param ResponseValueMapperInterface    $valueMapper
     * @param LoggerInterface                 $logger
     *
     * @return ResponseDataMapperInterface
     */
    public static function createForGateway(
        string                          $gatewayClass,
        ResponseValueFormatterInterface $valueFormatter,
        ResponseValueMapperInterface    $valueMapper,
        LoggerInterface                 $logger
    ): ResponseDataMapperInterface {
        /** @var class-string<ResponseDataMapperInterface> $responseDataMapperClass */
        foreach (self::$responseDataMapperClasses as $responseDataMapperClass) {
            if ($responseDataMapperClass::supports($gatewayClass)) {
                return new $responseDataMapperClass(
                    $valueFormatter,
                    $valueMapper,
                    $logger
                );
            }
        }

        throw new DomainException(\sprintf('Response data mapper not found for the gateway %s', $gatewayClass));
    }
}
