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

/**
 * Continy UnitTest
 */
class ContinyTest extends TestCase {
	/**
	 * 잘못된 아이디를 넣을 경우 Continy_Not_Found_Exception 예외가 발생하는지 검사합니다.
	 *
	 * @return void
	 * @throws Continy_Exception
	 * @throws Continy_Not_Found_Exception
	 */
	public function test_wrong_key() {
		$continy = new Continy( array() );

		$this->expectException( Continy_Not_Found_Exception::class );
		$continy->instantiate( 'wrong_key' );
	}

	/**
	 * ID가 다르지만, 실제로 동일한 클래스를 가리키는 경우 Continy_Exception을 던져야 합니다.
	 *
	 * @return void
	 * @throws Continy_Exception
	 */
	public function test_duplicated_as() {
		$this->expectException( Continy_Exception::class );

		// Exception will occur.
		new Continy(
			array(
				'bindings' => array(
					'one' => array( 'as' => Test_Very_Simple_Class::class ),
					'two' => array( 'as' => Test_Very_Simple_Class::class ),
				),
			),
		);
	}

	/**
	 * 'as' 설정에 매팽된 텍스트나 정수 등의 스칼라 값은 그대로 리턴됩니다.
	 *
	 * @return void
	 * @throws Continy_Exception
	 * @throws Continy_Not_Found_Exception
	 */
	public function test_constant_as() {
		$continy = new Continy(
			array(
				'bindings' => array(
					'me' => array( 'as' => 'whoami' ),
				),
			),
		);

		$this->assertEquals( 'whoami', $continy->instantiate( 'me' ) );
	}

	/**
	 * 'verbatim' 설정으로 정말 그대로의 값을 출력하는지 테스트합니다.
	 * 클래스 이름일지라도 이것을 인스턴스화 시키지 않습니다.
	 *
	 * @return void
	 * @throws Continy_Exception
	 * @throws Continy_Not_Found_Exception
	 */
	public function test_verbatim() {
		$continy = new Continy(
			array(
				'bindings' => array(
					'v1' => array(
						'verbatim' => 'verbatim_string',
					),
					'v2' => array(
						'verbatim' => Test_Very_Simple_Class::class,
					),
				),
			),
		);

		$this->assertEquals( 'verbatim_string', $continy->instantiate( 'v1' ) );
		$this->assertEquals( Test_Very_Simple_Class::class, $continy->instantiate( 'v2' ) );
	}

	/**
	 * 함수 같은 것을 매핑하면 그 함수를 그대로 가져옵니다.
	 *
	 * @return void
	 * @throws Continy_Exception
	 * @throws Continy_Not_Found_Exception
	 */
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

