<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Exception;

use RuntimeException;
use Throwable;

class MissingAccountInfoException extends RuntimeException implements PosException
{
    public function __construct(string $message = 'Missing Account Information!', int $code = 430, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
