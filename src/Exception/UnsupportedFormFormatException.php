<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Exception;

use InvalidArgumentException;
use Throwable;

class UnsupportedFormFormatException extends InvalidArgumentException implements PosException
{
    public function __construct(string $message = 'Unsupported 3D form format!', int $code = 333, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
