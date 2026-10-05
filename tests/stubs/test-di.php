<?php

namespace Bojagjhi\Continy\Tests;

use Psr\Container\ContainerInterface;

class My_Class {
	public int $x = 0;

	public int $y = 0;

	public function __construct( int $x, int $y ) {
		$this->x = $x;
		$this->y = $y;
	}
}

class Continy_DI {
	public int                 $x;
	public ?ContainerInterface $container;

	public function __construct( int $x, ?ContainerInterface $container = null ) {
		$this->x         = $x;
		$this->container = $container;
	}
}
