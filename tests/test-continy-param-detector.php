<?php

namespace Bojaghi\Continy\Tests;

use Bojaghi\Continy\Continy_Param_Detector;
use PHPUnit\Framework\TestCase;

/**
 * Continy_Param_Detector UnitTest
 */
class Test_Continy_Param_Detector extends TestCase {
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
	 */
	public function test_get_parameters(): void {
		// No constructor, returns an empty array.
		$actual = $this->detector->get_parameters( Param_Detector_Test_Class_No_Constructor::class );
		$this->assertEmpty( $actual, 'Class with no constructor.' );

		// Constructor with no parameters, returns an empty array.
		$actual = $this->detector->get_parameters( Param_Detector_Test_Class_Blank_Constructor::class );
		$this->assertEmpty( $actual, 'Constructor with no parameters.' );

		/* From class instances. */

		/* From class methods. */

		/* From functions. */

		/* From callables, like lambda */
	}
}
