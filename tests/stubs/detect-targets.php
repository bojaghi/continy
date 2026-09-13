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

if ( ! class_exists( 'Union_Param_Class' ) ) {
	class Param_Detector_Test_Union_Param_Class {
		public function __construct(
			Dependency_Class_A|string|false|null $a,
			Dependency_Class_A|string $b = 'test',
		) {
		}
	}
}

if ( ! class_exists( 'Untyped_Two_Params_Class' ) ) {
	class Untyped_Two_Params_Class {
		public function __construct( $a, $b ) {
		}
	}
}

if ( ! class_exists( 'Class_Method_Stub' ) ) {
	class Class_Method_Stub {
		public function stub_method( $a, $b ): void { }

		public static function stub_static( $a, $b, $c ): void { }
	}
}

if ( ! class_exists( 'Dependency_Class_A' ) ) {
	class Dependency_Class_A {
	}
}

if ( ! function_exists( 'func_two_params' ) ) {
	function func_two_params( $a ) {
	}
}
