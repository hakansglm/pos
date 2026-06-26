<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Factory;

use Mews\Pos\Crypt\AkbankPosCrypt;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\Crypt\AssecoPosCrypt;
use Mews\Pos\Crypt\GarantiPosCrypt;
use Mews\Pos\Crypt\InterPosCrypt;
use Mews\Pos\Crypt\IyzicoPosCrypt;
use Mews\Pos\Crypt\KuveytPosCrypt;
use Mews\Pos\Crypt\NullCrypt;
use Mews\Pos\Crypt\ParamPosCrypt;
use Mews\Pos\Crypt\PayFlexCPV4Crypt;
use Mews\Pos\Crypt\PayForPosCrypt;
use Mews\Pos\Crypt\PayTrPosCrypt;
use Mews\Pos\Crypt\PosNetPosCrypt;
use Mews\Pos\Crypt\PosNetV1PosCrypt;
use Mews\Pos\Crypt\ToslaPosCrypt;
use Mews\Pos\PosInterface;
use Psr\Log\LoggerInterface;

/**
 * CryptFactory
 *
 * @internal
 */
class CryptFactory
{
    /**
     * @var class-string<CryptInterface>[]
     */
    private static array $crypts = [
        AkbankPosCrypt::class,
        AssecoPosCrypt::class,
        GarantiPosCrypt::class,
        InterPosCrypt::class,
        IyzicoPosCrypt::class,
        KuveytPosCrypt::class,
        ParamPosCrypt::class,
        PayFlexCPV4Crypt::class,
        PayForPosCrypt::class,
        PayTrPosCrypt::class,
        PosNetPosCrypt::class,
        PosNetV1PosCrypt::class,
        ToslaPosCrypt::class,
    ];

    /**
     * @param class-string<PosInterface> $gatewayClass
     * @param LoggerInterface            $logger
     *
     * @return CryptInterface
     */
    public static function createForGateway(string $gatewayClass, LoggerInterface $logger): CryptInterface
    {
        /** @var class-string<CryptInterface> $crypt */
        foreach (self::$crypts as $crypt) {
            if ($crypt::supports($gatewayClass)) {
                return new $crypt($logger);
            }
        }

        return new NullCrypt($logger);
    }
}
