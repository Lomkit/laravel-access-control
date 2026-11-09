<?php

namespace Lomkit\Access\Tests\Feature;

use Lomkit\Access\Controls\HasControlScope;
use Lomkit\Access\Tests\Support\Models\Model;

class ControlsScopeTest extends TestCase
{
    public function test_control_controlled_scope(): void
    {
        Model::factory()
            ->count(50)
            ->create();

        $query = Model::controlled()->get();

        $this->assertEquals(0, $query->count());
    }

    public function test_control_uncontrolled_scope(): void
    {
        Model::factory()
            ->count(50)
            ->create();

        $query = Model::uncontrolled();

        $this->assertContains(HasControlScope::class, $query->removedScopes());
    }
}
