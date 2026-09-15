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
	 * Array of FQCN, that are resolved
	 *
	 * Key: alias
	 * Value: FQCN
	 *
	 * @var array
	 */
	protected array $resolved;

	/**
	 * Continy storage.
	 *
	 * @var array
	 *
	 * Key: FQCN
	 * Value: object
	 */
	protected array $storage;

	/**
	 * Continy constructor
	 *
	 * @param array $args Setup array.
	 *
	 * @see docs/factory-setup.md
	 */
	public function __construct( array $args = array() ) {
		$this->aliases  = array();
		$this->bindings = array();
		$this->resolved = array(
			'continy' => __CLASS__,
			__CLASS__ => __CLASS__,
		);
		$this->storage  = array( __CLASS__ => $this );

		$this->initialize_bindings( $args['bindings'] ?? array() );
		$this->initialize_modules( $args['modules'] ?? array() );
	}

	public function get( string $id ) {
		// TODO: Implement get() method.
	}

	public function has( string $id ): bool {
		return class_exists( $id ) || isset( $this->resolved[ $id ] );
	}

	public function call( callable|array|string $to_call, mixed $args = null ): mixed {
		// TODO: Implement call() method.
		return null;
	}

	public function parse_callback( callable|array|string $callback ): ?callable {
		return null;
	}

	/**
	 * Create and spawn object by its id
	 *
	 * @param string     $id    Identifier of object.
	 * @param mixed|null $args  Object argument.
	 * @param bool       $reuse Reuse the object, or create a new one.
	 *
	 * @return mixed
	 * @throws Continy_Not_Found_Exception When object not found.
	 */
	public function spawn( string $id, mixed $args = null, bool $reuse = true ): mixed {
		if ( ! $this->has( $id ) ) {
			// TODO: try to resolve it

			throw new Continy_Not_Found_Exception( esc_html( "'$id' is not a valid binding id." ) );
		}

		$binding = $this->bindings[ $id ];

		if ( ! empty( $binding['verbatim'] ) ) {
			return $binding['verbatim'];
		}

		return null;
	}

	public function forget( string $id ): void {
		// TODO
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
				if ( static::is_numeric_array( $setup ) ) {
					$nested = array();
					foreach ( $setup as $value ) {
						$nested[] = wp_parse_args( $value, $default );
					}
					$setup = $nested;
				} else {
					$setup = array( wp_parse_args( $setup, $default ) );
				}
			} else {
				continue;
			}

			$this->bindings[ $alias ] = $setup;

			// Remember aliases by 'as' key value.
			// Continy may need them when users query instances by FQCN, not by aliases.
			// Users should expect proper objects with dependency injection  no matter how they query.
			foreach ( $setup as $s ) {
				if ( $s['as'] && $s['as'] !== $alias ) {
					$this->aliases[ $s['as'] ] = $alias;
					$this->resolved[ $alias ]  = $s['as'];
				} elseif ( $s['verbatim'] ) {
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
				$callback = $this->get_action_callback( $module );
				if ( is_callable( $callback ) ) {
					call_user_func( $callback );
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
						$callback = $this->get_action_callback( $module );
						if ( is_callable( $callback ) ) {
							add_action( $hook_name, $callback, $priority, $accepted_args );
						}
					}
				}
			}
		}
	}

	/**
	 * Resolve module
	 *
	 * @param callable|string $id Module ID.
	 *
	 * @return callable|null
	 */
	protected function get_action_callback( callable|string $id ): callable|null {
		return function () use ( $id ): void {
			$args = func_get_args();

			// Any callables: function, string, array form.
			if ( is_callable( $id ) ) {
				call_user_func_array( $id, $args );
			} else {
				// Only string remains. Analyze it.
				$split = explode( '@', $id, 2 );
				$count = count( $split );

				try {
					if ( 1 === $count ) {
						// $id is alias, FQCN.
						$this->instantiate( $split[0] );
					} else {
						// Count is two.
						$real_callback = array( $this->instantiate( $split[0] ), $split[1] );
						if ( is_callable( $real_callback ) ) {
							call_user_func_array( $real_callback, $args );
						}
					}
				} catch ( Continy_Exception $e ) {
					wp_die( esc_html( $e->getMessage() ) );
				}
			}
		};
	}

	/**
	 * Try to instantiate
	 *
	 * @param string $id Given id to find.
	 *
	 * @return mixed
	 *
	 * @throws Continy_Not_Found_Exception When ID is not found.
	 */
	protected function instantiate( string $id ): mixed {
		$fqcn = $this->resolve( $id );
		if ( ! $fqcn ) {
			throw new Continy_Not_Found_Exception( esc_html( "'$id' does not exist." ) );
		}
	}

	protected function resolve( string $id ): string {
		if ( ! isset( $this->resolved[ $id ] ) ) {
			$fqcn                  = $this->find_fqcn( $id );
			$this->resolved[ $id ] = $fqcn;
			if ( $id !== $fqcn ) {
				$this->resolved[ $fqcn ] = $fqcn;
			}
		}

		return $this->resolved[ $id ];
	}

	protected function find_fqcn( string $id ): string {
		// bindings ...
	}

	/**
	 * Check if is numeric array, not associative array.
	 *
	 * @param array $input Input array.
	 *
	 * @return bool
	 */
	private static function is_numeric_array( array $input ): bool {
		return array_reduce(
			array_keys( $input ),
			fn( $carry, $item ) => $carry && is_int( $item ),
			true,
		);
	}
}
