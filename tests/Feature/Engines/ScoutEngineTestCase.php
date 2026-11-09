<?php

namespace Lomkit\Access\Tests\Feature\Engines;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Laravel\Scout\Builder;
use Laravel\Scout\EngineManager;
use Laravel\Scout\ScoutServiceProvider;
use Lomkit\Access\Tests\Feature\TestCase;
use Lomkit\Access\Tests\Support\Models\SearchableModel;
use Lomkit\Access\Tests\Support\Models\User;

abstract class ScoutEngineTestCase extends TestCase
{
    /**
     * The Scout driver under test.
     */
    abstract protected function driver(): string;

    /**
     * The environment variable that points the tests to a running server.
     */
    abstract protected function hostVariable(): string;

    /**
     * Configure the Scout driver under test.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    abstract protected function configureDriver($app): void;

    /**
     * Prepare the index before models are indexed.
     */
    protected function prepareIndex(string $index): void
    {
    }

    protected function setUp(): void
    {
        if (getenv($this->hostVariable()) === false) {
            $this->markTestSkipped(sprintf('Set %s to run the tests against a %s server.', $this->hostVariable(), $this->driver()));
        }

        parent::setUp();

        $this->beforeApplicationDestroyed(function () {
            app(EngineManager::class)->engine()->deleteIndex((new SearchableModel())->searchableAs());
        });
    }

    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);

        $app['config']->set('scout.driver', $this->driver());
        $app['config']->set('scout.prefix', sprintf('lac_%s_', bin2hex(random_bytes(4))));
        $app['config']->set('scout.queue', false);
        $app['config']->set('scout.after_commit', false);

        $this->configureDriver($app);
    }

    protected function getPackageProviders($app)
    {
        return [
            ...parent::getPackageProviders($app),
            ScoutServiceProvider::class,
        ];
    }

    /**
     * Index two models of the authenticated user's client and one of another client, and wait for the engine to serve them.
     */
    protected function indexSearchableModels(): void
    {
        $this->prepareIndex((new SearchableModel())->searchableAs());

        SearchableModel::forceCreate(['name' => 'alpha', 'number' => 1, 'client_id' => Auth::user()->client->getKey()]);
        SearchableModel::forceCreate(['name' => 'beta', 'number' => 2, 'client_id' => Auth::user()->client->getKey()]);
        SearchableModel::forceCreate(['name' => 'gamma', 'number' => 3]);

        for ($attempt = 0; $attempt < 50 && $this->totalCount(SearchableModel::search()) < 3; $attempt++) {
            usleep(100000);
        }

        $this->assertSame(3, $this->totalCount(SearchableModel::search()), 'The engine did not serve the indexed models.');
    }

    /**
     * The total the engine reports in its raw results.
     */
    protected function totalCount(Builder $query): int
    {
        return app(EngineManager::class)->engine()->getTotalCount($query->raw());
    }

    /**
     * Assert the Scout query returns nothing through every way of reading it.
     */
    protected function assertScoutQueryReturnsNothing(Builder $query): void
    {
        $this->assertCount(0, $query->get());
        $this->assertCount(0, $query->keys());
        $this->assertSame(0, $this->totalCount($query));
        $this->assertSame(0, iterator_count($query->cursor()));

        $paginator = $query->paginate(10);
        $this->assertCount(0, $paginator->items());
        $this->assertSame(0, $paginator->total());

        $simplePaginator = $query->simplePaginate(10);
        $this->assertCount(0, $simplePaginator->items());
        $this->assertFalse($simplePaginator->hasMorePages());
    }

    public function test_controlled_scout_query_with_no_perimeter_passing_returns_nothing(): void
    {
        $this->indexSearchableModels();

        $this->assertScoutQueryReturnsNothing(SearchableModel::search()->controlled());
    }

    public function test_controlled_scout_query_without_authenticated_user_returns_nothing(): void
    {
        Gate::define('view client models', function (User $user) {
            return true;
        });

        $this->indexSearchableModels();

        Auth::logout();

        $this->assertScoutQueryReturnsNothing(SearchableModel::search()->controlled());
    }

    public function test_controlled_scout_query_with_no_perimeter_passing_ignores_later_constraints(): void
    {
        $this->indexSearchableModels();

        $this->assertScoutQueryReturnsNothing(
            SearchableModel::search()->controlled()->where('client_id', Auth::user()->client->getKey())
        );
    }

    public function test_controlled_scout_query_using_client_perimeter_returns_client_models(): void
    {
        Gate::define('view client models', function (User $user) {
            return true;
        });

        $this->indexSearchableModels();

        $models = SearchableModel::search()->controlled()->get();

        $this->assertEqualsCanonicalizing(['alpha', 'beta'], $models->pluck('name')->all());
        $this->assertSame(2, SearchableModel::search()->controlled()->paginate(10)->total());
    }
}
