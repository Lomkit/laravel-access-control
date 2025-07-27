<?php

use Lomkit\Access\Tests\Support\Models\Model;

class ControlsScopeTest extends \Lomkit\Access\Tests\Feature\TestCase
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

        $this->assertContains(\Lomkit\Access\Controls\HasControlScope::class, $query->removedScopes());
    }
}
