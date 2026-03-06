<?php

namespace Bojaghi\Continy\Tests;

use Bojaghi\Continy\Continy;
use Bojaghi\Continy\Tests\DummyPlugin\Modules\CallbackWithArguments;
use WP_UnitTestCase;
use function Bojaghi\Continy\Tests\DummyPlugin\getTestDummyPlugin;

class TestCallbackWithArguments extends WP_UnitTestCase
{
    protected Continy $continy;

    public function setUp(): void
    {
        $this->continy = getTestDummyPlugin();
    }

    public function testCallbackWithArguments(): void
    {
        // This action creates an instance of CallbackWithArguments with value1=true and value2=1
        do_action('callback_with_arguments', true, 1);

        // Get the "created" instance. We re-use the instance.
        $instance = $this->continy->get('callback-with-arguments');

        // Assert values are correctly passed.
        $this->assertInstanceOf(CallbackWithArguments::class, $instance);
        $this->assertTrue($instance->value1);
        $this->assertEquals(1, $instance->value2);
    }
}
