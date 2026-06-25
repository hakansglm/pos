<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Factory;

use DomainException;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\Request\Mapper\AkbankPosRequestDataMapper;
use Mews\Pos\DataMapper\Request\Mapper\IyzicoPosRequestDataMapper;
use Mews\Pos\DataMapper\Request\Mapper\AssecoPosRequestDataMapper;
use Mews\Pos\DataMapper\Request\Mapper\GarantiPosRequestDataMapper;
use Mews\Pos\DataMapper\Request\Mapper\InterPosRequestDataMapper;
use Mews\Pos\DataMapper\Request\Mapper\KuveytPosRequestDataMapper;
use Mews\Pos\DataMapper\Request\Mapper\Param3DHostPosRequestDataMapper;
use Mews\Pos\DataMapper\Request\Mapper\ParamPosRequestDataMapper;
use Mews\Pos\DataMapper\Request\Mapper\PayFlexCPV4PosRequestDataMapper;
use Mews\Pos\DataMapper\Request\Mapper\PayFlexV4PosRequestDataMapper;
use Mews\Pos\DataMapper\Request\Mapper\PayForPosRequestDataMapper;
use Mews\Pos\DataMapper\Request\Mapper\PosNetPosRequestDataMapper;
use Mews\Pos\DataMapper\Request\Mapper\PosNetV1PosRequestDataMapper;
use Mews\Pos\DataMapper\Request\Mapper\RequestDataMapperInterface;
use Mews\Pos\DataMapper\Request\Mapper\PayTrPosRequestDataMapper;
use Mews\Pos\DataMapper\Request\Mapper\ToslaPosRequestDataMapper;
use Mews\Pos\DataMapper\Request\Mapper\VakifKatilimPosRequestDataMapper;
use Mews\Pos\DataMapper\Request\ValueFormatter\RequestValueFormatterInterface;
use Mews\Pos\DataMapper\Request\ValueMapper\RequestValueMapperInterface;
use Mews\Pos\PosInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * RequestDataMapperFactory
 */
class RequestDataMapperFactory
{
    /**
     * @var class-string<RequestDataMapperInterface>[]
     */
    private static array $requestDataMapperClasses = [
        AkbankPosRequestDataMapper::class,
        AssecoPosRequestDataMapper::class,
        GarantiPosRequestDataMapper::class,
        InterPosRequestDataMapper::class,
        IyzicoPosRequestDataMapper::class,
        KuveytPosRequestDataMapper::class,
        ParamPosRequestDataMapper::class,
        Param3DHostPosRequestDataMapper::class,
        PayFlexCPV4PosRequestDataMapper::class,
        PayFlexV4PosRequestDataMapper::class,
        PayForPosRequestDataMapper::class,
        PosNetPosRequestDataMapper::class,
        PosNetV1PosRequestDataMapper::class,
        ToslaPosRequestDataMapper::class,
        VakifKatilimPosRequestDataMapper::class,
        PayTrPosRequestDataMapper::class,
    ];

    /**
     * @param class-string<PosInterface>     $gatewayClass
     * @param RequestValueMapperInterface    $valueMapper
     * @param RequestValueFormatterInterface $valueFormatter
     * @param EventDispatcherInterface       $eventDispatcher
     * @param CryptInterface                 $crypt
     * @param PosInterface::LANG_*           $defaultLang
     *
     * @return RequestDataMapperInterface
     */
    public static function createForGateway(
        string                         $gatewayClass,
        RequestValueMapperInterface    $valueMapper,
        RequestValueFormatterInterface $valueFormatter,
        EventDispatcherInterface       $eventDispatcher,
        CryptInterface                 $crypt,
        string                         $defaultLang
    ): RequestDataMapperInterface {
        /** @var class-string<RequestDataMapperInterface> $requestDataMapperClass */
        foreach (self::$requestDataMapperClasses as $requestDataMapperClass) {
            if ($requestDataMapperClass::supports($gatewayClass)) {
                return new $requestDataMapperClass(
                    $valueMapper,
                    $valueFormatter,
                    $eventDispatcher,
                    $crypt,
                    $defaultLang
                );
            }
        }


        throw new DomainException(\sprintf('Request data mapper not found for the gateway %s', $gatewayClass));
    }
}
