<?php
/**
 * Continy test bootstrap
 *
 * @package Bojaghi\Continy\Tests
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../vendor/wordpress-autoload.php';
require_once __DIR__ . '/stubs/detect-targets.php';

/**
 * Some wordpress built-in function overrides
 */
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $string ): string {
		return $string;
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = array() ): array {
		if ( is_object( $args ) ) {
			$parsed_args = get_object_vars( $args );
		} elseif ( is_array( $args ) ) {
			$parsed_args =& $args;
		} else {
			wp_parse_str( $args, $parsed_args );
		}

		if ( is_array( $defaults ) && $defaults ) {
			return array_merge( $defaults, $parsed_args );
		}

		return $parsed_args;
	}
}

if ( ! function_exists( 'wp_parse_str' ) ) {
	function wp_parse_str( $input_string, &$result ) {
		parse_str( (string) $input_string, $result );

		return $result;
	}
}

if ( ! function_exists( 'array_last' ) ) {
	/**
	 * Polyfill for `array_last()` function added in PHP 8.5.
	 *
	 * Returns the last element of an array.
	 *
	 * @since 6.9.0
	 *
	 * @param array $array The array to get the last element from.
	 * @return mixed|null The last element of the array, or null if the array is empty.
	 */
	function array_last( array $array ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.arrayFound
		if ( empty( $array ) ) {
			return null;
		}

		return $array[ array_key_last( $array ) ];
	}
}
