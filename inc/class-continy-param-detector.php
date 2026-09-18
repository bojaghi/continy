<?php
/**
 * Continy
 *
 * @package Bojaghi\Continy
 */

declare( strict_types=1 );

namespace Bojaghi\Continy;

use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionUnionType;

/**
 * Continy param detector class
 */
class Continy_Param_Detector {
	/**
	 * Detect parameter
	 *
	 * @param array|callable|string|object $target Target to detect.
	 *                                             Target can be class FQN, class method, function, ond so on.
	 *
	 * @return array
	 *
	 * @throws ReflectionException Thrown when reflection fails.
	 */
	public function detect( array|callable|string|object $target ): array {
		$params = $this->get_parameters( $target );
		$output = array();

		foreach ( $params as $param ) {
			$output[ $param->getName() ] = $param->getType() instanceof ReflectionUnionType ?
				$this->detect_union_type( $param ) :
				$this->detect_singular_type( $param );
		}

		return $output;
	}

	/**
	 * Detect union type parameter
	 *
	 * Union type like, __construct( bool|int|string $var = false )
	 *
	 * @param ReflectionParameter $param Parameter to detect.
	 *
	 * @return array
	 *
	 * @throws ReflectionException Thrown when reflection fails.
	 */
	public function detect_union_type( ReflectionParameter $param ): array {
		$buffered = array_map(
			fn( $type ) => $type->getName(), // In union type, '?' prefix is invalid.
			$param->getType()->getTypes(),
		);

		return array(
			'type'        => implode( '|', $buffered ),
			'allow_null'  => $param->allowsNull(),
			'default'     => $param->isOptional() ? $param->getDefaultValue() : null,
			'is_optional' => $param->isOptional(),
		);
	}

	/**
	 * Detect against singular parameter
	 *
	 * @param ReflectionParameter $param Parameter to detect.
	 *
	 * @return array
	 * @throws ReflectionException Thrown when detection fails.
	 */
	public function detect_singular_type( ReflectionParameter $param ): array {
		return array(
			'type'        => $param->getType()?->getName(),
			'allow_null'  => $param->allowsNull(),
			'default'     => $param->isOptional() ? $param->getDefaultValue() : null,
			'is_optional' => $param->isOptional(),
		);
	}

	/**
	 * Get parameter of $target
	 *
	 * @param array|callable|string|object $target Target to inspect.
	 *
	 * @return ReflectionParameter[]
	 * @throws ReflectionException Thrown when reflection fails.
	 */
	public function get_parameters( array|callable|string|object $target ): array {
		if ( $target instanceof Closure ) {
			$ref    = new ReflectionFunction( $target );
			$params = $ref->getParameters();
		} elseif ( ( is_string( $target ) && class_exists( $target ) ) || is_object( $target ) ) {
			$ref    = new ReflectionClass( $target );
			$cons   = $ref->getConstructor();
			$params = $cons ? $cons->getParameters() : array();
		} elseif ( is_callable( $target ) ) {
			if ( self::is_class_method( $target ) ) {
				$ref = new ReflectionMethod( $target[0], $target[1] );
			} else {
				$ref = new ReflectionFunction( $target );
			}
			$params = $ref->getParameters();
		} else {
			throw new ReflectionException( 'Invalid target' );
		}

		return $params;
	}

	/**
	 * Tell if $target is class method form or not.
	 *
	 * @param array|callable|string $target Taret to check.
	 *
	 * @return bool
	 */
	protected static function is_class_method( array|callable|string $target ): bool {
		return is_array( $target ) && 2 === count( $target );
	}
}
