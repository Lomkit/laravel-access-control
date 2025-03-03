<?php

use Illuminate\Support\Facades\Auth;
use Lomkit\Access\Tests\Support\Models\Model;

class ControlsQueryTest extends \Lomkit\Access\Tests\Feature\TestCase
{
    public function test_control_with_no_perimeter_passing(): void
    {
        Model::factory()
            ->count(50)
            ->create();

        $query = Model::query();
        $query = (new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->queried($query, Auth::user());

        $this->assertEquals(0, $query->count());
    }

    public function test_control_queried_using_client_perimeter(): void
    {
        Auth::user()->update(['should_client' => true]);

        Model::factory()
            ->count(50)
            ->create();
        Model::factory()
            ->state(['is_client' => true])
            ->count(50)
            ->create();

        $query = Model::query();
        $query = (new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->queried($query, Auth::user());
        // @TODO: le soucis ici c'est que on n'applique pas le applies ?

        $this->assertEquals(50, $query->count());
    }

    // @TODO: tester le overlays perimeter
}
