<?php
/**
 * Continy
 *
 * @package Bojaghi\Continy
 */

declare( strict_types=1 );

namespace Bojaghi\Continy;

use Bojaghi\Contract\Container;
use Bojaghi\Helper\Helper;

/**
 * Continy container class
 */
class Continy implements Container {
	/* Priority constants */
	public const PR_URGENT    = -10000;
	public const PR_VERY_HIGH = 1;
	public const PR_HIGHER    = 5;
	public const PR_HIGH      = 7;
	public const PR_DEFAULT   = 10;
	public const PR_LOW       = 50;
	public const PR_LOWER     = 70;
	public const PR_VERY_LOW  = 100;
	public const PR_LAZY      = 10000;

	/**
	 * 'main_file' setup value
	 *
	 * @var string
	 */
	protected string $main_file;

	/**
	 * 'version' setup value.
	 *
	 * @var string
	 */
	protected string $version;

	/**
	 * Aliases
	 *
	 * - key: each 'as' value of bindings
	 * - value: alias string.
	 *
	 * @var array
	 */
	protected array $aliases;

	/**
	 * All binding items
	 *
	 * @var array
	 */
	protected array $bindings;

	/**
	 * Continy constructor
	 *
	 * @param array $args Setup array.
	 *
	 * @throws Continy_Exception Thrown when 'main_file' is missing in $args.
	 * @see docs/factory-setup.md
	 */
	public function __construct( array $args = array() ) {
		$default = array(
			'main_file' => '',
			'version'   => '0.0.0',
			'bindings'  => array(),
			'modules'   => array(),
		);

		$args = wp_parse_args( $args, $default );

		if ( empty( $args['main_file'] ) ) {
			throw new Continy_Exception( "'main_file' is is required." );
		}

		if ( empty( $args['version'] ) ) {
			$args['version'] = $default['version'];
		}

		$this->main_file = $args['main_file'];
		$this->version   = $args['version'];
		$this->aliases   = array();
		$this->bindings  = array();

		$this->initialize( $args );
	}

	public function get( string $id ) {
		// TODO: Implement get() method.
	}

	public function has( string $id ): bool {
		// TODO: Implement has() method.
	}

	public function call( callable|array|string $to_call, callable|array $args = array() ): mixed {
		// TODO: Implement call() method.
	}

	/**
	 * Return main file string
	 *
	 * Main file string is set by $args['main_file'].
	 *
	 * @return string
	 */
	public function get_main(): string {
		return $this->main_file;
	}

	/**
	 * Return version string
	 *
	 * Version string is set by $args['version'].
	 *
	 * @return string
	 */
	public function get_version(): string {
		return $this->version;
	}

	public function parse_callback( callable|array|string $callback ): ?callable {
		// TODO: Implement parse_callback() method.
	}

	/**
	 * Initialize continy
	 *
	 * @param array $args Setup array.
	 *
	 * @return void
	 */
	protected function initialize( array $args ): void {
		$this->initialize_bindings( $args['bindings'] );
		$this->initialize_modules( $args['modules'] );
	}

	/**
	 * Initialize bindings
	 *
	 * @param array $bindings_setup 'bindings' array.
	 *
	 * @return void
	 */
	protected function initialize_bindings( array $bindings_setup ): void {
		$default = array(
			'when'     => null,
			'as'       => null,
			'args'     => null,
			'reuse'    => true,
			'verbatim' => null,
		);

		// Handle setup items.
		foreach ( $bindings_setup as $alias => $setup ) {
			if ( is_string( $setup ) ) {
				$setup = wp_parse_args( array( 'as' => $setup ), $default );
			}

			if ( is_array( $setup ) ) {
				if ( wp_is_numeric_array( $setup ) ) {
					$nested = array();
					foreach ( $setup as $value ) {
						$nested[] = wp_parse_args( $value, $default );
					}
					$setup = $nested;
				} else {
					$setup = array( $setup );
				}
			} else {
				continue;
			}

			$this->bindings[ $alias ] = $setup;

			// Remember aliases by 'as' key value.
			// Continy may need them when users query instances by FQCN, not by aliases.
			// Users should expect proper objects with dependency injection  no matter how they query.
			foreach ( $setup as $s ) {
				if ( $s['as'] !== $alias ) {
					$this->aliases[ $s['as'] ] = $alias;
				}
			}
		}
	}

	/**
	 * Initialize modules
	 *
	 * @param array $modules_setup 'modules' setup.
	 *
	 * @return void
	 * @see docs/factory-setup.md
	 */
	protected function initialize_modules( array $modules_setup ): void {
		// Handle underscore modules.
		if ( isset( $modules_setup['_'] ) && is_array( $modules_setup['_'] ) ) {
			$underscored_modules = $modules_setup['_'];
			unset( $modules_setup['_'] );
			foreach ( $underscored_modules as $module ) {
				$item = $this->resolve_module( $module );
				if ( is_callable( $item ) ) {
					call_user_func( $item );
				}
			}
		}

		// Handle add_action modules.
		foreach ( $modules_setup as $hook_name => $modules ) {
			$accepted_args = $modules['accepted_args'] ?? 1;
			unset( $modules['accepted_args'] );
			foreach ( $modules as $priority => $group ) {
				if ( is_numeric( $priority ) ) {
					$priority = (int) $priority;
					foreach ( $group as $module ) {
						$item = $this->resolve_module( $module );
						if ( is_callable( $item ) ) {
							add_action( $hook_name, $item, $priority, $accepted_args );
						}
					}
				}
			}
		}
	}

	/**
	 * Resolve module
	 *
	 * @param string $module
	 *
	 * @return callable|null
	 */
	protected function resolve_module( string $module ): callable|null {

	}
}
