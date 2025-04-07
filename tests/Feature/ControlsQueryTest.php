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

        $this->assertEquals(50, $query->count());
    }

    public function test_control_queried_using_shared_overlayed_perimeter(): void
    {
        Auth::user()->update(['should_shared' => true]);
        Auth::user()->update(['should_client' => true]);

        Model::factory()
            ->count(50)
            ->create();
        Model::factory()
            ->state(['is_shared' => true])
            ->count(50)
            ->create();
        Model::factory()
            ->state(['is_client' => true])
            ->count(50)
            ->create();

        $query = Model::query();
        $query = (new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->queried($query, Auth::user());

        $this->assertEquals(100, $query->count());
    }

    public function test_control_queried_using_shared_overlayed_perimeter_with_distant_perimeter(): void
    {
        Auth::user()->update(['should_shared' => true]);
        Auth::user()->update(['should_own' => true]);

        Model::factory()
            ->state(['is_client' => true])
            ->count(50)
            ->create();
        Model::factory()
            ->state(['is_shared' => true])
            ->count(50)
            ->create();
        Model::factory()
            ->state(['is_own' => true])
            ->count(50)
            ->create();

        $query = Model::query();
        $query = (new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->queried($query, Auth::user());

        $this->assertEquals(100, $query->count());
    }

    public function test_control_queried_using_only_shared_overlayed_perimeter(): void
    {
        Auth::user()->update(['should_shared' => true]);

        Model::factory()
            ->state(['is_client' => true])
            ->count(50)
            ->create();
        Model::factory()
            ->state(['is_shared' => true])
            ->count(50)
            ->create();
        Model::factory()
            ->state(['is_own' => true])
            ->count(50)
            ->create();

        $query = Model::query();
        $query = (new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->queried($query, Auth::user());

        $this->assertEquals(50, $query->count());
    }

    public function test_control_queried_isolated(): void
    {
        Auth::user()->update(['should_shared' => true]);
        Auth::user()->update(['should_own' => true]);

        Model::factory()
            ->state(['is_client' => true])
            ->count(50)
            ->create();
        Model::factory()
            ->state(['is_shared' => true, 'is_client' => true])
            ->count(50)
            ->create();
        Model::factory()
            ->state(['is_own' => true])
            ->count(50)
            ->create();

        $query = Model::query()->where('is_client', true);
        $query = (new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->queried($query, Auth::user());

        $this->assertEquals(50, $query->count());
    }

    public function test_control_queried_not_isolated(): void
    {
        config(['access-control.queries.isolated' => false]);

        Auth::user()->update(['should_shared' => true]);
        Auth::user()->update(['should_own' => true]);

        Model::factory()
            ->state(['is_client' => true])
            ->count(50)
            ->create();
        Model::factory()
            ->state(['is_shared' => true, 'is_client' => true])
            ->count(50)
            ->create();
        Model::factory()
            ->state(['is_own' => true])
            ->count(50)
            ->create();

        $query = Model::query()->where('is_client', true);
        $query = (new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->queried($query, Auth::user());

        $this->assertEquals(150, $query->count());
    }
}
