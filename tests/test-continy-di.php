<?php
/**
 * Test Continy D.I..
 *
 * @package Bojaghi\Continy\Tests
 */

namespace Bojaghi\Continy\Tests;

use Bojaghi\Continy\Continy;
use Bojaghi\Continy\Continy_Factory;
use Bojagjhi\Continy\Tests\Continy_DI;
use Bojagjhi\Continy\Tests\My_Class;
use WP_UnitTestCase;

class Test_Continy_DI extends WP_UnitTestCase {
	public static function setUpBeforeClass(): void {
		require_once __DIR__ . '/stubs/test-di.php';
	}

	public function test_di(): void {
		$continy = Continy_Factory::create(
			array(
				'bindings' => array(
					'my-class' => array(
						'as'   => My_Class::class,
						'args' => array( 'y' => 100 ),
					),
				),
				'modules'  => array(
					'my_hook' => array(
						'accepted_args' => 1,
						10              => array( 'my-class' ),
					),
				),
			),
		);

		/**
		 * do_action()에서 넘긴 파라미터와 바인딩 설정에서 추가한 설정값이 사로 잘 섞여서
		 * 클래스 생성자로 전달되는 건지 확인하여 본다.
		 *
		 * my_hook으로는 10을 전달한다. 그러면 이것은 My_Class::$x 로 전달되어야 한다.
		 * Continy는 My_Class::$y로 100을 전달해야 한다고 지시를 받았다.
		 */
		do_action( 'my_hook', 10 );

		$my_class = $continy->get( 'my-class' );
		$this->assertInstanceOf( My_Class::class, $my_class );
		$this->assertEquals( 10, $my_class->x );
		$this->assertEquals( 100, $my_class->y );

		$func = function ( int $z, My_Class $my_class ): int {
			return $z + $my_class->x + $my_class->y;
		};

		$this->assertEquals( 160, $continy->call( $func, 50 ) );

		/**
		 * Psr\Container\ContainerInterface 이것은 몇몇 보자기 프로젝트에서 사용한다.
		 * 이 때 args에 명시적으로 적거나, 적지 않아도 continy가 눈치 봐서 채워주기를 바란다.
		 */
		$continy = Continy_Factory::create(
			array(
				'bindings' => array(
					'continy-di' => array(
						'as'   => Continy_DI::class,
						'args' => array( 29 ),
					),
				),
			),
		);

		$instance = $continy->get( 'continy-di' );

		$this->assertInstanceOf( Continy_DI::class, $instance );
		$this->assertEquals( 29, $instance->x );
		$this->assertNotNull( $instance->container );
		$this->assertInstanceOf( Continy::class, $instance->container );
	}
}
