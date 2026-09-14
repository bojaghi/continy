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
use Bojaghi\Helper\Helper;

/**
 * Continy Factory class
 *
 * @see docs/factory-setup.md
 */
class Continy_Factory implements Factory_Interface {
	/**
	 * Create continy instance by given $setup
	 *
	 * @param array|string $setup Configuration array or absolute path to file that returns configuration array.
	 *
	 * @return Container
	 */
	public static function create( array|string $setup ): Container {
		return new Continy( Helper::load_config( $setup ) );
	}
}
