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
	 * Sub-storage
	 *
	 * It is storage for managing conflicts.
	 *
	 * @var array
	 */
	protected array $sub_storage;

	/**
	 * Flag for initialization of Continy.
	 *
	 * @var bool
	 */
	protected bool $is_initialized;

	/**
	 * Explicit types: these types cannot be referenced dynamically.
	 *
	 * @var string[]
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

	/**
	 * Stack of currently creating classes.
	 *
	 * @var array
	 */
	protected array $instantiate_stack;

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
		$this->sub_storage    = array();

		$this->initialize_bindings( $args['bindings'] ?? array() );
		$this->initialize_modules( $args['modules'] ?? array() );

		// Now it is ready!
		$this->is_initialized    = true;
		$this->instantiate_stack = array();
	}

	/**
	 * Create an instance
	 *
	 * @template T
	 * @param string|class-string<T> $id QCN or ID that continy can find the binding.
	 *
	 * @return mixed
	 * @throws Continy_Exception When Continy is not fully initialized.
	 * @throws Continy_Not_Found_Exception When object not found.
	 */
	public function get( string $id ): mixed {
		return $this->instantiate( $id );
	}

	/**
	 * Check if ID is instantiated the container.
	 *
	 * @param string $id ID to check.
	 *
	 * @return bool
	 */
	public function has( string $id ): bool {
		return isset( $this->storage[ $id ] );
	}

	/**
	 * Call method or function with dependency injection
	 *
	 * @param callable|array|string $to_call Class method, function, or any callables.
	 * @param mixed|null            $args    Basic argument.
	 *
	 * @return mixed
	 * @uses parse_callback
	 * @throws Continy_Exception When callback cannot be invoked.
	 */
	public function call( callable|array|string $to_call, mixed $args = null ): mixed {
		$parsed = $this->parse_callback( $to_call );

		if ( $args && is_callable( $args ) ) {
			$args = (array) call_user_func_array( $args, array( $to_call, $this ) );
		} else {
			$args = (array) $args;
		}

		// Detect and merge $args.
		$params     = $this->detect_params( $parsed );
		$params_len = count( $params );
		$args       = (array) $args;
		$args_len   = count( $args );

		if ( $args_len < $params_len && array_is_list( $args ) ) {
			$args_copy = array();
			foreach ( array_keys( $params ) as $i => $key ) {
				if ( $i < $args_len ) {
					$args_copy[ $key ] = $args[ $i ];
				}
			}
			$args = $args_copy;
		}

		$args = $this->complete_constructor( $params, $args );

		if ( ! is_callable( $parsed ) ) {
			throw new Continy_Exception( '$to_call is not callable.' );
		} elseif ( ! is_array( $args ) ) {
			throw new Continy_Exception( '$args is not callable nor an array.' );
		}

		return call_user_func_array( $parsed, $args );
	}

	/**
	 * Parse callback
	 *
	 * '<id>@<method>' string is available.
	 * 'id' may be an identifier provided in 'bindings', or FQCN.
	 *
	 * @param callable|array|string $callback Any callables, or string to parse.
	 *
	 * @return callable|array|string|null
	 *
	 * @throws Continy_Exception When get() fails.
	 * @throws Continy_Not_Found_Exception When ID not found.
	 */
	public function parse_callback( callable|array|string $callback ): callable|array|string|null {
		if ( is_callable( $callback ) ) {
			return $callback;
		} elseif ( is_array( $callback ) && 2 === count( $callback ) ) {
			// e.g. array( 'string', 'string' );
			// 0th is not a class instance. If it was, then is_callable( $callback ) should return true.
			[ $cls, $method ] = $callback;
		} elseif ( is_string( $callback ) && str_contains( $callback, '@' ) ) {
			[ $cls, $method ] = explode( '@', $callback, 2 );
		}

		if ( isset( $cls, $method ) ) {
			$instance = $this->get( $cls );
			if ( is_callable( array( $instance, $method ) ) ) {
				return array( $instance, $method );
			}
		}

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

		/**
		 * Binding setup
		 *
		 * Continy has a binding array that can be identified by $id.
		 *
		 * @var array|null $binding_group
		 */
		$binding_group = $this->get_binding_group( $id );
		if ( ! $binding_group ) {
			throw new Continy_Not_Found_Exception( esc_html( "'$id' does not have binding group." ) );
		}

		/**
		 * Binding item
		 *
		 * Extract one setup item by given condition.
		 *
		 * @var array|null $binding
		 */
		$binding = $this->get_binding_item( $binding_group );
		if ( ! $binding ) {
			throw new Continy_Not_Found_Exception( esc_html( "'$id' does not have a valid binding item." ) );
		}

		// Verbatim.
		if ( isset( $binding['verbatim'] ) ) {
			return $binding['verbatim'];
		}

		if ( is_null( $reuse ) ) {
			$reuse = $binding['reuse'];
		}

		// Re-use it.
		if ( $reuse ) {
			if ( $this->has( $id ) ) {
				return $this->storage[ $id ];
			} elseif ( count( $binding_group ) > 1 && ! $binding['when'] && isset( $this->sub_storage[ $id ] ) ) {
				return $this->sub_storage[ $id ];
			}
		}

		$class_name = $binding['as'];

		// Create a new instance.
		if ( empty( $class_name ) ) {
			throw new Continy_Not_Found_Exception( esc_html( "'$id', Invalid 'as' value." ) );
		} elseif ( is_string( $class_name ) && class_exists( $class_name ) ) {
			$instance = null;

			if ( $args && is_callable( $args ) ) {
				// In this case, the return value of the callable should be an instance.
				$instance = call_user_func_array( $args, array( $id, $class_name, $this ) );
			}

			if ( ! $instance ) {
				// $args is null, retrieved from $binding.
				$params = $this->detect_params( $class_name );

				if ( $args ) {
					$args = (array) $args;
				} elseif ( is_callable( $binding['args'] ) ) {
					$args = call_user_func_array( $binding['args'], array( $id, $class_name, $this ) );
				} else {
					$args = (array) ( $binding['args'] ?? array() );
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

				if ( in_array( $class_name, $this->instantiate_stack, true ) ) {
					throw new Continy_Exception( esc_html( 'Class name loop found: ' . $class_name ) );
				}

				$this->instantiate_stack[] = $class_name;

				$args = $this->complete_constructor( $params, $args );

				$instance = new $class_name( ...$args );

				array_pop( $this->instantiate_stack );
			}

			if ( $reuse ) {
				if ( 1 === count( $binding_group ) ) {
					$this->storage[ $id ] = $instance;
				} elseif ( count( $binding_group ) > 1 && ! $binding['when'] ) {
					// If binding group has multiple items, it is conditional setup.
					// Store only the default item, because eventually 'when' object will have the instance.
					$this->sub_storage[ $id ] = $instance;
				}
				if ( $id !== $class_name ) {
					// User is calling this method by alias.
					$this->storage[ $class_name ] = $instance;
				} elseif (
					isset( $this->resolved[ $id ] ) &&
					isset( $this->bindings[ $this->resolved[ $id ] ] ) &&
					! isset( $this->storage[ $this->resolved[ $id ] ] )
				) {
					// User entered a valid binding, but the user is calling this method by class name, not its alias.
					// In this case, $id is class name, and $this->resolved[ $id ] is alias.
					$this->storage[ $this->resolved[ $id ] ] = $instance;
				}
			}
		} else {
			// Any constant value like string, number, and so on. Return itself as-is.
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
		unset( $this->storage[ $id ], $this->sub_storage[ $id ] );

		if ( isset( $this->resolved[ $id ] ) ) {
			unset( $this->storage[ $this->resolved[ $id ] ] );
		}

		// Key of alias is removed, and search for its class name..
		$pos = array_search( $id, $this->resolved, true );
		if ( false !== $pos && class_exists( $pos ) ) {
			unset( $this->storage[ $pos ] );
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
					'as' => $setup,
				);
			}

			if ( ! is_array( $setup ) ) {
				continue;
			}

			if ( array_is_list( $setup ) ) {
				$setup = array_map(
					fn( $s ) => array_intersect_key( array( ...$default, ...$s ), $default ),
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
					$key = "{continy-verbatim}:$alias";
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
		if ( is_callable( $id ) ) {
			return $id;
		}

		return function () use ( $id ): void {
			$args = func_get_args();

			// Any callables: function, string, array form.
			if ( is_callable( $id ) ) {
				call_user_func_array( $id, $args );
			} else {
				// Only string remains. Analyze it.
				$has_at_sign = str_contains( $id, '@' );

				try {
					if ( ! $has_at_sign ) {
						// $id is alias, FQCN.
						$this->instantiate( $id, $args );
					} else {
						$real_callback = $this->parse_callback( $id );
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
	 * Detect parameter of class constructor, or callables
	 *
	 * @param callable|array|string $target Class names, functions, or any callables.
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
	protected function get_binding_group( string $id ): ?array {
		$output = null;

		if ( isset( $this->bindings[ $id ] ) ) {
			$output = $this->bindings[ $id ];
		} elseif ( isset( $this->resolved[ $id ] ) ) {
			// $id may be a class string, and may be already resolved.
			// Set to its alias.
			$output = $this->bindings[ $this->resolved[ $id ] ];
		} elseif ( class_exists( $id ) ) {
			// We cannot find the binding. Add dynamically.
			// $id can be a class string that autoloader can include right now.
			$this->resolved[ $id ] = $id;
			$this->bindings[ $id ] = array(
				array(
					...static::get_default_binding_array(),
					'as' => $id,
				),
			);

			$output = $this->bindings[ $id ];
		}

		return $output;
	}

	/**
	 * Choose appropriate binding item
	 *
	 * @param array $binding_setup Array returned by get_binding().
	 *
	 * @return ?array Extracted binding item.
	 */
	protected function get_binding_item( array $binding_setup ): ?array {
		$count = count( $binding_setup );

		if ( 1 === $count ) {
			return $binding_setup[0];
		} elseif ( $count > 1 ) {
			$when = array_last( $this->instantiate_stack );

			// Find matching item.
			if ( $when ) {
				foreach ( $binding_setup as $binding ) {
					if ( $binding['when'] === $when ) {
						return $binding;
					}
				}
			}

			// If 'when' is null, it is the default fallback item.
			foreach ( $binding_setup as $binding ) {
				if ( ! $binding['when'] ) {
					return $binding;
				}
			}
		}

		return null; // Give up.
	}

	/**
	 * Complete constructor arguments.
	 *
	 * @param array $params A list of parameters returned by detector.
	 * @param array $args   Current arguments.
	 *
	 * @return array
	 * @throws Continy_Exception When reference fails.
	 */
	protected function complete_constructor( array $params, array $args ): array {
		if ( count( $params ) <= count( $args ) ) {
			return array_slice( $args, 0, count( $params ) );
		}

		foreach ( $params as $key => $param ) {
			/**
			 * Each $param information
			 *
			 * @var array{
			 *     type: string,
			 *     allow_null: bool,
			 *     default: mixed,
			 *     is_optional: bool,
			 * } $param
			 */

			if ( array_key_exists( $key, $args ) ) {
				continue;
			}

			$type        = explode( '|', $param['type'] ?? '' );
			$allow_null  = $param['allow_null'];
			$default     = $param['default'];
			$is_optional = $param['is_optional'];

			if ( count( $type ) > 1 ) {
				// Union types.
				$result = null;
				$done   = false;

				foreach ( $type as $t ) {
					if ( 'true' === $t || 'false' === $t ) {
						$result = 'true' === $t;
						$done   = true;
					} elseif ( 'null' === $t ) {
						$result = null;
						$done   = true;
					} elseif ( in_array( $t, $this->explicit_types, true ) ) {
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

				if ( empty( $type ) ) {
					throw new Continy_Exception( esc_html( "'$key' does not have type. It cannot be referenced." ) );
				} elseif ( 'true' === $type || 'false' === $type ) {
					$args[ $key ] = 'true' === $type;
				} elseif ( 'null' === $type ) {
					$args[ $key ] = null;
				} elseif ( in_array( $type, $this->explicit_types, true ) ) {
					if ( $is_optional ) {
						$args[ $key ] = $default;
					} elseif ( $allow_null ) {
						$args[ $key ] = null;
					} else {
						throw new Continy_Exception( esc_html( "'$type $key' cannot be referenced." ) );
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
