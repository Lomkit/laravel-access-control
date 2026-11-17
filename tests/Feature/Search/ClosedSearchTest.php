<?php

namespace Lomkit\Access\Tests\Feature\Search;

use Closure;
use Illuminate\Support\Facades\Auth;
use Laravel\Scout\Builder;
use Laravel\Scout\EngineManager;
use Lomkit\Access\Access;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredArticleControl;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\CallbackBlindEngine;
use Lomkit\Access\Tests\Fixtures\Search\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\SearchTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class ClosedSearchTest extends SearchTestCase
{
    protected User $ada;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Access::trustSearchEngine(CallbackBlindEngine::class);
        $engine = $this->engine = new CallbackBlindEngine;
        $manager = $this->app->make(EngineManager::class);
        $manager->extend('memory', fn (): CallbackBlindEngine => $engine);
        $manager->forgetDrivers();

        $this->ada = $this->user('Ada');
        Article::factory()->createOne(['author_id' => $this->ada->getKey(), 'title' => 'Scout primer']);
        Article::factory()->createOne(['author_id' => $this->ada->getKey(), 'title' => 'Scout recipes', 'featured' => true]);
    }

    #[Test]
    public function it_finds_the_rows_of_an_open_reader_on_an_engine_that_ignores_the_search_callback(): void
    {
        $this->allow(['view global articles']);

        $this->assertCount(2, Article::search('Scout')->controlledFor($this->ada)->get());
        $this->assertCount(1, $this->engine->searches);
    }

    #[Test]
    public function it_never_calls_an_engine_that_ignores_the_search_callback_without_a_signed_in_user(): void
    {
        $this->allow(['view global articles']);
        Auth::forgetGuards();

        $this->assertSearchesNothing(fn (): Builder => Article::search('Scout')->controlled());
    }

    #[Test]
    public function it_never_calls_an_engine_that_ignores_the_search_callback_when_controlled_for_no_reader(): void
    {
        $this->allow(['view global articles']);

        $this->assertSearchesNothing(fn (): Builder => Article::search('Scout')->controlledFor(null));
    }

    #[Test]
    public function it_never_calls_an_engine_that_ignores_the_search_callback_when_no_perimeter_allows_the_reader(): void
    {
        $this->actingAs($this->ada);

        $this->assertSearchesNothing(fn (): Builder => Article::search('Scout')->controlled());
        $this->assertSearchesNothing(fn (): Builder => Article::search('Scout')->controlledFor($this->ada));
    }

    #[Test]
    public function it_never_calls_an_engine_that_ignores_the_search_callback_when_the_reader_has_no_key(): void
    {
        ConfiguredArticleControl::bind(
            ClientPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->searchKeys('client', fn (Article $article): int => 1, fn (User $user): ?int => null),
        );
        Article::query()->get()->searchable();

        $this->assertSearchesNothing(fn (): Builder => Article::search('Scout')->controlledFor($this->ada));
    }

    /** @param  Closure(): Builder<Article>  $search */
    protected function assertSearchesNothing(Closure $search): void
    {
        $this->assertCount(0, $search()->get());
        $this->assertSame(0, $search()->paginate(10)->total());
        $this->assertCount(0, $search()->paginate(10)->items());
        $this->assertCount(0, $search()->simplePaginate(10)->items());
        $this->assertFalse($search()->simplePaginate(1)->hasMorePages());
        $this->assertCount(0, $search()->keys());
        $this->assertSame([], $search()->raw());
        $this->assertCount(0, $search()->cursor());
        $this->assertSame(0, $search()->paginateRaw(10)->total());
        $this->assertSame([], $this->engine->searches);
    }
}
