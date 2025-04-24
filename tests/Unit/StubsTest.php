<?php

namespace Lomkit\Access\Tests\Unit;

use Illuminate\Events\Dispatcher;

class StubsTest extends TestCase
{
    public function test_stubs_correctly_registered(): void
    {
        app(Dispatcher::class)->dispatch($event = new \Illuminate\Foundation\Events\PublishingStubs([]));

        $this->assertEquals(
            [
                realpath(__DIR__.'/../../src/Console/stubs/control.stub')           => 'control.stub',
                realpath(__DIR__.'/../../src/Console/stubs/perimeter.plain.stub')   => 'perimeter.plain.stub',
                realpath(__DIR__.'/../../src/Console/stubs/perimeter.overlay.stub') => 'perimeter.overlay.stub',
            ],
            $event->stubs
        );
    }
}