		$this->assertSame( $test_function, $continy->instantiate( 'test_callable' ) );
	}

	/**
	 * 아이디로 불러오는 경우, 제대로 클래스가 생성되는지 테스트합니다.
	 * 여러번 불러도 객체가 제대로 재활용되는지, 또는 재활용을 멈추고 새로운 객체를 생성하는지 테스트합니다.
	 * 부가적으로, 객체가 새로 만들어지게 되는 경우에 기존에 캐싱된 객체를 건드리지 않는지도 검증합니다.
	 *
	 * @return void
	 * @throws Continy_Exception
	 * @throws Continy_Not_Found_Exception
	 */
	public function test_instantiate_simple_class() {
		$continy = new Continy(
			array(
				'bindings' => array(
					'very_simple' => array(
						'as'    => Test_Very_Simple_Class::class,
						'reuse' => true,
					),
				),
			),
		);

		$instance = $continy->instantiate( 'very_simple' );
		$this->assertInstanceOf( Test_Very_Simple_Class::class, $instance );
		$this->assertEquals( 1, $instance->get_count() );

		// Set value
		$instance->set_value( 'instance test' );

		// Check if instance is reused.
		$reused = $continy->instantiate( 'very_simple' );
		$this->assertEquals( 'instance test', $reused->get_value() );

		// Count is increased by 1 because reuse is overridden to false.
		$another = $continy->instantiate( 'very_simple', null, false );
		$this->assertInstanceOf( Test_Very_Simple_Class::class, $another );
		$this->assertEquals( 2, $another->get_count() ); // Because count is static.
		$this->assertEmpty( $another->get_value() );     // Because it is a fresh instance.

		// Count is the same because this time, reuse is overridden to true.
		$yet_another = $continy->instantiate( 'very_simple', null, true );
		$this->assertInstanceOf( Test_Very_Simple_Class::class, $yet_another );
		$this->assertEquals( 2, $yet_another->get_count() );               // It is reused, and static did not increase.
		$this->assertEquals( 'instance test', $yet_another->get_value() ); // 'instance test' is from previous instance.
	}

	/**
	 * 생성자에 명시적으로 값을 제공해야 하는 경우, 즉 Continy가 독자적으로 생성자 파라미터 값을 추론할 수 없을 경우,
	 * 제대로 Continy_Exception 예외를 던지는지 체크합니다.
	 *
	 * @return void
	 * @throws Continy_Exception
	 * @throws Continy_Not_Found_Exception
	 */
	public function test_instantiate_exception_occurs(): void {
		$continy = new Continy(
			array(
				'bindings' => array(
					'simple_constructor' => array(
						'as' => Test_Simple_Constructor::class,
					),
				),
			),
		);

		// Simple constructor will throw an exception because parameter is scalar value that continy cannot guess.
		$this->expectException( Continy_Exception::class );
		$continy->instantiate( 'simple_constructor' );
	}

	/**
	 * instantiate() 메소드의 인자 $args를 테스트합니다.
	 * 콜백 형태로 입력하면 제대로 호출되어 인스턴스 생성에 기여하는지 검증합니다.
	 *
	 * @return void
	 * @throws Continy_Exception
	 * @throws Continy_Not_Found_Exception
	 */
	public function test_instantiate_with_callback(): void {
		$continy = new Continy(
			array(
				'bindings' => array(
					'simple_constructor' => array(
						'as' => Test_Simple_Constructor::class,
					),
				),
			),
		);

		// In this test 'simple_constructor is instantiated with valid callback.
		$instance = $continy->instantiate(
			'simple_constructor',
			function ( string $id, string $class_name, $continy ) {
				$this->assertEquals( 'simple_constructor', $id );
				$this->assertEquals( Test_Simple_Constructor::class, $class_name );
				$this->assertInstanceOf( Continy::class, $continy );

				return new $class_name( 'success' ); // Set p1 value here.
			},
		);

		$this->assertInstanceOf( Test_Simple_Constructor::class, $instance );
		$this->assertEquals( 'success', $instance->get_p1() );
	}

	/**
	 * Continy가 올바르게 생성자 파라미터를 분석하여 의존성을 해결하는지 테스트합니다.
	 * 부가적으로 Continy가 미리 알려주지 않은 클래스에 대해서도 충분히 객체 생성을 하는지도 점검합니다.
	 *
	 * @return void
	 * @throws Continy_Exception
	 * @throws Continy_Not_Found_Exception
	 */
	public function test_instantiate_with_common_object(): void {
		$continy = new Continy(
			array(
				'bindings' => array(
					'test_dep' => array(
						'as' => Test_Dep::class,
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

	/**
	 * A가 B를 요구하고, B가 A를 요구하는 경우에 의존성 루프가 발생하는지 테스트합니다.
	 * Continy는 이러한 의존성 루프를 감지하여 Continy_Exception 예외를 발생시켜야 합니다.
	 *
	 * @return void
	 * @throws Continy_Exception
	 * @throws Continy_Not_Found_Exception
	 */
	public function test_detect_dependency_loop(): void {
		$continy = new Continy(
			array(
				'bindings' => array(
					'test_dep_loop' => array(
						'as' => Test_Dep_Loop::class,
					),
				),
			),
		);

		// Exception by looping.
		$this->expectException( Continy_Exception::class );
		$continy->instantiate( 'test_dep_loop' );
	}

	/**
	 * 'bindings' 내부에 설정한 'args' 값이 올바르게 동작하는지 점검합니다.
	 * 배열의 형태로 제공해 보고, 콜백 형태로도 테스트해 봅니다. 어떤 경우든 생성자 파라미터에 제대로 전달되어야 합니다.
	 *
	 * @return void
	 * @throws Continy_Exception
	 * @throws Continy_Not_Found_Exception
	 */
	public function test_binding_args(): void {
		// 'args' as an array.
		$continy = new Continy(
			array(
				'bindings' => array(
					'test' => array(
						'as'   => Test_Constructor_Class::class,
						'args' => array( 1, 2 ),
					),
				),
			),
		);

		$instance = $continy->instantiate( 'test' );
		$this->assertInstanceOf( Test_Constructor_Class::class, $instance );
		$this->assertEquals( 1, $instance->p1 );
		$this->assertEquals( 2, $instance->p2 );

		// 'args' as a callback.
		$continy = new Continy(
			array(
				'bindings' => array(
					'test' => array(
						'as'   => Test_Constructor_Class::class,
						'args' => function ( $id, $class_name, $continy ) {
							$this->assertEquals( 'test', $id );
							$this->assertEquals( Test_Constructor_Class::class, $class_name );
							$this->assertInstanceOf( Continy::class, $continy );

							return array( 3, 4 );
						},
					),
				),
			),
		);

		$instance = $continy->instantiate( 'test' );
		$this->assertInstanceOf( Test_Constructor_Class::class, $instance );
		$this->assertEquals( 3, $instance->p1 );
		$this->assertEquals( 4, $instance->p2 );
	}

	/**
	 * 조건부로 객체 생성을 하는지 점검합니다.
	 * 생성자에 선언된 인터페이스가 맥락에 따라 각각 다른 구현 클래스의 객체로 생성되는지,
	 * 또는 기본값으로 설정된 (when이 없는) 객체로 생성되는지 점검해 봅니다.
	 *
	 * @return void
	 * @throws Continy_Exception
	 * @throws Continy_Not_Found_Exception
	 */
	public function test_binding_when(): void {
		// Test 'when' setup.
		$continy = new Continy(
			array(
				'bindings' => array(
					Handler_Interface::class => array(
						// Default handler
						array(
							'as' => Default_Handler::class,
						),
						array(
							'as'   => Image_Handler::class,
							'when' => Image_Processor::class,
						),
						array(
							'as'   => Video_Handler::class,
							'when' => Video_Processor::class,
						),
					),
				),
			),
		);

		// Default_Processor w/ Default_Handler
		$processor = $continy->instantiate( Default_Processor::class );
		$this->assertInstanceOf( Default_Processor::class, $processor );
		$this->assertInstanceOf( Default_Handler::class, $processor->handler );

		// Image_Processor w/ Image_Handler
		$processor = $continy->instantiate( Image_Processor::class );
		$this->assertInstanceOf( Image_Processor::class, $processor );
		$this->assertInstanceOf( Image_Handler::class, $processor->handler );

		// Video_Processor w/ Video_Handler
		$processor = $continy->instantiate( Video_Processor::class );
		$this->assertInstanceOf( Video_Processor::class, $processor );
		$this->assertInstanceOf( Video_Handler::class, $processor->handler );

		// Assert default handler can be reused.
		$handler = $continy->instantiate( Handler_Interface::class );
		$this->assertInstanceOf( Handler_Interface::class, $handler );
		$this->assertInstanceOf( Default_Handler::class, $handler );
		$this->assertEquals( 1, $handler::$count );
	}

	/**
	 * drop() 메소드를 점검합니다.
	 * id로 앨리어스를 넣든, QCN을 넣든 상관 없이 Continy는 깨끗하게 캐싱된 값을 지워야 합니다.
	 *
	 * @return void
	 * @throws Continy_Exception
	 * @throws Continy_Not_Found_Exception
	 */
	public function test_drop(): void {
		$continy = new Continy(
			array(
				'bindings' => array(
					'test' => Test_Very_Simple_Class::class,
				),
			),
		);

		// Ensure that the class is not instantiated.
		$this->assertFalse( $continy->has( Test_Very_Simple_Class::class ) );
		$this->assertFalse( $continy->has( 'test' ) );

		// Instantiate by class name.
		$continy->instantiate( Test_Very_Simple_Class::class ); // --- [1] Created by class name.
		// Make sure that everything is available.
		$this->assertTrue( $continy->has( Test_Very_Simple_Class::class ) );
		$this->assertTrue( $continy->has( 'test' ) );
		$continy->drop( Test_Very_Simple_Class::class );        // --- [1] Drop by class name.
		// Make sure that everything is removed.
		$this->assertFalse( $continy->has( Test_Very_Simple_Class::class ) );
		$this->assertFalse( $continy->has( 'test' ) );

		// Instantiate by alias
		$continy->instantiate( 'test' );                        // --- [2] Created by alias.
		// Make sure that everything is available.
		$this->assertTrue( $continy->has( Test_Very_Simple_Class::class ) );
		$this->assertTrue( $continy->has( 'test' ) );
		$continy->drop( 'test' );                               // --- [2] Drop by alias.
		// Make sure that everything is removed.
		$this->assertFalse( $continy->has( Test_Very_Simple_Class::class ) );
		$this->assertFalse( $continy->has( 'test' ) );

		$continy->instantiate( 'test' );                        // --- [3] Created by alias.
		// Make sure that everything is available.
		$this->assertTrue( $continy->has( Test_Very_Simple_Class::class ) );
		$this->assertTrue( $continy->has( 'test' ) );
		$continy->drop( Test_Very_Simple_Class::class );        // --- [3] Drop by class name.
		// Make sure that everything is removed.
		$this->assertFalse( $continy->has( Test_Very_Simple_Class::class ) );
		$this->assertFalse( $continy->has( 'test' ) );

		$continy->instantiate( Test_Very_Simple_Class::class ); // --- [4] Created by class name.
		// Make sure that everything is available.
		$this->assertTrue( $continy->has( Test_Very_Simple_Class::class ) );
		$this->assertTrue( $continy->has( 'test' ) );
		$continy->drop( 'test' );                               // --- [4] Drop by alias.
		// Make sure that everything is removed.
		$this->assertFalse( $continy->has( Test_Very_Simple_Class::class ) );
		$this->assertFalse( $continy->has( 'test' ) );
	}

	/**
	 * parse_callbak() 메소드를 테스트합니다.
	 *
	 * @return void
	 * @throws Continy_Exception
	 * @throws Continy_Not_Found_Exception
	 */
	public function test_parse_callback(): void {
		$continy = new Continy(
			array(
				'bindings' => array(
					'test' => Test_Very_Simple_Class::class,
				),
			),
		);

		// alias@method
		$callback = $continy->parse_callback( 'test@get_count' );
		$this->assertIsArray( $callback );
		$this->assertCount( 2, $callback );
		$this->assertInstanceOf( Test_Very_Simple_Class::class, $callback[0] );
		$this->assertEquals( 'get_count', $callback[1] );

		// QCN@method
		$callback = $continy->parse_callback( Test_Very_Simple_Class::class . '@get_count' );
		$this->assertCount( 2, $callback );
		$this->assertInstanceOf( Test_Very_Simple_Class::class, $callback[0] );
		$this->assertEquals( 'get_count', $callback[1] );

		// functions
		$callback = $continy->parse_callback( 'Bojaghi\\Continy\\Tests\\test_empty_function' );
		$this->assertEquals( 'Bojaghi\\Continy\\Tests\\test_empty_function', $callback );
		$this->assertIsCallable( $callback );

		// Closures
		$cb1 = function () { return 'callback_1'; };
		$cb2 = fn() => 'callback_2';
		$this->assertNotSame( $cb1, $continy->parse_callback( $cb2 ) );
		$this->assertNotSame( $cb2, $continy->parse_callback( $cb1 ) );
		$this->assertSame( $cb1, $continy->parse_callback( $cb1 ) );
		$this->assertSame( $cb2, $continy->parse_callback( $cb2 ) );

		// This callback method does not exist.
		$callback = $continy->parse_callback( 'test@not_exists' );
		$this->assertNull( $callback );

		// This function does not exist.
		$this->assertNull( $continy->parse_callback( '_test_invalid_function_name_' ) );
	}

	/**
	 * call() 메소드를 테스트합니다.
	 *
	 * @return void
	 * @throws Continy_Exception
	 * @throws Continy_Not_Found_Exception
	 */
	public function test_call(): void {
		$continy = new Continy(
			array(
				'bindings' => array(
					'simple_test'   => Test_Very_Simple_Class::class,
					'call_test'     => Call_Test::class,
					'call_test_dep' => array(
						'as'   => Call_Test_Dep::class,
						'args' => array( 'continy' ),
					),
				),
			),
		);

		$continy->call( 'simple_test@set_value', 120 );
		$this->assertEquals( 120, $continy->call( 'simple_test@get_value' ) );

		$continy->call( 'call_test@import_name', array( 'name' => 'bojaghi' ) );
		$this->assertEquals( 'bojaghi/continy', $continy->get( 'call_test' )->value );
		$this->assertEquals( 'continy', $continy->get( 'call_test_dep' )->name );

		$continy->call( 'call_test@import_name', function ( $to_call, $continy ) {
			$this->assertEquals( 'call_test@import_name', $to_call );
			$this->assertInstanceOf( Continy::class, $continy );

			return 'my';
		} );
		$this->assertEquals( 'my/continy', $continy->get( 'call_test' )->value );
	}

	/**
	 * 모듈이 제대로 실행되는지 확인합니다.
	 *
	 * @return void
	 * @throws Continy_Exception
	 */
	public function test_module(): void {
		$flag = 0;

		$continy = new Continy(
			array(
				'modules' => array(
					'_'           => array(
						function () use ( &$flag ) {
							$flag += 1;
						},
					),
					'module_hook' => array(
						Continy::PR_DEFAULT => array(
							Module_Class::class,
						),
						Continy::PR_LOW     => array(
							'Bojaghi\\Continy\\Tests\\test_modules_function',
						),
					),
				),
			),
		);

		// Continy handles '_' modules as soon as it is initialized, therefore $flag should be true.
		$this->assertEquals( 1, $flag );

		// Continy does not initialize until do_action() is called, therefore Module_Class count should be zero.
		$this->assertEquals( 0, Module_Class::get_count() );

		// The same as here. Function test_modules_function is called here for the first time.
		$this->assertEquals( 0, test_modules_function() );

		// Check really callback is connected.
		$has = has_action( 'module_hook', 'Bojaghi\\Continy\\Tests\\test_modules_function' );
		$this->assertEquals( Continy::PR_LOW, $has );

		do_action( 'module_hook', 'success' );

		// Check if '_' module is handled only once.
		$this->assertEquals( 1, $flag );

		// Check if Continy really instantiated Module_Class.
		$this->assertTrue( $continy->has( Module_Class::class ) );
		$this->assertEquals( 1, $continy->get( Module_Class::class )->get_count() );
		// Check if do_action argument is properly passed to constructor.
		$this->assertEquals( 'success', $continy->get( Module_Class::class )->get_str() );

		// Check if the function is also called.
		// Called once when asserting 0 above. and called once again when do_action is called.
		// The result should be 2.
		$this->assertEquals( 2, test_modules_function() );
	}
}
