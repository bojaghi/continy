<?php

namespace Bojaghi\Continy\Tests;

use Bojaghi\Continy\ContinyFactory;
use WeakReference;
use WP_UnitTestCase;

class TestForget extends WP_UnitTestCase
{
    public function testForget()
    {
        $continy = ContinyFactory::create([]);

        // Get the instance, and increase
        $one = WeakReference::create($continy->get(ForgetItem::class));
        $one->get()->increaseCount();
        $this->assertEquals(1, $one->get()->getCount());

        // Get the another instance, it is reused.
        $two = WeakReference::create($continy->get(ForgetItem::class));
        $two->get()->increaseCount();
        $this->assertEquals(2, $two->get()->getCount());

        // This static value is untouched, therefore it is zero.
        $this->assertEquals(0, ForgetItem::$destructCall);

        // Forget the counter
        $continy->forget(ForgetItem::class);

        // This static value should be increased after forget.
        $this->assertEquals(1, ForgetItem::$destructCall);

        // Get the third, and make sure that it is fresh one.
        $three = $continy->get(ForgetItem::class);
        $this->assertEquals(0, $three->getCount());

        // These are just WeakReference checks
        $this->assertNull($one->get());
        $this->assertNull($two->get());
    }
}

class ForgetItem
{
    public static int $destructCall = 0;

    private int $count;

    public function __construct()
    {
        $this->count = 0;
    }

    public function __destruct()
    {
        self::$destructCall++;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function increaseCount(): void
    {
        $this->count++;
    }
}
