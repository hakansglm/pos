<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Exception;

use RuntimeException;
use Throwable;

class GatewayConfigNotFoundException extends RuntimeException implements PosException
{
    public function __construct(string $message = 'Bank config not found!', int $code = 330, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
