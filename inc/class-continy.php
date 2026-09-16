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
	 * Array of alias, succeeded in finding bound item.
	 *
	 * Key: FQCN
	 * Value: alias
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
		if ( $this->has( $id ) && $reuse ) {
			return $this->resolved[ $id ];
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
		// TODO: accomplish resolved property.

		$default = self::get_default_binding_array();

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

		// id 찾는 조건
		// 우선 인자가 분명히 string으로 못박혀 있으므로 다른 경우는 불가능
		// - alias
		// - FQCN 인데, binding 된 것일 수도 있고
		// - FQCN 인데, unbound 된 경우
		// - 기타 string
		//
		// -- alias: 바로 binding에 있으므로 원하는 설정 찾을 수 있음
		// -- FQCN, but bound -> 이 경우 때문에 binding 될 때 미리 FQCN 따로 수집해야 함
		// -- FQCN, unbound   -> 즉시 컨테이너가 만들어서 보간해야 함
		// -- 기타 문자열은 parse 시도해 봐서 콜백 같은 걸로 걸리면 해동 콜백을 리턴
		//
		// 또한 binding 이라도 어떤 의존성 걸리는 조건도 파악해 두어야 함
		// binding 에서 verbatim은 가장 간단한 경우
		// when 에서 어떤 클래스가 생성자로 요청하는지 조건이 걸림
		// as 는 반드시 FQCN 이어야 함
		// reuse 는 새 객체인지 stored 된 것도 허용하는지
		// 즉 ... binding 찾고 해당 값 분석을 먼저 해야
		// 객체를 생성할지 재사용할지를 판단할 수 있음.
		//
		// 오호라! get_binding( $id ) 로 찾아야 한다
		// binding을 찾아내면 (컨테이너가 즉석에서 만들든 어쩌든)
		// 해당 정보를 바탕으로 조건대로 처리한다
	}

	/**
	 * Get binding by given $id
	 *
	 * @param string $id bound item to search for.
	 *
	 * @return array|null
	 */
	protected function get_binding( string $id ): ?array {
		// id is mapped to bindings.
		if ( isset( $this->bindings[ $id ] ) ) {
			$this->resolved[ $id ] = $id;

			return $this->bindings[ $id ];
		}

		// FQCN is resolved and then we can get the id.
		if (
			( $alt_id = $this->resolved[ $id ] ?? false ) &&
			$alt_id !== $id &&
			isset( $this->bindings[ $alt_id ] )
		) {
			return $this->bindings[ $alt_id ];
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

		// Note: binding is not for functions, methods, or callable strings
		//       which are normally we can call them directly.
		//
		// Give up.
		return null;
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
