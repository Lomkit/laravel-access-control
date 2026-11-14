<?php

namespace Lomkit\Access\Tests\Feature\Search;

use Illuminate\Database\Eloquent\Builder;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\Engine;
use Laravel\Scout\Engines\TurbopufferEngine;
use Lomkit\Access\Access;
use Lomkit\Access\Exceptions\InvalidSearchEngineException;
use Lomkit\Access\Exceptions\UnsupportedSearchEngineException;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredArticleControl;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\AuthorPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\SearchTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
#[WithConfig('scout.driver', 'database')]
class DatabaseEngineSearchTest extends SearchTestCase
{
    protected User $ada;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->ada = $this->user('Ada');
        $grace = $this->user('Grace');
        Article::factory()->createOne(['author_id' => $this->ada->getKey(), 'title' => 'Scout primer']);
        Article::factory()->createOne(['author_id' => $this->ada->getKey(), 'title' => 'Scout recipes']);
        Article::factory()->createOne(['author_id' => $grace->getKey(), 'title' => 'Scout internals', 'featured' => true]);
        Article::factory()->createOne(['author_id' => $grace->getKey(), 'title' => 'Policy guide', 'featured' => true]);
        $this->actingAs($this->ada);
    }

    #[Test]
    public function it_applies_the_sql_of_the_control_inside_the_search_query(): void
    {
        $this->allow(['view own articles', 'view featured articles']);

        $this->assertSame(['Scout internals', 'Scout primer', 'Scout recipes'], $this->searchedTitles());
        $this->assertSame(3, Article::search('Scout')->controlled()->paginate(2)->total());
        $this->assertCount(2, Article::search('Scout')->controlled()->paginate(2)->items());
    }

    #[Test]
    public function it_applies_the_sql_of_a_perimeter_without_search_keys(): void
    {
        ConfiguredArticleControl::bind(
            AuthorPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->query(function (Builder $query, User $user): void {
                    $query->where('author_id', $user->getKey());
                }),
        );

        $this->assertSame(['Scout primer', 'Scout recipes'], $this->searchedTitles());
    }

    #[Test]
    public function it_reads_no_row_without_a_signed_in_user(): void
    {
        $this->allow(['view global articles']);
        $this->app['auth']->forgetGuards();

        $this->assertSame([], $this->searchedTitles());
    }

    #[Test]
    public function it_keeps_the_query_callback_of_the_search_and_never_lets_it_widen_the_control(): void
    {
        $this->allow(['view own articles']);

        $titles = Article::search('Scout')
            ->controlled()
            ->query(function (Builder $query): void {
                $query->orWhere('featured', true)->with('author');
            })
            ->get();

        $this->assertSame(['Scout primer', 'Scout recipes'], $titles->pluck('title')->sort()->values()->all());
        $this->assertTrue($titles->first()?->relationLoaded('author'));
    }

    #[Test]
    #[WithConfig('scout.driver', 'collection')]
    public function it_refuses_a_controlled_search_on_the_collection_engine(): void
    {
        $this->expectException(UnsupportedSearchEngineException::class);
        $this->expectExceptionMessage(sprintf('A controlled search of the [%s] model cannot run on the collection engine', Article::class));

        Article::search('Scout')->controlled();
    }

    #[Test]
    #[WithConfig('scout.driver', 'collection')]
    public function it_runs_an_uncontrolled_search_on_the_collection_engine(): void
    {
        $this->assertCount(3, Article::search('Scout')->uncontrolled()->get());
    }

    #[Test]
    public function it_refuses_a_controlled_search_on_the_turbopuffer_engine(): void
    {
        if (! class_exists(TurbopufferEngine::class)) {
            $this->markTestSkipped('This Scout version has no Turbopuffer engine.');
        }

        $engine = $this->createStub(TurbopufferEngine::class);
        $this->app->make(EngineManager::class)->extend('turbopuffer-stub', fn (): TurbopufferEngine => $engine);
        config(['scout.driver' => 'turbopuffer-stub']);

        $this->expectException(UnsupportedSearchEngineException::class);
        $this->expectExceptionMessage(sprintf('A controlled search of the [%s] model cannot run on the Turbopuffer engine yet', Article::class));

        Article::search('Scout')->controlled();
    }

    #[Test]
    public function it_refuses_a_controlled_search_on_an_engine_the_package_does_not_trust(): void
    {
        $engine = $this->createStub(Engine::class);
        $this->app->make(EngineManager::class)->extend('custom', fn (): Engine => $engine);
        config(['scout.driver' => 'custom']);

        $this->expectException(UnsupportedSearchEngineException::class);
        $this->expectExceptionMessage(sprintf('A controlled search of the [%s] model cannot run on the [%s] engine', Article::class, $engine::class));

        Article::search('Scout')->controlled();
    }

    #[Test]
    public function it_filters_a_controlled_search_on_an_engine_the_application_trusts(): void
    {
        $engine = $this->createStub(Engine::class);
        $this->app->make(EngineManager::class)->extend('custom', fn (): Engine => $engine);
        config(['scout.driver' => 'custom']);
        Access::trustSearchEngine($engine::class);
        $this->allow(['view own articles']);

        $search = Article::search('Scout')->controlled();

        $this->assertSame(['_access_version', '_access'], array_keys($search->whereIns));
    }

    #[Test]
    public function it_runs_an_uncontrolled_search_on_an_engine_the_package_does_not_trust(): void
    {
        $engine = $this->createStub(Engine::class);
        $this->app->make(EngineManager::class)->extend('custom', fn (): Engine => $engine);
        config(['scout.driver' => 'custom']);

        $this->assertSame([], Article::search('Scout')->uncontrolled()->whereIns);
    }

    #[Test]
    #[WithConfig('scout.driver', 'null')]
    public function it_runs_a_controlled_search_on_the_null_engine(): void
    {
        $this->assertCount(0, Article::search('Scout')->controlled()->get());
    }

    #[Test]
    public function it_refuses_to_trust_a_class_that_is_not_a_search_engine(): void
    {
        $this->expectException(InvalidSearchEngineException::class);
        $this->expectExceptionMessage(sprintf('The [%s] class cannot be trusted as a search engine', User::class));

        Access::trustSearchEngine(User::class);
    }
}
