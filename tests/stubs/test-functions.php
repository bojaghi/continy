<?php

namespace Bojaghi\Continy\Tests;

function test_empty_function(): void {
}

function test_modules_function(): int {
	static $count = 0;

	return $count++;
}
