<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Exceptions;

use DomainException;
use Throwable;

/**
 * thrown when card type is not supported by the gateway
 */
class CardTypeNotSupportedException extends DomainException
{
    /**
     * BankNotFoundException constructor.
     *
     * @param string         $type
     * @param string         $message
     * @param int            $code
     * @param Throwable|null $previous
     */
    public function __construct(
        private string $type,
        string         $message = 'Card type is not supported by this gateway!',
        int            $code = 74,
        ?Throwable     $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }
}
