<?php

namespace Bojaghi\Continy\Tests;

if ( ! class_exists( 'Param_Detector_Test_Class_No_Constructor' ) ) {
	class Param_Detector_Test_Class_No_Constructor {
		// No constructor!
	}
}


if ( ! class_exists( 'Param_Detector_Test_Class_Blank_Constructor' ) ) {
	class Param_Detector_Test_Class_Blank_Constructor {
		public function __construct() {
		}
	}
}
