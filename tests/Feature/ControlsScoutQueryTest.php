<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Tests\Support\Models\Model;
use Lomkit\Access\Tests\Support\Models\User;

class ControlsScoutQueryTest extends \Lomkit\Access\Tests\Feature\TestCase
{
    /**
     * Normalize Scout's `$wheres` to a [field => value] map so assertions
     * survive the shape change introduced in laravel/scout 11.0
     * (assoc array → list of ['field', 'operator', 'value'] dicts).
     */
    protected function whereMap(\Laravel\Scout\Builder $query): array
    {
        $wheres = $query->wheres;

        if ($wheres !== [] && isset($wheres[0]) && is_array($wheres[0]) && array_key_exists('field', $wheres[0])) {
            return array_column($wheres, 'value', 'field');
        }

        return $wheres;
    }

    public function test_control_scout_query_with_no_perimeter_passing(): void
    {
        $query = Model::search();
        $query = (new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->scoutQueried($query, Auth::user());

        $this->assertEquals(['__NOT_A_VALID_FIELD__' => 0], $this->whereMap($query));
    }

    public function test_control_scout_queried_using_client_perimeter(): void
    {
        Gate::define('view client models', function (User $user) {
            return true;
        });

        $query = Model::search();
        $query = (new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->scoutQueried($query, Auth::user());

        $this->assertEquals(['client_id' => Auth::user()->client->getKey()], $this->whereMap($query));
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

        $this->assertEquals(['client_id' => Auth::user()->client->getKey(), 'shared_with_users' => Auth::user()->getKey()], $this->whereMap($query));
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

        $this->assertEquals(['shared_with_users' => Auth::user()->getKey(), 'author_id' => Auth::user()->getKey()], $this->whereMap($query));
    }

    public function test_control_scout_queried_using_only_shared_overlayed_perimeter(): void
    {
        Gate::define('view shared models', function (User $user) {
            return true;
        });

        $query = Model::search();
        $query = (new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->scoutQueried($query, Auth::user());

        $this->assertEquals(['shared_with_users' => Auth::user()->getKey()], $this->whereMap($query));
    }
}
