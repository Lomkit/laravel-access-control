<?php

namespace Lomkit\Access\Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Tests\Support\Models\Model;
use Lomkit\Access\Tests\Support\Models\User;

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
        Gate::define('view client models', function (User $user) {
            return true;
        });

        Model::factory()
            ->count(50)
            ->create();
        Model::factory()
            ->clientPerimeter()
            ->count(50)
            ->create();

        $query = Model::query();
        $query = (new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->queried($query, Auth::user());

        $this->assertEquals(50, $query->count());
    }

    public function test_control_queried_using_shared_overlayed_perimeter(): void
    {
        Gate::define('view client models', function (User $user) {
            return true;
        });
        Gate::define('view shared models', function (User $user) {
            return true;
        });

        Model::factory()
            ->count(50)
            ->create();
        Model::factory()
            ->sharedPerimeter()
            ->count(50)
            ->create();
        Model::factory()
            ->clientPerimeter()
            ->count(50)
            ->create();

        $query = Model::query();
        $query = (new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->queried($query, Auth::user());

        $this->assertEquals(100, $query->count());
    }

    public function test_control_queried_using_shared_overlayed_perimeter_with_distant_perimeter(): void
    {
        Gate::define('view shared models', function (User $user) {
            return true;
        });
        Gate::define('view own models', function (User $user) {
            return true;
        });

        Model::factory()
            ->clientPerimeter()
            ->count(50)
            ->create();
        Model::factory()
            ->sharedPerimeter()
            ->count(50)
            ->create();
        Model::factory()
            ->ownPerimeter()
            ->count(50)
            ->create();

        $query = Model::query();
        $query = (new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->queried($query, Auth::user());

        $this->assertEquals(100, $query->count());
    }

    public function test_control_queried_using_only_shared_overlayed_perimeter(): void
    {
        Gate::define('view shared models', function (User $user) {
            return true;
        });

        Model::factory()
            ->clientPerimeter()
            ->count(50)
            ->create();
        Model::factory()
            ->sharedPerimeter()
            ->count(50)
            ->create();
        Model::factory()
            ->ownPerimeter()
            ->count(50)
            ->create();

        $query = Model::query();
        $query = (new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->queried($query, Auth::user());

        $this->assertEquals(50, $query->count());
    }

    public function test_control_queried_isolated(): void
    {
        Gate::define('view shared models', function (User $user) {
            return true;
        });
        Gate::define('view own models', function (User $user) {
            return true;
        });

        Model::factory()
            ->clientPerimeter()
            ->count(50)
            ->create();
        Model::factory()
            ->clientPerimeter()
            ->sharedPerimeter()
            ->count(50)
            ->create();
        Model::factory()
            ->ownPerimeter()
            ->count(50)
            ->create();

        $query = Model::query()->where('client_id', Auth::user()->client->getKey());
        $query = (new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->queried($query, Auth::user());

        $this->assertEquals(50, $query->count());
    }

    public function test_control_queried_not_parent_isolated(): void
    {
        config(['access-control.queries.isolate_parent_query' => false]);

        Gate::define('view shared models', function (User $user) {
            return true;
        });
        Gate::define('view own models', function (User $user) {
            return true;
        });

        Model::factory()
            ->clientPerimeter()
            ->count(50)
            ->create();
        Model::factory()
            ->clientPerimeter()
            ->sharedPerimeter()
            ->count(50)
            ->create();
        Model::factory()
            ->ownPerimeter()
            ->count(50)
            ->create();

        $query = Model::query()->where('client_id', Auth::user()->client->getKey());
        $query = (new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->queried($query, Auth::user());

        $this->assertEquals(150, $query->count());
    }

    public function test_control_queried_not_perimeter_isolated(): void
    {
        config(['access-control.queries.isolate_perimeter_queries' => false]);

        Gate::define('view shared models', function (User $user) {
            return true;
        });
        Gate::define('view own models', function (User $user) {
            return true;
        });

        Model::factory()
            ->clientPerimeter()
            ->count(50)
            ->create();
        Model::factory()
            ->clientPerimeter()
            ->sharedPerimeter()
            ->count(50)
            ->create();
        Model::factory()
            ->ownPerimeter()
            ->sharedPerimeter()
            ->count(50)
            ->create();
        Model::factory()
            ->ownPerimeter()
            ->count(50)
            ->create();

        $query = Model::query();
        $query = (new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->queried($query, Auth::user());

        $this->assertEquals(50, $query->count());
    }
}
