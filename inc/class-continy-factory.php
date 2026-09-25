<?php
/**
 * Continy
 *
 * @package Bojaghi\Continy
 */

declare( strict_types=1 );

namespace Bojaghi\Continy;

use Bojaghi\Contract\Container;
use Bojaghi\Contract\Container_Factory;
use Bojaghi\Helper\Helper;

/**
 * Continy Factory class
 *
 * @see docs/factory-setup.md
 */
class Continy_Factory implements Container_Factory {
	/**
	 * Create continy instance by given $setup
	 *
	 * @param array|string $setup Configuration array or absolute path to file that returns configuration array.
	 *
	 * @return Container
	 *
	 * @throws Continy_Exception When initialization fails.
	 */
	public static function create( array|string $setup ): Container {
		return new Continy( Helper::load_config( $setup ) );
	}
}
