<?php


use Illuminate\Support\Facades\Auth;
use Lomkit\Access\Tests\Support\Models\Model;

class ControlsScoutQueryTest extends \Lomkit\Access\Tests\Feature\TestCase
{
    public function test_control_scout_query_with_no_perimeter_passing(): void
    {
        Model::factory()
            ->count(50)
            ->create();

        $query = Model::search();
        $query = (new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->scoutQueried($query, Auth::user());

        $this->assertEquals(['__NOT_A_VALID_FIELD__' => 0], $query->wheres);
    }

    public function test_control_scout_queried_using_client_perimeter(): void
    {
        Auth::user()->update(['should_client' => true]);

        Model::factory()
            ->count(50)
            ->create();
        Model::factory()
            ->state(['is_client' => true])
            ->count(50)
            ->create();

        $query = Model::search();
        $query = (new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->scoutQueried($query, Auth::user());

        $this->assertEquals(['is_client' => true], $query->wheres);
    }

    public function test_control_scout_queried_using_shared_overlayed_perimeter(): void
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

        $query = Model::search();
        $query = (new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->scoutQueried($query, Auth::user());

        $this->assertEquals(['is_shared' => true, 'is_client' => true], $query->wheres);
    }

    public function test_control_scout_queried_using_shared_overlayed_perimeter_with_distant_perimeter(): void
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

        $query = Model::search();
        $query = (new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->scoutQueried($query, Auth::user());

        $this->assertEquals(['is_shared' => true, 'is_own' => true], $query->wheres);
    }

    public function test_control_scout_queried_using_only_shared_overlayed_perimeter(): void
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

        $query = Model::search();
        $query = (new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->scoutQueried($query, Auth::user());

        $this->assertEquals(['is_shared' => true], $query->wheres);
    }
}
