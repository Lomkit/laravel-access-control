<?php

namespace Lomkit\Access\Tests\Feature\RestApi\Engines;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;
use Laravel\Scout\EngineManager;
use Laravel\Scout\ScoutServiceProvider;
use Lomkit\Access\Access;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\RestApi\Search\RestApiSearch;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\ArticleControl;
use Lomkit\Access\Tests\Fixtures\Search\Search;
use Lomkit\Access\Tests\Fixtures\Testing\AccessTestCase;
use Lomkit\Rest\RestServiceProvider;
use PHPUnit\Framework\Attributes\Test;

abstract class RestApiEngineTestCase extends AccessTestCase
{
    protected User $ada;

    protected User $grace;

    abstract protected function driver(): string;

    abstract protected function hostVariable(): string;

    /** @param  Application  $app */
    abstract protected function configureDriver($app): void;

    protected function prepareIndex(string $index): void {}

    protected function waitForIndexing(string $index): void {}

    #[\Override]
    protected function fixtures(): array
    {
        return [new Blog, new Search];
    }

    #[\Override]
    protected function getPackageProviders($app): array
    {
        return [RestServiceProvider::class, ...parent::getPackageProviders($app), ScoutServiceProvider::class];
    }

    #[\Override]
    protected function defineRoutes($router): void
    {
        RestApiSearch::routes();
    }

    #[\Override]
    protected function defineEnvironment($app): void
    {
        $app['config']->set('scout.prefix', sprintf('lac_rest_%s_', bin2hex(random_bytes(4))));
        $app['config']->set('scout.queue', false);
        $app['config']->set('scout.after_commit', false);

        $this->configureDriver($app);
    }

    #[\Override]
    protected function setUp(): void
    {
        if (getenv($this->hostVariable()) === false) {
            $this->markTestSkipped(sprintf('Set %s to run the tests against a %s server.', $this->hostVariable(), $this->driver()));
        }

        parent::setUp();

        $this->app['config']->set('scout.driver', $this->driver());
        $this->beforeApplicationDestroyed(function (): void {
            $this->app->make(EngineManager::class)->engine()->deleteIndex((new Article)->searchableAs());
        });
        Access::registerPolicies();

        $this->ada = User::factory()->createOne(['name' => 'Ada', 'email' => 'ada@example.com']);
        $this->grace = User::factory()->createOne(['name' => 'Grace', 'email' => 'grace@example.com']);
        $this->prepareIndex((new Article)->searchableAs());
        Article::factory()->createOne(['author_id' => $this->ada->getKey(), 'title' => 'Scout primer']);
        Article::factory()->createOne(['author_id' => $this->grace->getKey(), 'title' => 'Scout recipes', 'featured' => true]);
        Article::factory()->createOne(['author_id' => $this->grace->getKey(), 'title' => 'Scout internals']);
        $this->waitForDocuments(3);
    }

    #[Test]
    public function it_finds_through_the_engine_the_articles_that_a_controlled_search_finds(): void
    {
        Gate::define('view own articles', fn (User $user): bool => true);
        Gate::define('view featured articles', fn (User $user): bool => $user->is($this->ada));

        $this->assertSame(['Scout primer', 'Scout recipes'], $this->found($this->ada));
        $this->assertSame(['Scout internals', 'Scout recipes'], $this->found($this->grace));
        $this->assertSame($this->controlledTitles($this->ada), $this->found($this->ada));
        $this->assertSearchAgrees(ArticleControl::class, [$this->ada, $this->grace]);
    }

    #[Test]
    public function it_finds_every_article_for_an_unrestricted_reader(): void
    {
        Gate::define('view global articles', fn (User $user): bool => $user->is($this->grace));
        Gate::define('view own articles', fn (User $user): bool => true);

        $this->assertSame(['Scout internals', 'Scout primer', 'Scout recipes'], $this->found($this->grace));
        $this->assertSame(['Scout primer'], $this->found($this->ada));
    }

    /** @return list<string> */
    private function found(User $reader): array
    {
        $titles = array_column(
            $this->actingAs($reader)->postJson('/articles/search', ['search' => ['text' => ['value' => 'Scout']]])->assertOk()->json('data'),
            'title',
        );
        sort($titles);

        return $titles;
    }

    /** @return list<string> */
    private function controlledTitles(User $reader): array
    {
        return Article::search('Scout')->controlledFor($reader)->get()->pluck('title')->sort()->values()->all();
    }

    private function waitForDocuments(int $count): void
    {
        $this->waitForIndexing((new Article)->searchableAs());

        for ($attempt = 0; $attempt < 50 && $this->indexed() < $count; $attempt++) {
            usleep(100000);
        }

        $this->assertSame($count, $this->indexed(), 'The engine did not serve the indexed articles.');
    }

    private function indexed(): int
    {
        return $this->app->make(EngineManager::class)->engine()->getTotalCount(Article::search('Scout')->uncontrolled()->raw());
    }
}
