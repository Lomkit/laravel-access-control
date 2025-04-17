<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Tests\Support\Models\Model;
use Lomkit\Access\Tests\Support\Models\User;

class ControlsScoutQueryTest extends \Lomkit\Access\Tests\Feature\TestCase
{
    public function test_control_scout_query_with_no_perimeter_passing(): void
    {
        $query = Model::search();
        $query = (new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->scoutQueried($query, Auth::user());

        $this->assertEquals(['__NOT_A_VALID_FIELD__' => 0], $query->wheres);
    }

    public function test_control_scout_queried_using_client_perimeter(): void
    {
        Gate::define('view client models', function (User $user) {
            return true;
        });

        $query = Model::search();
        $query = (new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->scoutQueried($query, Auth::user());

        $this->assertEquals(['client_id' => Auth::user()->client->getKey()], $query->wheres);
    }

    public function test_control_scout_queried_using_shared_overlayed_perimeter(): void
    {
        Gate::define('view client models', function (User $user) {
            return true;
        });
        Gate::define('view shared models', function (User $user) {
            return true;
        });

        $query = Model::search();
        $query = (new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->scoutQueried($query, Auth::user());

        $this->assertEquals(['client_id' => Auth::user()->client->getKey(), 'shared_with_users' => Auth::user()->getKey()], $query->wheres);
    }

    public function test_control_scout_queried_using_shared_overlayed_perimeter_with_distant_perimeter(): void
    {
        Gate::define('view own models', function (User $user) {
            return true;
        });
        Gate::define('view shared models', function (User $user) {
            return true;
        });

        $query = Model::search();
        $query = (new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->scoutQueried($query, Auth::user());

        $this->assertEquals(['shared_with_users' => Auth::user()->getKey(), 'author_id' => Auth::user()->getKey()], $query->wheres);
    }

    public function test_control_scout_queried_using_only_shared_overlayed_perimeter(): void
    {
        Gate::define('view shared models', function (User $user) {
            return true;
        });

        $query = Model::search();
        $query = (new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->scoutQueried($query, Auth::user());

        $this->assertEquals(['shared_with_users' => Auth::user()->getKey()], $query->wheres);
    }
}
