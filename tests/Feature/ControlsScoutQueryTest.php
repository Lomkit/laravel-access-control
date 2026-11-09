<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Laravel\Scout\Builder;
use Lomkit\Access\Tests\Feature\TestCase;
use Lomkit\Access\Tests\Support\Access\Controls\ModelControl;
use Lomkit\Access\Tests\Support\Models\SearchableModel;
use Lomkit\Access\Tests\Support\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[Group('scout')]
class ControlsScoutQueryTest extends TestCase
{
    /**
     * Normalize Scout's `$wheres` to a [field => value] map so assertions
     * survive the shape change introduced in laravel/scout 11.0
     * (assoc array → list of ['field', 'operator', 'value'] dicts).
     */
    protected function whereMap(Builder $query): array
    {
        $wheres = $query->wheres;

        if ($wheres !== [] && isset($wheres[0]) && is_array($wheres[0]) && array_key_exists('field', $wheres[0])) {
            return array_column($wheres, 'value', 'field');
        }

        return $wheres;
    }

    /**
     * Assert the Scout query yields nothing without relying on a filter the engine may reject.
     */
    protected function assertNoResultScoutQuery(Builder $query): void
    {
        $this->assertEquals([], $this->whereMap($query));
        $this->assertNotNull($query->callback);

        // Search engines receive their client and get back an empty page in their own raw shape
        $results = ($query->callback)(new stdClass, '', []);

        $this->assertSame([], $results['hits']);
        $this->assertSame([], $results['rows']);
        $this->assertSame(0, $results['nbHits']);
        $this->assertSame(0, $results['totalHits']);
        $this->assertSame(0, $results['found']);
    }

    /**
     * The Scout engines that run against the test database.
     *
     * @return array<string, array{string}>
     */
    public static function databaseEngines(): array
    {
        return [
            'database' => ['database'],
            'collection' => ['collection'],
        ];
    }

    /**
     * Index three models: two of the authenticated user's client and one of another client.
     */
    protected function seedSearchableModels(string $engine): void
    {
        config(['scout.driver' => $engine]);

        SearchableModel::forceCreate(['name' => 'alpha', 'number' => 1, 'client_id' => Auth::user()->client->getKey()]);
        SearchableModel::forceCreate(['name' => 'beta', 'number' => 2, 'client_id' => Auth::user()->client->getKey()]);
        SearchableModel::forceCreate(['name' => 'gamma', 'number' => 3]);
    }

    /**
     * Assert the Scout query returns nothing through every way of reading it.
     */
    protected function assertScoutQueryReturnsNothing(Builder $query): void
    {
        $this->assertCount(0, $query->get());
        $this->assertCount(0, $query->keys());
        $this->assertSame(0, $query->raw()['total']);
        $this->assertSame(0, iterator_count($query->cursor()));

        $paginator = $query->paginate(10);
        $this->assertCount(0, $paginator->items());
        $this->assertSame(0, $paginator->total());

        $simplePaginator = $query->simplePaginate(10);
        $this->assertCount(0, $simplePaginator->items());
        $this->assertFalse($simplePaginator->hasMorePages());
    }

    public function test_control_scout_query_with_no_perimeter_passing(): void
    {
        $query = SearchableModel::search();
        $query = (new ModelControl)->scoutQueried($query, Auth::user());

        $this->assertNoResultScoutQuery($query);
    }

    public function test_control_scout_queried_using_client_perimeter(): void
    {
        Gate::define('view client models', function (User $user) {
            return true;
        });

        $query = SearchableModel::search();
        $query = (new ModelControl)->scoutQueried($query, Auth::user());

        $this->assertEquals(['client_id' => Auth::user()->client->getKey()], $this->whereMap($query));
        $this->assertNull($query->callback);
    }

    public function test_control_scout_queried_using_shared_overlayed_perimeter(): void
    {
        Gate::define('view client models', function (User $user) {
            return true;
        });
        Gate::define('view shared models', function (User $user) {
            return true;
        });

        $query = SearchableModel::search();
        $query = (new ModelControl)->scoutQueried($query, Auth::user());

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

        $query = SearchableModel::search();
        $query = (new ModelControl)->scoutQueried($query, Auth::user());

        $this->assertEquals(['shared_with_users' => Auth::user()->getKey(), 'author_id' => Auth::user()->getKey()], $this->whereMap($query));
    }

    public function test_control_scout_queried_using_only_shared_overlayed_perimeter(): void
    {
        Gate::define('view shared models', function (User $user) {
            return true;
        });

        $query = SearchableModel::search();
        $query = (new ModelControl)->scoutQueried($query, Auth::user());

        $this->assertEquals(['shared_with_users' => Auth::user()->getKey()], $this->whereMap($query));
    }

    public function test_control_scout_queried_without_authenticated_user_returns_no_results(): void
    {
        Gate::define('view client models', function (User $user) {
            return true;
        });

        Auth::logout();

        $query = SearchableModel::search()->controlled();

        $this->assertNoResultScoutQuery($query);
    }

    #[DataProvider('databaseEngines')]
    public function test_controlled_scout_query_with_no_perimeter_passing_returns_nothing(string $engine): void
    {
        $this->seedSearchableModels($engine);

        $this->assertScoutQueryReturnsNothing(SearchableModel::search()->controlled());
    }

    #[DataProvider('databaseEngines')]
    public function test_controlled_scout_query_without_authenticated_user_returns_nothing(string $engine): void
    {
        Gate::define('view client models', function (User $user) {
            return true;
        });

        $this->seedSearchableModels($engine);

        Auth::logout();

        $this->assertScoutQueryReturnsNothing(SearchableModel::search()->controlled());
    }

    #[DataProvider('databaseEngines')]
    public function test_controlled_scout_query_returns_nothing_without_reassigning_the_builder(string $engine): void
    {
        $this->seedSearchableModels($engine);

        $query = SearchableModel::search();
        $query->controlled();

        $this->assertScoutQueryReturnsNothing($query);
    }

    #[DataProvider('databaseEngines')]
    public function test_controlled_scout_query_with_no_perimeter_passing_ignores_later_constraints(string $engine): void
    {
        $this->seedSearchableModels($engine);

        $query = SearchableModel::search()->controlled()->where('client_id', Auth::user()->client->getKey());

        $this->assertScoutQueryReturnsNothing($query);
    }

    #[DataProvider('databaseEngines')]
    public function test_controlled_scout_query_using_client_perimeter_returns_client_models(string $engine): void
    {
        Gate::define('view client models', function (User $user) {
            return true;
        });

        $this->seedSearchableModels($engine);

        $models = SearchableModel::search()->controlled()->get();

        $this->assertEqualsCanonicalizing(['alpha', 'beta'], $models->pluck('name')->all());
        $this->assertSame(2, SearchableModel::search()->controlled()->paginate(10)->total());
    }
}
