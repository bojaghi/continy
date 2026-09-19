<?php
/**
 * Continy test
 *
 * @package Bojaghi\Continy\Tests
 */

namespace Bojaghi\Continy\Tests;

use Bojaghi\Continy\Continy;
use Bojaghi\Continy\Continy_Exception;
use Bojaghi\Continy\Continy_Not_Found_Exception;
use PHPUnit\Framework\TestCase;
use Test_Dep;
use Test_Dep_Common;
use Test_Dep_Deep;
use Test_Simple_Constructor;
use Test_Very_Simple_Class;

/**
 * Continy UnitTest
 */
class ContinyTest extends TestCase {
	public function test_wrong_key() {
		$continy = new Continy( array() );

		$this->expectException( Continy_Not_Found_Exception::class );
		$continy->instantiate( 'wrong_key' );
	}

	public function test_verbatim() {
		$continy = new Continy(
			array(
				'bindings' => array(
					'test_verbatim' => array(
						'verbatim' => 'verbatim_string',
					),
				),
			),
		);

		$this->assertEquals( 'verbatim_string', $continy->instantiate( 'test_verbatim' ) );
	}

	public function test_binding_callable() {
		$test_function = function () { };

		$continy = new Continy(
			array(
				'bindings' => array(
					'test_callable' => array(
						'as' => $test_function,
					),
				),
			),
		);

		$this->assertEquals( $test_function, $continy->instantiate( 'test_callable' ) );
	}

	public function test_instantiate_simple_class() {
		$continy = new Continy(
			array(
				'bindings' => array(
					'very_simple' => array(
						'as'    => 'Test_Very_Simple_Class',
						'reuse' => true,
					),
				),
			),
		);

		$instance = $continy->instantiate( 'very_simple' );
		$this->assertInstanceOf( Test_Very_Simple_Class::class, $instance );
		$this->assertEquals( 1, $instance->get_count() );

		// Set to this value.
		$instance->set_value( 'instance test' );

		// Count is increased by 1 because reuse is overridden to false.
		$another = $continy->instantiate( 'very_simple', null, false );
		$this->assertInstanceOf( Test_Very_Simple_Class::class, $another );
		$this->assertEquals( 2, $another->get_count() );
		$this->assertEmpty( $another->get_value() );

		// Count is the same because this time, reuse is overridden to true.
		$yet_another = $continy->instantiate( 'very_simple', null, true );
		$this->assertInstanceOf( Test_Very_Simple_Class::class, $another );
		$this->assertEquals( 2, $another->get_count() );
		$this->assertEquals( 'instance test', $yet_another->get_value() );
	}

	public function test_instantiate_exception_occurs(): void {
		$continy = new Continy(
			array(
				'bindings' => array(
					'simple_constructor' => array(
						'as' => 'Test_Simple_Constructor',
					),
				),
			),
		);

		// Simple constructor will throw an exception because parameter is scalar value that continy cannot guess.
		$this->expectException( Continy_Exception::class );
		$continy->instantiate( 'simple_constructor' );
	}

	public function test_instantiate_with_callback(): void {
		$continy = new Continy(
			array(
				'bindings' => array(
					'simple_constructor' => array(
						'as' => 'Test_Simple_Constructor',
					),
				),
			),
		);

		// In this test 'simple_constructor is instantiated with valid callback.
		$instance = $continy->instantiate(
			'simple_constructor',
			function ( string $id, string $class_name, $continy ) {
				$this->assertEquals( 'simple_constructor', $id );
				$this->assertEquals( 'Test_Simple_Constructor', $class_name );
				$this->assertInstanceOf( Continy::class, $continy );

				return new $class_name( 'success' ); // Set p1 value here.
			},
		);

		$this->assertInstanceOf( Test_Simple_Constructor::class, $instance );
		$this->assertEquals( 'success', $instance->get_p1() );
	}

	public function test_instantiate_with_common_object(): void {
		$continy = new Continy(
			array(
				'bindings' => array(
					'test_dep' => array(
						'as' => 'Test_Dep',
					),
				),
			),
		);

		$dep = $continy->instantiate( 'test_dep' );
		$this->assertInstanceOf( Test_Dep::class, $dep );
		$this->assertInstanceOf( Test_Dep_Deep::class, $dep->deep );
		$this->assertInstanceOf( Test_Dep_Common::class, $dep->common );
		$this->assertInstanceOf( Test_Dep_Common::class, $dep->deep->common );
	}

	public function test_detect_dependency_loop(): void
	{
		$continy = new Continy(
			array(
				'bindings' => array(
					'test_dep_loop' => array(
						'as' => 'Test_Dep_Loop',
					),
				),
			),
		);

		// Exception by looping.
		$this->expectException( Continy_Exception::class );
		$continy->instantiate( 'test_dep_loop' );
	}

	public function test_binding_arguments(): void
	{
		// TODO: 바인딩 하면서 집어넣는 값을 통해 생성자 제대로 호출되는지 확인.
	}
}
