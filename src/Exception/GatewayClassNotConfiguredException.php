<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Exception;

use RuntimeException;
use Throwable;

class GatewayClassNotConfiguredException extends RuntimeException implements PosException
{
    public function __construct(string $message = 'Gateway class must be specified in config!', int $code = 331, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
