<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Exception;

use DomainException;
use Throwable;

/**
 * thrown if card type is not provided
 */
class CardTypeRequiredException extends DomainException
{
    /**
     * BankNotFoundException constructor.
     *
     * @param string         $gatewayName
     * @param string         $message
     * @param int            $code
     * @param Throwable|null $previous
     */
    public function __construct(
        private string $gatewayName,
        string         $message = 'Card type is required for this gateway!',
        int            $code = 73,
        ?Throwable     $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return string
     */
    public function getGatewayName(): string
    {
        return $this->gatewayName;
    }
}
