<?php

namespace Bojaghi\Continy\Tests;

use Bojaghi\Continy\Continy;
use PHPUnit\Framework\TestCase;

/**
 * Continy UnitTest
 */
class ContinyTest extends TestCase {
	/**
	 * Test Continy::spawn method
	 */
	public function test_spawn_verbatim() {
		$continy = new Continy( array(
			'bindings' => array(
				'abc' => array(
					'verbatim' => 'abc_verbatim',
				),
			),
		) );

		$this->assertEquals( 'abc_verbatim', $continy->spawn( 'abc' ) );
	}
}
