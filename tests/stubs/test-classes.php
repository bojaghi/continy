<?php

namespace Bojaghi\Continy\Tests;

class Test_Very_Simple_Class {
	public static $count = 0;

	private string $value;

	public function __construct() {
		self::$count++; // Whenever it is instantiated, it will increase.

		$this->value = '';
	}

	public function get_count(): int {
		return self::$count;
	}

	public function get_value(): string {
		return $this->value;
	}

	public function set_value( string $value ): void {
		$this->value = $value;
	}
}

class Test_Simple_Constructor {
	private string $p1;

	public function __construct( string $p1 ) {
		$this->p1 = $p1;
	}

	public function get_p1(): string {
		return $this->p1;
	}
}

/**
 * Three classes for testing nested dependency injection.
 */
class Test_Dep {
	public function __construct( public Test_Dep_Common $common, public Test_Dep_Deep $deep ) {
	}
}

class Test_Dep_Deep {
	public function __construct( public Test_Dep_Common $common ) {
	}
}

class Test_Dep_Common {
	public function __construct() {
	}
}

/**
 * Two classes for detecting dependency loop.
 */
class Test_Dep_Loop {
	public function __construct( public Test_Dep_Loop_Deep $deep ) {
	}
}

class Test_Dep_Loop_Deep {
	public function __construct( public Test_Dep_Loop $loop ) {
	}
}

/**
 * Test class for testing dependency injection by 'binding' arguments.
 */
class Test_Constructor_Class {
	public function __construct( public $p1, public $p2 ) { }
}


/**
 * Test conditional binding
 */
interface Handler_Interface { }

class Default_Handler implements Handler_Interface {
	public static int $count = 0;

	public function __construct() {
		self::$count++;
	}
}

class Image_Handler implements Handler_Interface { }

class Video_Handler implements Handler_Interface { }

class Default_Processor {
	public function __construct( public Handler_Interface $handler ) { }
}

class Image_Processor {
	public function __construct( public Handler_Interface $handler ) { }
}

class Video_Processor {
	public function __construct( public Handler_Interface $handler ) { }
}


/**
 * call() method test classes
 */
class Call_Test {
	public string $value = '';

	public function import_name( string $name, Call_Test_Dep $depend ): void {
		$this->value = $name . '/' . $depend->name;
	}
}

class Call_Test_Dep {
	public function __construct( public string $name ) {
	}
}

/**
 * Modules test class
 */
class Module_Class {
	private static int $count = 0;

	private string $str;

	public function __construct( string $str ) {
		self::$count++;
		$this->str = $str;
	}

	public static function get_count(): int {
		return self::$count;
	}

	public function get_str(): string {
		return $this->str;
	}
}
