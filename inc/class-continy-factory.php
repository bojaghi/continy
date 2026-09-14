<?php
/**
 * Continy
 *
 * @package Bojaghi\Continy
 */

declare( strict_types=1 );

namespace Bojaghi\Continy;

use Bojaghi\Contract\Container;
use Bojaghi\Contract\Continy_Factory as Factory_Interface;

/**
 * Continy Factory class
 *
 * @see docs/factory-setup.md
 */
class Continy_Factory implements Factory_Interface {
	/**
	 * @param array|string $setup Configuration array or absolute path to file that returns configuration array.
	 *
	 * @return Container
	 */
	public static function create( array|string $setup ): Container {
	}
}
