<?php

namespace Lomkit\Access\Tests\Feature\Search;

use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Exceptions\UnsearchableBoundaryException;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Search\SearchKeyDigest;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Blog\WorkspaceBoundary;
use Lomkit\Access\Tests\Fixtures\ConfiguredArticleControl;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\AuthorPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\SearchTestCase;
use Lomkit\Access\Tests\Fixtures\Search\WorkspaceSearchBoundary;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class SearchBoundaryTest extends SearchTestCase
{
    protected User $ada;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->ada = $this->user('Ada', workspace: 1);
        Article::factory()->createOne(['author_id' => $this->ada->getKey(), 'title' => 'Scout primer', 'workspace_id' => 1]);
        Article::factory()->createOne(['author_id' => $this->ada->getKey(), 'title' => 'Scout recipes', 'workspace_id' => 2]);
        Article::factory()->createOne(['title' => 'Scout internals', 'workspace_id' => 1]);
        $this->actingAs($this->ada);
    }

    protected function unrestricted(): Levels
    {
        return Levels::of(
            GlobalPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->unrestricted(),
        );
    }

    protected function author(): AuthorPerimeter
    {
        return AuthorPerimeter::new()
            ->allowed(fn (User $user): bool => true)
            ->searchKeys('author', fn (Article $article): mixed => $article->author_id, fn (User $user): mixed => $user->getKey());
    }

    #[Test]
    public function it_keeps_an_unrestricted_reader_inside_a_column_boundary(): void
    {
        ConfiguredArticleControl::bindWithin(Boundary::where('workspace_id', fn (User $user): ?int => $user->current_workspace_id), $this->unrestricted());
        $this->reindex();

        $this->assertSame(['Scout internals', 'Scout primer'], $this->searchedTitles());
        $this->assertSame([
            '_boundary' => SearchKeyDigest::all(['workspace_id.1']),
        ], Article::search('Scout')->controlled()->whereIns);
    }

    #[Test]
    public function it_applies_the_boundary_and_the_perimeters_together(): void
    {
        ConfiguredArticleControl::bindWithin(Boundary::where('workspace_id', fn (User $user): ?int => $user->current_workspace_id), $this->author());
        $this->reindex();

        $this->assertSame(['Scout primer'], $this->searchedTitles());
    }

    #[Test]
    public function it_returns_an_empty_page_without_calling_the_engine_for_a_null_boundary_value(): void
    {
        ConfiguredArticleControl::bindWithin(Boundary::where('workspace_id', fn (User $user): ?int => null), $this->unrestricted());
        $this->reindex();

        $this->assertSame([], $this->searchedTitles());
        $this->assertSame([], $this->engine->searches);
    }

    #[Test]
    public function it_searches_through_the_search_form_of_a_boundary_class(): void
    {
        ConfiguredArticleControl::bindWithin(new WorkspaceSearchBoundary, $this->unrestricted());
        $this->reindex();

        $this->assertSame(['Scout internals', 'Scout primer'], $this->searchedTitles());
        $this->assertSame(SearchKeyDigest::all(['workspace.1']), $this->document(Article::query()->where('title', 'Scout primer')->firstOrFail())['_boundary']);
    }

    #[Test]
    public function it_refuses_a_controlled_search_through_a_boundary_without_a_search_form(): void
    {
        $control = ConfiguredArticleControl::bindWithin(new WorkspaceBoundary, $this->unrestricted());

        $this->expectException(UnsearchableBoundaryException::class);
        $this->expectExceptionMessage(sprintf('The [%s] boundary of the [%s] control has no search form', WorkspaceBoundary::class, $control::class));

        Article::search('Scout')->controlled();
    }

    #[Test]
    public function it_refuses_it_even_without_a_signed_in_user(): void
    {
        ConfiguredArticleControl::bindWithin(new WorkspaceBoundary, $this->unrestricted());
        $this->app['auth']->forgetGuards();

        $this->expectException(UnsearchableBoundaryException::class);

        Article::search('Scout')->controlled();
    }

    #[Test]
    #[WithConfig('scout.driver', 'database')]
    public function it_applies_a_boundary_without_a_search_form_through_the_sql_of_the_database_engine(): void
    {
        ConfiguredArticleControl::bindWithin(new WorkspaceBoundary, $this->unrestricted());

        $this->assertSame(['Scout internals', 'Scout primer'], $this->searchedTitles());
    }
}
