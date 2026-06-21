<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\Response\ValueFormatter;

use Mews\Pos\Gateway\AkbankPos;
use Mews\Pos\Gateway\PayFlexCPV4Pos;
use Mews\Pos\Gateway\PayFlexV4Pos;
use Mews\Pos\Gateway\PayForPos;

class BasicResponseValueFormatter extends AbstractResponseValueFormatter
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return AkbankPos::class === $gatewayClass
            || PayFlexCPV4Pos::class === $gatewayClass
            || PayFlexV4Pos::class === $gatewayClass
            || PayForPos::class === $gatewayClass;
    }
}
