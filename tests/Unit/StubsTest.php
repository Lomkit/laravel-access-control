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
                '/app/src/Console/stubs/control.stub'           => 'controller.stub',
                '/app/src/Console/stubs/perimeter.plain.stub'   => 'perimeter.plain.stub',
                '/app/src/Console/stubs/perimeter.overlay.stub' => 'perimeter.overlay.stub',
            ],
            $event->stubs
        );
    }
}
