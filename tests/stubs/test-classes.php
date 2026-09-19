<?php

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
