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
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionUnionType;

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
	 * All binding items
	 *
	 * @var array
	 */
	protected array $bindings;

	/**
	 * Array of alias, succeeded in finding a bound item.
	 *
	 * Key: 'as' value or FQCN.
	 * Value: alias, or FQCN.
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
		$this->bindings = array();
		$this->resolved = array( __CLASS__ => 'continy' );
		$this->storage  = array( __CLASS__ => $this );

		$this->initialize_bindings( $args['bindings'] ?? array() );
		$this->initialize_modules( $args['modules'] ?? array() );
	}

	public function get( string $id ) {
		// TODO: Implement get() method.
	}

	public function has( string $id ): bool {
		return isset( $this->resolved[ $id ] );
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
	 *
	 * @throws Continy_Exception When multiple aliases are mapped to one class.
	 */
	protected function initialize_bindings( array $bindings_setup ): void {
		$default = self::get_default_binding_array();

		// Handle setup items.
		foreach ( $bindings_setup as $alias => $setup ) {
			if ( is_string( $setup ) ) {
				$setup = array(
					...$default,
					array( 'as' => $setup ),
				);
			}

			if ( ! is_array( $setup ) ) {
				continue;
			}

			if ( wp_is_numeric_array( $setup ) ) {
				$setup = array_map( fn( $s ) => wp_parse_args( $s, $default ), $setup );
			} else {
				$setup = array( wp_parse_args( $setup, $default ) );
			}

			// Fill resolved property.
			foreach ( $setup as $item ) {
				if ( isset( $item['as'] ) ) {
					throw new Continy_Exception( 'You are trying to map multiple aliases to one class.' );
				}
				$this->resolved[ $item['as'] ] = $alias;
			}

			$this->bindings[ $alias ] = $setup;
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
	protected function instantiate( string $id, string $when = '' ): mixed {
		$binding = $this->get_binding( $id );
		if ( ! $binding ) {
			throw new Continy_Not_Found_Exception( esc_html( "'$id' does not exist." ) );
		}

		// Filter by 'when'
		if ( 1 === count( $binding ) ) {
			$binding = array_shift( $binding );
		} elseif ( count( $binding ) > 1 && $when ) {
			$binding = array_find( $binding, fn( $b ) => $b['when'] === $when );
			if ( ! $binding ) {
				throw new Continy_Not_Found_Exception( esc_html( "'when' value of '$id' is invalid." ) );
			}
		}

		if ( $binding['verbatim'] ) {
			// Verbatim returns the value itself.
			return $binding['verbatim'];
		}

		$args  = $binding['args'];
		$fqcn  = $binding['as'];
		$reuse = $binding['reuse'];

		// Re-use.
		if ( $reuse && isset( $this->storage[ $fqcn ] ) ) {
			return $this->storage[ $fqcn ];
		}

		// Detect construct parameter.

		// instantiate using new keyword

		// store it.

		// return.
	}

	protected function _create_instance( string $fqcn, array $args = array() ) {

	}

	/**
	 * @param callable|array|string $target
	 * @param array                 $given
	 *
	 * @return void
	 * @throws ReflectionException Reflection failed.
	 */
	protected function detect_params( callable|array|string $target ) {
		if ( is_string( $target ) && class_exists( $target ) ) {
			$reflection  = new ReflectionClass( $target );
			$constructor = $reflection->getConstructor();
			$parameters  = $constructor ? $constructor->getParameters() : [];
		} elseif ( is_callable( $target ) ) {
			if ( is_array( $target ) && 2 === count( $target ) ) {
				$reflection = new ReflectionMethod( $target[0], $target[1] );
			} else {
				$reflection = new ReflectionFunction( $target );
			}
			$parameters = $reflection->getParameters();
		} else {
			throw new ReflectionException( 'Invalid target' );
		}

		foreach ( $parameters as $parameter ) {
			$name = $parameter->getName();

			if ( $parameter->getType() instanceof ReflectionUnionType ) {
				$union_types = $parameter->getType()->getTypes();

				if ( $parameter->isOptional() ) {
					$default_value      = $parameter->getDefaultValue();
					$default_value_type = is_scalar( $default_value ) ? gettype( $default_value ) : get_class( $default_value );

					foreach ( $union_types as $union_type ) {

					}
				} else {
					foreach ( $union_types as $union_type ) {
						if ( $union_type->allowsNull() && str_starts_with( $union_type->getName(), '?' ) ) {
							substr($union_type->getName(), 1);
						} else {
							$union_type->getName();
						}
					}
				}
			} else {

			}

			//
		}
	}

	/**
	 * Get binding by given $id
	 *
	 * @param string $id bound item to search for.
	 *
	 * @return array|null
	 */
	protected function get_binding( string $id ): ?array {
		if ( isset( $this->resolved[ $id ] ) && $id !== $this->resolved[ $id ] ) {
			// $id may be a class string, and may be already resolved.
			// Set to its alias.
			$id = $this->resolved[ $id ];
		}

		if ( isset( $this->bindings[ $id ] ) ) {
			// id is mapped to bindings.
			return $this->bindings[ $id ];
		}

		// We cannot find the binding. Add dynamically.
		// $id can be a class string that autoloader can include right now.
		if ( class_exists( $id ) ) {
			$this->resolved[ $id ] = $id;
			$this->bindings[ $id ] = array(
				...static::get_default_binding_array(),
				'as' => $id,
			);

			return $this->bindings[ $id ];
		}

		// Note: binding is not for functions, methods, or callable strings.
		// Give up.
		return null;
	}

	private static function get_default_binding_array(): array {
		return array(
			'when'     => null,
			'as'       => null,
			'args'     => null,
			'reuse'    => true,
			'verbatim' => null,
		);
	}
}
