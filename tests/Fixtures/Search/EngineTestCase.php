<?php

namespace Lomkit\Access\Tests\Fixtures\Search;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Laravel\Scout\Builder;
use Laravel\Scout\EngineManager;
use Laravel\Scout\ScoutServiceProvider;
use Lomkit\Access\Access;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Search\Tokens\SearchToken;
use Lomkit\Access\Search\Tokens\SearchTokenKey;
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

    abstract protected function searchOnlyKey(string $index): SearchTokenKey;

    /** @return class-string<\Throwable> */
    abstract protected function refusedSearch(): string;

    /** @return list<string> */
    abstract protected function titlesFoundWith(SearchToken $token, string $index): array;

    protected function prepareIndex(string $index): void {}

    protected function waitForIndexing(string $index): void {}

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
        $this->waitForIndexing((new Article)->searchableAs());

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
    public function it_applies_no_filter_for_an_unrestricted_reader(): void
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

    #[Test]
    public function it_finds_the_children_of_the_parents_the_reader_may_view(): void
    {
        File::delete([$this->app->bootstrapPath('cache/access-controls.php'), $this->app->bootstrapPath('cache/access-reindex.php')]);
        Access::discoverControlsIn([__DIR__, dirname(__DIR__).'/Threads/Folders']);
        ConfiguredArticleControl::bind(ThroughParent::belongsTo('folder', inverse: 'articles'));
        $this->allow(['view client folders']);
        $handbook = Folder::query()->create(['client_id' => $this->ada->client_id, 'name' => 'Handbook']);
        $playbook = Folder::query()->create(['client_id' => $this->grace->client_id, 'name' => 'Playbook']);
        Article::factory()->createOne(['author_id' => $this->grace->getKey(), 'title' => 'Scout in a folder', 'folder_id' => $handbook->getKey()]);
        Article::factory()->createOne(['author_id' => $this->ada->getKey(), 'title' => 'Scout in another folder', 'folder_id' => $playbook->getKey()]);
        Article::query()->get()->searchable();
        $this->waitForDocuments(5);

        $this->assertSame(['Scout in a folder'], $this->searchedTitles());
        $this->assertSame(['Scout in another folder'], $this->controlledTitles($this->grace));

        $handbook->update(['client_id' => $this->grace->client_id]);
        $this->waitForIndexing((new Article)->searchableAs());

        $this->assertSame([], $this->searchedTitles());
        $this->assertSame(['Scout in a folder', 'Scout in another folder'], $this->controlledTitles($this->grace));
    }

    /** @return list<string> */
    protected function controlledTitles(?User $reader): array
    {
        return Article::search('Scout')
            ->controlledFor($reader)
            ->get()
            ->pluck('title')
            ->sort()
            ->values()
            ->all();
    }

    /** @return list<string> */
    protected function tokenTitles(?User $reader, SearchTokenKey $key): array
    {
        $token = Access::searchToken($reader, Article::class, $key, now()->addMinutes(10));
        $titles = $this->titlesFoundWith($token, $token->index());
        sort($titles);

        return $titles;
    }

    /** @param  array<string, list<int>>  $grants */
    protected function grant(array $grants): void
    {
        foreach ($grants as $ability => $readers) {
            Gate::define($ability, fn (User $user): bool => in_array($user->getKey(), $readers, true));
        }
    }

    protected function reader(string $name, int $workspace = 1): User
    {
        return User::factory()->createOne(['name' => $name, 'email' => strtolower($name).'@example.com', 'current_workspace_id' => $workspace]);
    }

    #[Test]
    public function it_finds_with_a_search_token_exactly_the_documents_of_a_controlled_search(): void
    {
        $key = $this->searchOnlyKey((new Article)->searchableAs());
        $carol = $this->reader('Carol');
        $dan = $this->reader('Dan');
        $this->grant([
            'view global articles' => [$dan->getKey()],
            'view own articles' => [$this->ada->getKey(), $this->grace->getKey()],
            'view featured articles' => [$this->ada->getKey()],
        ]);

        $readers = [
            'no reader' => [null, []],
            'a reader no perimeter allows' => [$carol, []],
            'an unrestricted reader' => [$dan, ['Scout internals', 'Scout primer', 'Scout recipes']],
            'a reader of one level' => [$this->grace, ['Scout internals', 'Scout recipes']],
            'a reader with several keys' => [$this->ada, ['Scout primer', 'Scout recipes']],
        ];

        foreach ($readers as $label => [$reader, $expected]) {
            $this->assertSame($expected, $this->controlledTitles($reader), "The controlled search for {$label}.");
            $this->assertSame($expected, $this->tokenTitles($reader, $key), "The search token for {$label}.");
        }
    }

    #[Test]
    public function it_finds_with_a_search_token_exactly_the_documents_inside_the_boundary_of_the_reader(): void
    {
        ConfiguredArticleControl::bindWithin(
            Boundary::where('workspace_id', fn (User $user): ?int => $user->current_workspace_id),
            AuthorPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->searchKeys('author', fn (Article $article): array => [$article->author_id, 'Ada'], fn (User $user): array => [$user->getKey(), $user->name]),
        );
        $eve = $this->reader('Eve', workspace: 2);
        Article::factory()->createOne(['author_id' => $eve->getKey(), 'title' => 'Scout drafts', 'workspace_id' => 2]);
        Article::query()->get()->searchable();
        $this->waitForDocuments(4);
        $key = $this->searchOnlyKey((new Article)->searchableAs());

        $readers = [
            'a reader whose escaped name is a key of every row' => [$this->ada, ['Scout internals', 'Scout primer', 'Scout recipes']],
            'a reader with one key' => [$this->grace, ['Scout internals', 'Scout recipes']],
            'a reader in another workspace' => [$eve, ['Scout drafts']],
        ];

        foreach ($readers as $label => [$reader, $expected]) {
            $this->assertSame($expected, $this->controlledTitles($reader), "The controlled search for {$label}.");
            $this->assertSame($expected, $this->tokenTitles($reader, $key), "The search token for {$label}.");
        }
    }

    #[Test]
    public function it_signs_a_token_that_finds_nothing_for_a_closed_reader(): void
    {
        $key = $this->searchOnlyKey((new Article)->searchableAs());
        $token = Access::searchToken($this->ada, Article::class, $key, now()->addMinutes(10));

        $this->assertNotNull($token->filter());
        $this->assertStringContainsString('#closed', $token->filter());
        $this->assertSame([], $this->titlesFoundWith($token, $token->index()));
        $this->assertSame([], $this->titlesFoundWith(Access::searchToken(null, Article::class, $key, now()->addMinutes(10)), $token->index()));
    }

    #[Test]
    public function it_signs_a_token_without_a_filter_for_an_unrestricted_reader(): void
    {
        $this->allow(['view global articles']);
        $token = Access::searchToken($this->ada, Article::class, $this->searchOnlyKey((new Article)->searchableAs()), now()->addMinutes(10));

        $this->assertNull($token->filter());
        $this->assertCount(3, $this->titlesFoundWith($token, $token->index()));
    }

    #[Test]
    public function it_signs_a_token_that_cannot_search_another_index(): void
    {
        $this->allow(['view global articles']);
        $token = Access::searchToken($this->ada, Article::class, $this->searchOnlyKey((new Article)->searchableAs()), now()->addMinutes(10));

        $this->assertSearchIsRefused(fn () => $this->titlesFoundWith($token, (new Article)->searchableAs().'_other'));
    }

    protected function assertSearchIsRefused(callable $search): void
    {
        try {
            $search();
        } catch (\Throwable $exception) {
            $this->assertInstanceOf($this->refusedSearch(), $exception);

            return;
        }

        $this->fail('The search token searched another index.');
    }
}
