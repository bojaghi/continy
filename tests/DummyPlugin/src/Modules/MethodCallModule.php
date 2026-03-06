<?php

namespace Bojaghi\Continy\Tests\DummyPlugin\Modules;

final class MethodCallModule
{
    public static bool $called = false;

    public function testMethod(): void
    {
        self::$called = true;
    }
}
