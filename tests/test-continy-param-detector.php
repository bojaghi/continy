<?php

namespace Bojaghi\Continy\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Continy_Param_Detector UnitTest
 */
class Continy_Param_Detector extends TestCase {
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



}
