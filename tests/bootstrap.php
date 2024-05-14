<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package Continy_Sample
 */
$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
    $_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
$_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $_phpunit_polyfills_path ) {
    define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path );
}

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
    echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    exit( 1 );
}

// Give access to tests_add_filter() function.
require_once "{$_tests_dir}/includes/functions.php";

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin(): void {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
    require_once dirname(__DIR__) . '/tests/DummyPlugin/dummy-plugin.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

function getAccessibleMethod(string $className, string $methodName): ?\ReflectionMethod
{
    $method = null;

    if (class_exists($className) && method_exists($className, $methodName)) {
        try {
            $reflection = new \ReflectionClass($className);
            $method = $reflection->getMethod($methodName);
            $method->setAccessible(true);
        } catch (\ReflectionException $e) {
        }
    }

    return $method;
}

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";