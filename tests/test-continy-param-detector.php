<?php

namespace Bojaghi\Continy\Tests;

use Bojaghi\Continy\Continy_Param_Detector;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionParameter;

/**
 * Continy_Param_Detector UnitTest
 */
class Continy_Param_Detector_Test extends TestCase {
	/**
	 * Detector instance
	 *
	 * @var Continy_Param_Detector
	 */
	protected Continy_Param_Detector $detector;

	/**
	 * Setup method
	 *
	 * @return void
	 */
	public function setUp(): void {
		$this->detector = new Continy_Param_Detector();
	}

	/**
	 * Test get_parameters method
	 *
	 * @return void
	 * @throws ReflectionException
	 */
	public function test_get_parameters(): void {
		// No constructor, returns an empty array.
		$actual = $this->detector->get_parameters( Param_Detector_Test_Class_No_Constructor::class );
		$this->assertEmpty( $actual, 'Class with no constructor.' );

		// Constructor with no parameters, returns an empty array.
		$actual = $this->detector->get_parameters( Param_Detector_Test_Class_Blank_Constructor::class );
		$this->assertEmpty( $actual, 'Constructor with no parameters.' );

		// Using instance. It's okay to confirm that Reflection just works only.
		$instance = new Param_Detector_Test_Class_No_Constructor();
		$this->assertEmpty( $this->detector->get_parameters( $instance ) );

		// Constructor params.
		$actual = $this->detector->get_parameters( Untyped_Two_Params_Class::class );
		$this->assertIsArray( $actual );
		$this->assertCount( 2, $actual );
		$this->assertInstanceOf( ReflectionParameter::class, $actual[0] );
		$this->assertInstanceOf( ReflectionParameter::class, $actual[1] );

		/* From class instances. */
		$actual = $this->detector->get_parameters( new Untyped_Two_Params_Class( '1', '2' ) );
		$this->assertIsArray( $actual );
		$this->assertCount( 2, $actual );
		$this->assertInstanceOf( ReflectionParameter::class, $actual[0] );
		$this->assertInstanceOf( ReflectionParameter::class, $actual[1] );

		/* From class methods. */
		$actual = $this->detector->get_parameters( array( new Class_Method_Stub(), 'stub_method' ) );
		$this->assertIsArray( $actual );
		$this->assertCount( 2, $actual );
		$this->assertInstanceOf( ReflectionParameter::class, $actual[0] );
		$this->assertInstanceOf( ReflectionParameter::class, $actual[1] );

		$actual = $this->detector->get_parameters( array( Class_Method_Stub::class, 'stub_static' ) );
		$this->assertIsArray( $actual );
		$this->assertCount( 3, $actual );
		$this->assertInstanceOf( ReflectionParameter::class, $actual[0] );
		$this->assertInstanceOf( ReflectionParameter::class, $actual[1] );
		$this->assertInstanceOf( ReflectionParameter::class, $actual[2] );

		/* From functions. */
		$actual = $this->detector->get_parameters( __NAMESPACE__ . '\\func_two_params' );
		$this->assertIsArray( $actual );
		$this->assertCount( 1, $actual );
		$this->assertInstanceOf( ReflectionParameter::class, $actual[0] );

		/* From callables, like lambda */
		$actual = $this->detector->get_parameters( function ( $t ) { } );
		$this->assertIsArray( $actual );
		$this->assertCount( 1, $actual );
		$this->assertInstanceOf( ReflectionParameter::class, $actual[0] );

		$actual = $this->detector->get_parameters( fn( $k ) => $k );
		$this->assertIsArray( $actual );
		$this->assertCount( 1, $actual );
		$this->assertInstanceOf( ReflectionParameter::class, $actual[0] );
	}

	/**
	 * Test detect method
	 *
	 * @return void
	 * @dataProvider provider_detect
	 *
	 * @throws ReflectionException When reflection fails.
	 */
	public function test_detect( $target, $expected, $message = '' ): void {
		$actual = $this->detector->detect( $target );

		$this->assertEquals( $expected, $actual, $message );
	}

	protected function provider_detect(): array {
		return array(
			array(
				Param_Detector_Test_Union_Param_Class::class,
				array(
					'a' => array(
						'type'        => 'Dependency_Class_A|string|false|null',
						'allow_null'  => false,
						'default'     => null,
						'has_default' => false,
					),
				),
				'Param_Detector_Test_Union_Param_Class',
			),
		);
	}
}
