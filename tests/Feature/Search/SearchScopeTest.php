<?php

namespace Lomkit\Access\Tests\Feature\Search;

use Closure;
use Laravel\Scout\Builder;
use Lomkit\Access\Exceptions\ConflictingSearchScopeException;
use Lomkit\Access\Exceptions\MissingControlException;
use Lomkit\Access\Exceptions\MissingControlledSearchableException;
use Lomkit\Access\Exceptions\UndeclaredSearchScopeException;
use Lomkit\Access\Search\ControlledSearchBuilder;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\Page;
use Lomkit\Access\Tests\Fixtures\Search\SearchTestCase;
use Lomkit\Access\Tests\Fixtures\Search\Snippet;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class SearchScopeTest extends SearchTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Article::factory()->createOne(['title' => 'Scout primer']);
        Article::factory()->createOne(['title' => 'Scout recipes']);
    }

    /** @return array<string, array{Closure(Builder<Article>): mixed}> */
    public static function executions(): array
    {
        return [
            'get' => [fn (Builder $search): mixed => $search->get()],
            'first' => [fn (Builder $search): mixed => $search->first()],
            'keys' => [fn (Builder $search): mixed => $search->keys()],
            'raw' => [fn (Builder $search): mixed => $search->raw()],
            'cursor' => [fn (Builder $search): mixed => $search->cursor()],
            'paginate' => [fn (Builder $search): mixed => $search->paginate(10)],
            'simplePaginate' => [fn (Builder $search): mixed => $search->simplePaginate(10)],
            'paginateRaw' => [fn (Builder $search): mixed => $search->paginateRaw(10)],
        ];
    }

    /** @param  Closure(Builder<Article>): mixed  $execute */
    #[Test]
    #[DataProvider('executions')]
    public function it_refuses_to_run_a_search_that_declares_neither_controlled_nor_uncontrolled(Closure $execute): void
    {
        $this->expectException(UndeclaredSearchScopeException::class);
        $this->expectExceptionMessage(sprintf('The search of the [%s] model declares neither controlled() nor uncontrolled().', Article::class));

        $execute(Article::search('Scout')->where('featured', false));
    }

    #[Test]
    public function it_searches_every_row_on_purpose_with_uncontrolled(): void
    {
        $search = Article::search('Scout')->uncontrolled();

        $this->assertInstanceOf(ControlledSearchBuilder::class, $search);
        $this->assertCount(2, $search->get());
        $this->assertSame([], $search->whereIns);
    }

    #[Test]
    public function it_refuses_a_search_that_declares_both_scopes(): void
    {
        $this->expectException(ConflictingSearchScopeException::class);
        $this->expectExceptionMessage(sprintf('The search of the [%s] model declares both controlled() and uncontrolled().', Article::class));

        Article::search('Scout')->uncontrolled()->controlled();
    }

    #[Test]
    public function it_accepts_the_same_scope_declared_twice(): void
    {
        $this->actingAs($this->user('Ada'));
        $this->allow(['view global articles']);

        $this->assertCount(2, Article::search('Scout')->controlled()->controlled()->get());
        $this->assertCount(2, Article::search('Scout')->uncontrolled()->uncontrolled()->get());
    }

    #[Test]
    public function it_refuses_a_controlled_search_on_a_model_that_uses_the_searchable_trait_of_scout(): void
    {
        $this->expectException(MissingControlledSearchableException::class);
        $this->expectExceptionMessage(sprintf('The [%s] model uses Laravel Scout\'s Searchable trait, so its documents carry no access keys.', Snippet::class));

        Snippet::search('Scout')->controlled();
    }

    #[Test]
    public function it_throws_on_a_controlled_search_without_a_control(): void
    {
        $this->expectException(MissingControlException::class);
        $this->expectExceptionMessage(sprintf('No control is registered for the [%s] model.', Page::class));

        Page::search('Scout')->controlled();
    }
}
