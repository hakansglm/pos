<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\Response\Mapper;

use Mews\Pos\PosInterface;

/**
 * @internal
 */
interface ResponseDataMapperInterface extends PaymentResponseMapperInterface, NonPaymentResponseMapperInterface
{
    /** @var string */
    public const TX_APPROVED = 'approved';

    /** @var string */
    public const TX_DECLINED = 'declined';

    /**
     * @param class-string<PosInterface> $gatewayClass
     *
     * @return bool
     */
    public static function supports(string $gatewayClass): bool;
}
