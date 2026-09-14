<?php
/**
 * Continy
 *
 * @package Bojaghi\Continy
 */

declare( strict_types=1 );

namespace Bojaghi\Continy;

use Psr\Container\NotFoundExceptionInterface;

/**
 * Continy_Not_Found_Exception
 *
 * Thrown when the result is not queried.
 */
class Continy_Not_Found_Exception extends Continy_Exception implements NotFoundExceptionInterface {
}
