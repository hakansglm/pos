<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Exception;

/**
 * Marker interface for all library exceptions.
 * Catch this type to handle any exception thrown by this library.
 *
 * @example
 * try {
 *     $pos->payment(...);
 * } catch (\Mews\Pos\Exception\PosException $e) {
 *     // catches any exception from this library
 * }
 */
interface PosException extends \Throwable
{
}
