<?php
/**
 * Continy
 *
 * @package Bojaghi\Continy
 */

declare( strict_types=1 );

namespace Bojaghi\Continy;

use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
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
		$types  = $param->getType()->getTypes();
		$output = array();

		if ( $param->isOptional() ) {
			$default_value      = $param->getDefaultValue();
			$default_value_type = is_scalar( $default_value ) ? gettype( $default_value ) : get_class( $default_value );

			if ( array_any( $types, fn( $t ) => $t->getName() === $default_value_type ) ) {
				$output = array(
					'type'  => is_scalar( $default_value ) || is_object( $default_value ) ? 'value' : 'data_type',
					'value' => $default_value,
				);
			}
		} else {
			$buffered = array_map( fn( $type ) => self::strip_question_mark( $type ), $types );
			$output   = array(
				'type'  => 'data_type',
				'value' => implode( '|', $buffered ),
			);
		}

		return $output;
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
		$type = $param->getType();

		if ( $type->isBuiltin() ) {
			if ( $param->isOptional() ) {
				$output = array(
					'type'  => 'value',
					'value' => $param->getDefaultValue(),
				);
			} elseif ( $param->allowsNull() ) {
				$output = array(
					'type'  => 'value',
					'value' => null,
				);
			} else {
				throw new ReflectionException(
					esc_html(
						sprintf(
							"Error while detecting parameter '%s'. Built-in type should have default value or can be nullish, or invoke an explicit injection function.",
							$param->getName(),
						),
					),
				);
			}
		} else {
			$output = array(
				'type'  => 'data_type',
				'value' => self::strip_question_mark( $type ),
			);
		}

		return $output;
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
		if ( ( is_string( $target ) && class_exists( $target ) ) || is_object( $target ) ) {
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

	/**
	 * Strip out heading question mark
	 *
	 * @param ReflectionIntersectionType|ReflectionNamedType|ReflectionUnionType $type Input type.
	 *
	 * @return string
	 */
	protected static function strip_question_mark(
		ReflectionIntersectionType|ReflectionNamedType|ReflectionUnionType $type,
	): string {
		return $type->allowsNull() && str_starts_with( $type->getName(), '?' ) ?
			substr( $type->getName(), 1 ) : $type->getName();
	}
}
