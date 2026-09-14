<?php
/**
 * Continy
 *
 * @package Bojaghi\Continy
 */

declare( strict_types=1 );

namespace Bojaghi\Continy;

use Exception;
use Psr\Container\ContainerExceptionINterface;

/**
 * Continy_Exception
 *
 * Thrown when something is wrong
 */
class Continy_Exception extends Exception implements ContainerExceptionInterface {
}
