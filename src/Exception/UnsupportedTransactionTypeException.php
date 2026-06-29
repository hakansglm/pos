<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Exception;

use RuntimeException;
use Throwable;

class UnsupportedTransactionTypeException extends RuntimeException implements PosException
{
    public function __construct(string $message = 'Unsupported transaction type!', int $code = 332, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
