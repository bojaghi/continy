<?php

namespace Bojaghi\Continy\Tests\DummyPlugin\Modules;

class CallbackWithArguments
{
    public function __construct(public bool $value1 = false, public int $value2 = 0)
    {
    }
}
