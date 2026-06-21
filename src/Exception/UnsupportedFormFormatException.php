<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Exception;

use Exception;
use Throwable;

class UnsupportedFormFormatException extends Exception
{
    public function __construct(string $message = 'Unsupported 3D form format!', int $code = 333, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
