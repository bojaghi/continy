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
use ReflectionException;

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
	 * Detector instance
	 *
	 * @var Continy_Param_Detector
	 */
	protected Continy_Param_Detector $detector;

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
	 * Flag for initialization.
	 *
	 * @var bool
	 */
	protected bool $is_initialized;

	/**
	 * Explicit types: thes types cannot be referenced dynamically.
	 */
	protected array $explicit_types = array(
		'bool',
		'int',
		'float',
		'double',
		'string',
		'array',
		'object',
		'resource',
		'callable',
	);

	protected array $instantiation_stack;

	/**
	 * Continy constructor
	 *
	 * @param array $args Setup array.
	 *
	 * @throws Continy_Exception Throws when binding fails.
	 *
	 * @see docs/factory-setup.md
	 */
	public function __construct( array $args = array() ) {
		$this->is_initialized = false;
		$this->bindings       = array();
		$this->detector       = new Continy_Param_Detector();
		$this->resolved       = array( __CLASS__ => 'continy' );
		$this->storage        = array( __CLASS__ => $this );

		$this->initialize_bindings( $args['bindings'] ?? array() );
		$this->initialize_modules( $args['modules'] ?? array() );

		// Now it is ready!
		$this->is_initialized      = true;
		$this->instantiation_stack = array();
	}

	/**
	 * @template T
	 * @param string|class-string<T> $id QCN or ID that continy can find the binding.
	 *
	 * @return mixed
	 * @throws Continy_Exception
	 * @throws Continy_Not_Found_Exception
	 */
	public function get( string $id ) {
		return $this->instantiate( $id );
	}

	/**
	 * Check if ID is instantiated the container.
	 *
	 * @param string $id
	 *
	 * @return bool
	 */
	public function has( string $id ): bool {
		return isset( $this->storage[ $id ] );
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
	 * @template T
	 * @param string|class-string<T> $id    Identifier of object.
	 * @param mixed|null             $args  Object argument.
	 * @param bool|null              $reuse Reuse the object, or create a new one.
	 *
	 * @return mixed
	 * @throws Continy_Exception When Continy is not fully initialized.
	 * @throws Continy_Not_Found_Exception When object not found.
	 */
	public function instantiate( string $id, mixed $args = null, bool|null $reuse = null ): mixed {
		if ( ! $this->is_initialized ) {
			throw new Continy_Exception( esc_html( 'Continy is not initialized yet.' ) );
		}

		$binding = $this->get_binding( $id );
		if ( ! $binding ) {
			throw new Continy_Not_Found_Exception( esc_html( "'$id' does not have binding." ) );
		}

		// Verbatim.
		if ( isset( $binding['verbatim'] ) ) {
			return $binding['verbatim'];
		}

		if ( is_null( $reuse ) ) {
			$reuse = $binding['reuse'];
		}

		// Re-use
		if ( $reuse && $this->has( $id ) ) {
			return $this->storage[ $id ];
		}

		// Creation
		$class_name = $binding['as'];

		if ( ! $class_name ) {
			throw new Continy_Not_Found_Exception( esc_html( "'$id', Invalid 'as' value." ) );
		} elseif ( is_string( $class_name ) && class_exists( $class_name ) ) {
			if ( $args ) {
				// $args may be callable, array, or string, provided by arguments.
				if ( is_callable( $args ) ) {
					// In this case, the return value of the callable should be an instance.
					$instance = call_user_func_array( $args, array( $id, $class_name, $this ) );
				} else {
					throw new Continy_Not_Found_Exception( esc_html( "'$id', unsupported \$args type." ) );
				}
			} else {
				// $args is null, retrieved from $binding.
				$params = $this->detect_params( $class_name );

				if ( is_callable( $args ) ) {
					$args = call_user_func_array( $args, array( $id, $class_name, $this ) );
				} elseif ( is_string( $args ) ) {
					$args = Helper::load_config( $args );
				} else {
					$args = array();
				}

				if ( ! is_array( $args ) ) {
					throw new Continy_Exception( esc_html( "'$id', unsupported \$args input." ) );
				}

				// Make sure that $args is an indexed array.
				if ( ! empty( $args ) && array_is_list( $args ) ) {
					$args_len   = count( $args );
					$params_len = count( $params );

					if ( $args_len <= $params_len ) {
						$args_copy = array();
						foreach ( array_keys( $params ) as $i => $key ) {
							if ( $i < $args_len ) {
								$args_copy[ $key ] = $args[ $i ];
							}
						}
						$args = $args_copy;
					}
				}

				if ( in_array( $class_name, $this->instantiation_stack ) ) {
					throw new Continy_Exception( esc_html( 'Class name loop found: ' . $class_name ) );
				}

				$this->instantiation_stack[] = $class_name;

				$args = $this->complete_constructor( $params, $args );

				$instance = new $class_name( ... $args );

				$this->instantiation_stack = array_slice( $this->instantiation_stack, 0, -1 );
			}

			if ( $reuse ) {
				$this->storage[ $id ] = $instance;
				if ( $id !== $class_name ) {
					$this->storage[ $class_name ] = $instance;
				}
			}
		} else {
			$instance = $class_name;
		}

		return $instance;
	}

	/**
	 * Drop the instance by ID.
	 *
	 * @param string $id Identifier to forget.
	 *
	 * @return void
	 */
	public function drop( string $id ): void {
		if ( isset( $this->storage[ $id ] ) ) {
			unset( $this->storage[ $id ] );
		}

		if ( isset( $this->resolved[ $id ] ) ) {
			unset( $this->storage[ $this->resolved[ $id ] ] );
		}
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

			if ( array_is_list( $setup ) ) {
				$setup = array_map(
					fn( $s ) => array_intersect_key( array( ...$default, ...$setup ), $default ),
					$setup,
				);
			} else {
				$setup = array( array_intersect_key( array( ...$default, ...$setup ), $default ) );
			}

			// Fill resolved property.
			foreach ( $setup as $item ) {
				if ( $item['as'] ) {
					$key = $item['as'];
				} elseif ( $item['verbatim'] && is_string( $item['verbatim'] ) ) {
					$key = "{continy-verbatim}:" . $alias;
				} else {
					throw new Continy_Exception( esc_html( "'as', or 'verbatim' is required." ) );
				}

				if ( is_object( $key ) ) {
					$key = (string) spl_object_id( $key );
				} else {
					$key = (string) $key;
				}

				if ( isset( $this->resolved[ $key ] ) ) {
					throw new Continy_Exception( 'You are trying to map multiple aliases to one object.' );
				}

				$this->resolved[ $key ] = $alias;
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
	 * @param callable|array|string $target
	 *
	 * @return array|null
	 */
	protected function detect_params( callable|array|string $target ): ?array {
		try {
			return $this->detector->detect( $target );
		} catch ( ReflectionException $_ ) {
			return null;
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
		$output = null;

		if ( isset( $this->bindings[ $id ] ) ) {
			$output = $this->bindings[ $id ];
		} elseif ( isset( $this->resolved[ $id ] ) ) {
			// $id may be a class string, and may be already resolved.
			// Set to its alias.
			$output = $this->bindings[ $this->resolved[ $id ] ];
		} else {
			// We cannot find the binding. Add dynamically.
			// $id can be a class string that autoloader can include right now.
			if ( class_exists( $id ) ) {
				$this->resolved[ $id ] = $id;
				$this->bindings[ $id ] = array(
					array(
						...static::get_default_binding_array(),
						'as' => $id,
					),
				);

				$output = $this->bindings[ $id ];
			}
		}

		$when  = array_last( $this->instantiation_stack );
		$count = $output ? count( $output ) : 0;

		if ( 1 === $count ) {
			return $output[0];
		} elseif ( $count > 1 ) {
			if ( $when ) {
				foreach ( $output as $item ) {
					if ( $item['when'] === $when ) {
						return $item;
					}
				}
			} else {
				foreach ( $output as $item ) {
					if ( ! $item['when'] ) {
						return $item;
					}
				}
			}
		}

		return null; // Give up.
	}

	/**
	 * Complete constructor arguments.
	 *
	 * @param array $params
	 * @param array $args
	 *
	 * @return array
	 * @throws Continy_Exception When reference fails.
	 * @throws Continy_Not_Found_Exception When the type not found.
	 */
	protected function complete_constructor( array $params, array $args ): array {
		if ( count( $params ) <= count( $args ) ) {
			return array_slice( $args, 0, count( $params ) );
		}

		foreach ( $params as $key => $param ) {
			/**
			 * @var array{
			 *     type: string,
			 *     allow_null: bool,
			 *     default: mixed,
			 *     is_optional: bool,
			 * } $param Parameter informaion.
			 */

			if ( array_key_exists( $key, $args ) ) {
				continue;
			}

			$type        = explode( '|', $param['type'] );
			$allow_null  = $param['allow_null'];
			$default     = $param['default'];
			$is_optional = $param['is_optional'];

			if ( count( $type ) > 1 ) {
				// Union types
				$result = null;
				$done   = false;

				foreach ( $type as $t ) {
					if ( 'true' === $t || 'false' === $t ) {
						$result = 'true' === $t;
						$done   = true;
					} elseif ( 'null' === $t ) {
						$result = null;
						$done   = true;
					} elseif ( in_array( $t, $this->explicit_types ) ) {
						if ( $is_optional ) {
							$result = $default;
							$done   = true;
						} elseif ( $allow_null ) {
							$result = null;
							$done   = true;
						}
					} else {
						try {
							$result = $this->instantiate( $t );
						} catch ( Continy_Exception $_ ) {
							$result = null;
							$done   = false;
						}
					}
					if ( $done ) {
						break;
					}
				}

				if ( ! $done ) {
					throw new Continy_Exception(
						esc_html( sprintf( "Could not instantiate union type '%s'.", $param['type'] ) ),
					);
				}

				$args[ $key ] = $result;
			} else {
				$type = $type[0];

				if ( 'true' === $type || 'false' === $type ) {
					$args[ $key ] = 'true' === $type;
				} elseif ( 'null' === $type ) {
					$args[ $key ] = null;
				} elseif ( in_array( $type, $this->explicit_types ) ) {
					if ( $is_optional ) {
						$args[ $key ] = $default;
					} elseif ( $allow_null ) {
						$args[ $key ] = null;
					} else {
						throw new Continy_Exception( esc_html( $type . ' cannot be referenced.' ) );
					}
				} else {
					$args[ $key ] = $this->instantiate( $type );
				}
			}
		}

		return $args;
	}

	/**
	 * Get the default binding array form.
	 *
	 * @return array
	 */
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
