<?php

namespace Lomkit\Access\Tests\Feature\Search;

use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Exceptions\InvalidSearchKeyValueException;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredArticleControl;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\AuthorPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\Notice;
use Lomkit\Access\Tests\Fixtures\Search\SearchTestCase;
use Lomkit\Access\Tests\Fixtures\Search\SharedPerimeter;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class AccessMetadataTest extends SearchTestCase
{
    #[Test]
    public function it_adds_the_access_keys_to_the_document_without_touching_the_searchable_array(): void
    {
        $ada = $this->user('Ada');
        $article = Article::factory()->createOne(['author_id' => $ada->getKey(), 'title' => 'Scout primer', 'featured' => true]);

        $this->assertSame(
            ['id' => (string) $article->getKey(), 'author_id' => $ada->getKey(), 'title' => 'Scout primer', 'featured' => true],
            $article->toSearchableArray(),
        );
        $this->assertSame([
            ...$article->toSearchableArray(),
            '_access' => ["author.{$ada->getKey()}", 'featured.yes'],
            '_boundary' => [],
        ], $this->document($article));
    }

    #[Test]
    public function it_gives_no_key_for_a_null_value(): void
    {
        $article = Article::factory()->createOne(['author_id' => null, 'title' => 'Scout primer']);

        $this->assertSame([], $this->document($article)['_access']);
    }

    #[Test]
    public function it_prefixes_the_keys_with_the_name_of_their_perimeter(): void
    {
        ConfiguredArticleControl::bind(
            AuthorPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->searchKeys('author', fn (Article $article): mixed => $article->author_id, fn (User $user): mixed => $user->getKey()),
            SharedPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->searchKeys('editor', fn (Article $article): mixed => $article->author_id, fn (User $user): mixed => $user->getKey()),
        );

        $article = Article::factory()->createOne(['author_id' => 7, 'title' => 'Scout primer']);

        $this->assertSame(['author.7', 'editor.7'], $this->document($article)['_access']);
    }

    #[Test]
    public function it_writes_composite_keys_and_lists_of_keys(): void
    {
        ConfiguredArticleControl::bind(
            ClientPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->searchKeys('brand', fn (Article $article): array => [['fr', $article->author_id], ['de', null]], fn (User $user): array => []),
            SharedPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->searchKeys('shared', fn (Article $article): array => [3, '3', 'a.7', 'Ada', null], fn (User $user): array => []),
        );

        $article = Article::factory()->createOne(['author_id' => 7, 'title' => 'Scout primer']);

        $this->assertSame(['brand.fr.7', 'shared.3', 'shared._612e37', 'shared._416461'], $this->document($article)['_access']);
    }

    #[Test]
    public function it_writes_the_keys_of_a_column_boundary(): void
    {
        ConfiguredArticleControl::bindWithin(
            Boundary::where('workspace_id', fn (User $user): ?int => $user->current_workspace_id),
            Levels::of(GlobalPerimeter::new()->allowed(fn (User $user): bool => true)->unrestricted()),
        );

        $article = Article::factory()->createOne(['workspace_id' => 3, 'title' => 'Scout primer']);
        $orphan = Article::factory()->createOne(['workspace_id' => null, 'title' => 'Scout internals']);

        $this->assertSame(['workspace_id.3'], $this->document($article)['_boundary']);
        $this->assertSame([], $this->document($orphan)['_boundary']);
        $this->assertSame([], $this->document($article)['_access']);
    }

    #[Test]
    public function it_loads_the_declared_relations_once_per_indexed_batch(): void
    {
        $acme = Client::factory()->createOne(['name' => 'Acme']);
        $globex = Client::factory()->createOne(['name' => 'Globex']);
        $website = Project::factory()->for($acme)->createOne(['name' => 'Website']);
        $warehouse = Project::factory()->for($globex)->createOne(['name' => 'Warehouse']);
        ConfiguredArticleControl::bind(
            ClientPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->searchKeys(
                    name: 'client',
                    row: fn (Article $article): mixed => $article->project?->client_id,
                    reader: fn (User $user): mixed => $user->client_id,
                    load: ['project:id,client_id'],
                ),
        );
        Article::withoutSyncingToSearch(function () use ($website, $warehouse): void {
            Article::factory()->for($website)->createOne(['title' => 'Scout primer']);
            Article::factory()->for($warehouse)->createOne(['title' => 'Scout recipes']);
            Article::factory()->for($website)->createOne(['title' => 'Scout internals']);
        });
        $articles = Article::query()->orderBy('id')->get();

        $this->app['db']->enableQueryLog();
        $articles->searchable();
        $queries = $this->app['db']->getQueryLog();

        $this->assertCount(1, $queries);
        $this->assertSame(
            [["client.{$acme->getKey()}"], ["client.{$globex->getKey()}"], ["client.{$acme->getKey()}"]],
            $articles->map(fn (Article $article): mixed => $this->document($article)['_access'])->all(),
        );
        $this->assertFalse($articles->first()?->relationLoaded('project'));
    }

    #[Test]
    public function it_throws_when_a_key_callback_returns_a_value_that_is_not_a_key(): void
    {
        ConfiguredArticleControl::bind(
            ClientPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->searchKeys('client', fn (Article $article): float => 1.5, fn (User $user): int => 1),
        );

        $this->expectException(InvalidSearchKeyValueException::class);
        $this->expectExceptionMessage(sprintf('The [client] search keys received a value of type [float] for the [%s] model.', Article::class));

        Article::factory()->createOne(['title' => 'Scout primer']);
    }

    #[Test]
    #[WithConfig('scout.driver', 'database')]
    public function it_computes_no_key_for_the_database_engine(): void
    {
        $computed = 0;
        ConfiguredArticleControl::bind(
            ClientPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->searchKeys('client', function (Article $article) use (&$computed): int {
                    $computed++;

                    return 1;
                }, fn (User $user): int => 1),
        );

        $article = Article::factory()->createOne(['title' => 'Scout primer']);

        $this->assertSame(0, $computed);
        $this->assertSame([], $article->scoutMetadata());
    }

    #[Test]
    public function it_writes_the_keys_when_the_model_overrides_makesearchableusing_and_calls_withaccesskeys(): void
    {
        $notice = Notice::query()->create(['title' => 'release']);

        $this->assertSame(['titled.release'], $notice->scoutMetadata()['_access']);
        $this->assertSame(['titled.release'], $this->engine->documents[$notice->searchableAs()][(string) $notice->getKey()]['_access']);
    }
}
