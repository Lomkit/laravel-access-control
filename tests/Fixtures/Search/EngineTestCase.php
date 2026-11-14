<?php

namespace Lomkit\Access\Tests\Fixtures\Search;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Gate;
use Laravel\Scout\Builder;
use Laravel\Scout\EngineManager;
use Laravel\Scout\ScoutServiceProvider;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredArticleControl;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

abstract class EngineTestCase extends PackageTestCase
{
    protected User $ada;

    protected User $grace;

    abstract protected function driver(): string;

    abstract protected function hostVariable(): string;

    /** @param  Application  $app */
    abstract protected function configureDriver($app): void;

    protected function prepareIndex(string $index): void {}

    #[\Override]
    protected function fixtures(): array
    {
        return [new Blog, new Search];
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

        $this->ada = User::factory()->createOne(['name' => 'Ada', 'email' => 'ada@example.com']);
        $this->grace = User::factory()->createOne(['name' => 'Grace', 'email' => 'grace@example.com']);
        $this->prepareIndex((new Article)->searchableAs());
        Article::factory()->createOne(['author_id' => $this->ada->getKey(), 'title' => 'Scout primer']);
        Article::factory()->createOne(['author_id' => $this->grace->getKey(), 'title' => 'Scout recipes', 'featured' => true]);
        Article::factory()->createOne(['author_id' => $this->grace->getKey(), 'title' => 'Scout internals']);
        $this->waitForDocuments(3);
        $this->actingAs($this->ada);
    }

    #[\Override]
    protected function defineEnvironment($app): void
    {
        $app['config']->set('scout.prefix', sprintf('lac_%s_', bin2hex(random_bytes(4))));
        $app['config']->set('scout.queue', false);
        $app['config']->set('scout.after_commit', false);

        $this->configureDriver($app);
    }

    #[\Override]
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            ScoutServiceProvider::class,
        ];
    }

    protected function waitForDocuments(int $count): void
    {
        for ($attempt = 0; $attempt < 50 && $this->totalCount(Article::search('Scout')->uncontrolled()) < $count; $attempt++) {
            usleep(100000);
        }

        $this->assertSame($count, $this->totalCount(Article::search('Scout')->uncontrolled()), 'The engine did not serve the indexed articles.');
    }

    /** @param  Builder<Article>  $search */
    protected function totalCount(Builder $search): int
    {
        return $this->app->make(EngineManager::class)->engine()->getTotalCount($search->raw());
    }

    /** @param  list<string>  $abilities */
    protected function allow(array $abilities): void
    {
        foreach ($abilities as $ability) {
            Gate::define($ability, fn (User $user): bool => true);
        }
    }

    /** @return list<string> */
    protected function searchedTitles(): array
    {
        return Article::search('Scout')
            ->controlled()
            ->get()
            ->pluck('title')
            ->sort()
            ->values()
            ->all();
    }

    #[Test]
    public function it_keeps_the_documents_that_share_at_least_one_key_with_the_reader(): void
    {
        $this->allow(['view own articles', 'view featured articles']);

        $this->assertSame(['Scout primer', 'Scout recipes'], $this->searchedTitles());
        $this->assertSame(2, Article::search('Scout')->controlled()->paginate(10)->total());
    }

    #[Test]
    public function it_leaves_out_the_documents_that_share_no_key_with_the_reader(): void
    {
        $this->allow(['view own articles']);

        $this->assertSame(['Scout primer'], $this->searchedTitles());
    }

    #[Test]
    public function it_filters_on_the_version_only_for_an_unrestricted_reader(): void
    {
        $this->allow(['view global articles']);

        $this->assertSame(['Scout internals', 'Scout primer', 'Scout recipes'], $this->searchedTitles());
    }

    #[Test]
    public function it_returns_an_empty_page_when_no_perimeter_allows_the_reader(): void
    {
        $search = Article::search('Scout')->controlled();

        $this->assertCount(0, $search->get());
        $this->assertCount(0, $search->keys());
        $this->assertSame(0, $search->paginate(10)->total());
        $this->assertFalse($search->simplePaginate(10)->hasMorePages());
    }

    #[Test]
    public function it_leaves_out_a_document_indexed_with_another_version_of_the_key_rules(): void
    {
        $stale = Article::withoutSyncingToSearch(fn (): Article => Article::factory()->createOne(['author_id' => $this->ada->getKey(), 'title' => 'Scout drafts']));
        $stale->withScoutMetadata('_access', ["author.{$this->ada->getKey()}"]);
        $stale->withScoutMetadata('_boundary', []);
        $stale->withScoutMetadata('_access_version', 'v000000000000');
        $stale->searchableUsing()->update($stale->newCollection([$stale]));
        $this->waitForDocuments(4);
        $this->allow(['view own articles']);

        $this->assertSame(['Scout primer'], $this->searchedTitles());
    }

    #[Test]
    public function it_keeps_the_documents_inside_the_boundary_of_the_reader(): void
    {
        ConfiguredArticleControl::bindWithin(
            Boundary::where('workspace_id', fn (User $user): ?int => $user->current_workspace_id),
            AuthorPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->searchKeys('author', fn (Article $article): array => [$article->author_id, 'Ada'], fn (User $user): array => [$user->getKey(), $user->name]),
        );
        Article::factory()->createOne(['author_id' => $this->grace->getKey(), 'title' => 'Scout drafts', 'workspace_id' => 2]);
        Article::query()->get()->searchable();
        $this->waitForDocuments(4);

        $this->assertSame(['Scout internals', 'Scout primer', 'Scout recipes'], $this->searchedTitles());
    }
}
