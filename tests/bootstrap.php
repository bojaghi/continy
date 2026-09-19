<?php
/**
 * Continy test bootstrap
 *
 * @package Bojaghi\Continy\Tests
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../vendor/wordpress-autoload.php';
require_once __DIR__ . '/stubs/detect-targets.php';
require_once __DIR__ . '/stubs/test-classes.php';

/**
 * Some wordpress built-in function overrides
 */
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $string ): string {
		return $string;
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
