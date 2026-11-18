<?php

namespace Lomkit\Access\Tests\Feature\Testing;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Blog\WorkspaceBoundary;
use Lomkit\Access\Tests\Fixtures\ConfiguredArticleControl;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\ArticleControl;
use Lomkit\Access\Tests\Fixtures\Search\AuthorPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\NoticeControl;
use Lomkit\Access\Tests\Fixtures\Search\SearchTestCase;
use Lomkit\Access\Tests\Fixtures\Search\SharedPerimeter;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class SearchAgreesTest extends SearchTestCase
{
    protected User $ada;

    protected User $grace;

    protected User $hank;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $acme = Client::factory()->createOne(['name' => 'Acme']);
        $this->ada = $this->user('Ada', $acme);
        $this->grace = $this->user('Grace', $acme, workspace: 2);
        $this->hank = $this->user('Hank');
        $website = Project::factory()->for($acme)->createOne(['name' => 'Website']);
        $warehouse = Project::factory()->for($this->hank->client)->createOne(['name' => 'Warehouse']);
        Article::withoutSyncingToSearch(function () use ($website, $warehouse): void {
            Article::factory()->for($website)->createOne(['author_id' => $this->ada->getKey(), 'title' => 'Scout primer']);
            Article::factory()->for($website)->createOne(['author_id' => $this->grace->getKey(), 'title' => 'Scout recipes', 'workspace_id' => 2]);
            Article::factory()->for($warehouse)->createOne(['author_id' => $this->hank->getKey(), 'title' => 'Scout internals', 'featured' => true]);
            Article::factory()->createOne(['title' => 'Orphan']);
        });
        Article::query()->where('title', 'Scout primer')->firstOrFail()->sharedWith()->attach($this->hank);
        $this->allow(['view own articles', 'view featured articles']);
    }

    protected function client(): ClientPerimeter
    {
        return ClientPerimeter::new()
            ->allowed(fn (User $user): bool => $user->name !== 'Hank')
            ->query(function (Builder $query, User $user): void {
                $query->whereHas('project', fn (Builder $projects) => $projects->where('client_id', $user->client_id));
            })
            ->searchKeys(
                name: 'client',
                row: fn (Article $article): mixed => $article->project?->client_id,
                reader: fn (User $user): mixed => $user->client_id,
                load: ['project:id,client_id'],
            );
    }

    protected function shared(): SharedPerimeter
    {
        return SharedPerimeter::new()
            ->allowed(fn (User $user): bool => true)
            ->query(function (Builder $query, User $user): void {
                $query->whereHas('sharedWith', fn (Builder $users) => $users->whereKey($user->getKey()));
            })
            ->searchKeys(
                name: 'shared',
                row: fn (Article $article): array => $article->sharedWith->modelKeys(),
                reader: fn (User $user): mixed => $user->getKey(),
                load: ['sharedWith:id'],
            );
    }

    #[Test]
    public function it_passes_on_the_article_control(): void
    {
        $this->assertSearchAgrees(ArticleControl::class, readers: [$this->ada, $this->grace, $this->hank]);
    }

    #[Test]
    public function it_passes_on_relation_and_pivot_keys_inside_a_boundary(): void
    {
        ConfiguredArticleControl::bindWithin(
            Boundary::where('workspace_id', fn (User $user): ?int => $user->current_workspace_id),
            Levels::of(
                GlobalPerimeter::new()
                    ->allowed(fn (User $user): bool => $user->name === 'Grace')
                    ->unrestricted(),
                $this->client(),
            ),
            $this->shared(),
        );

        $this->assertSearchAgrees(ArticleControl::class, readers: [$this->ada, $this->grace, $this->hank]);
    }

    #[Test]
    public function it_fails_when_the_search_keys_describe_other_rows_than_the_query(): void
    {
        $website = Project::query()->where('name', 'Website')->firstOrFail();
        ConfiguredArticleControl::bind(
            AuthorPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->query(function (Builder $query, User $user): void {
                    $query->where('author_id', $user->getKey());
                })
                ->searchKeys('author', fn (Article $article): mixed => $article->project_id, fn (User $user): mixed => $website->getKey()),
        );
        $recipes = Article::query()->where('title', 'Scout recipes')->firstOrFail();

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage(sprintf(
            'The search keys of the [%s] control do not agree with its SQL for the [%s] reader with key [%s], whose search holds [_access {author.%s}]: the keys find the rows [%s with _access {author.%s}] that the SQL does not, and miss the rows [] that the SQL finds.',
            ArticleControl::class,
            User::class,
            $this->ada->getKey(),
            $website->getKey(),
            $recipes->getKey(),
            $website->getKey(),
        ));

        $this->assertSearchAgrees(ArticleControl::class, readers: [$this->ada]);
    }

    #[Test]
    public function it_lists_the_first_keys_of_a_reader_with_many_keys_in_its_failure(): void
    {
        $website = Project::query()->where('name', 'Website')->firstOrFail();
        ConfiguredArticleControl::bind(
            AuthorPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->query(function (Builder $query, User $user): void {
                    $query->where('author_id', $user->getKey());
                })
                ->searchKeys('author', fn (Article $article): mixed => $article->project_id, fn (User $user): array => [...range(1001, 1012), $website->getKey()]),
        );

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('whose search holds [_access {author.1001, author.1002, author.1003, author.1004, author.1005, author.1006, author.1007, author.1008, author.1009, author.1010 and 3 more}]');

        $this->assertSearchAgrees(ArticleControl::class, readers: [$this->ada]);
    }

    #[Test]
    public function it_fails_when_a_reached_perimeter_has_no_search_keys(): void
    {
        ConfiguredArticleControl::bind(
            AuthorPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->query(function (Builder $query, User $user): void {
                    $query->where('author_id', $user->getKey());
                }),
        );

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('and miss the rows [');

        $this->assertSearchAgrees(ArticleControl::class, readers: [$this->ada]);
    }

    #[Test]
    public function it_fails_without_a_reader(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage(sprintf('assertSearchAgrees() received no reader for the [%s] control', ArticleControl::class));

        $this->assertSearchAgrees(ArticleControl::class, readers: []);
    }

    #[Test]
    public function it_fails_for_a_boundary_without_a_search_form(): void
    {
        ConfiguredArticleControl::bindWithin(new WorkspaceBoundary, Levels::of(GlobalPerimeter::new()->allowed(fn (User $user): bool => true)->unrestricted()));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage(sprintf('The boundary of the [%s] control has no search form', ArticleControl::class));

        $this->assertSearchAgrees(ArticleControl::class, readers: [$this->ada]);
    }

    #[Test]
    public function it_compares_the_rows_whatever_the_search_engine(): void
    {
        config(['scout.driver' => 'database']);

        $this->assertSearchAgrees(ArticleControl::class, readers: [$this->ada, $this->hank]);
        $this->assertSearchAgrees(NoticeControl::class, readers: [$this->ada]);
    }
}
