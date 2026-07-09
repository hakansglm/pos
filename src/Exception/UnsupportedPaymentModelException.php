<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Exception;

use RuntimeException;
use Throwable;

class UnsupportedPaymentModelException extends RuntimeException implements PosException
{
    public function __construct(string $message = 'Unsupported payment model!', int $code = 333, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
