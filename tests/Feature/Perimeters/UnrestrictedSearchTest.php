<?php

namespace Lomkit\Access\Tests\Feature\Perimeters;

use Illuminate\Support\Facades\Gate;
use Laravel\Scout\Builder as ScoutBuilder;
use Laravel\Scout\ScoutServiceProvider;
use Lomkit\Access\Exceptions\QueryCallbackReturnedAnotherBuilderException;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredArticleControl;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\AuthorPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\FeaturedPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\Search;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class UnrestrictedSearchTest extends PackageTestCase
{
    protected User $ada;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Blog, new Search];
    }

    #[\Override]
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            ScoutServiceProvider::class,
        ];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->ada = User::factory()->createOne(['name' => 'Ada', 'email' => 'ada@example.com']);
        $grace = User::factory()->createOne(['name' => 'Grace', 'email' => 'grace@example.com']);
        Article::factory()->createOne(['author_id' => $this->ada->getKey(), 'title' => 'Scout primer']);
        Article::factory()->createOne(['author_id' => $grace->getKey(), 'title' => 'Scout recipes', 'featured' => true]);
        Article::factory()->createOne(['author_id' => $grace->getKey(), 'title' => 'Scout internals']);
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
        $this->actingAs($this->ada);

        return Article::search('Scout')->controlled()->get()->pluck('title')->sort()->values()->all();
    }

    #[Test]
    public function it_searches_every_article_through_an_unrestricted_perimeter(): void
    {
        $this->allow(['view global articles']);

        $this->assertSame(['Scout internals', 'Scout primer', 'Scout recipes'], $this->searchedTitles());
    }

    #[Test]
    public function it_searches_every_article_for_a_reader_with_the_global_and_the_featured_permissions(): void
    {
        $this->allow(['view global articles', 'view featured articles']);

        $this->actingAs($this->ada);
        $search = Article::search('Scout')->controlled();

        $this->assertSame([], $search->wheres);
        $this->assertNull($search->callback);
        $this->assertSame(['Scout internals', 'Scout primer', 'Scout recipes'], $this->searchedTitles());
    }

    #[Test]
    public function it_searches_the_featured_articles_when_the_unrestricted_perimeter_does_not_allow_the_reader(): void
    {
        $this->allow(['view featured articles', 'update global articles']);

        $this->assertSame(['Scout recipes'], $this->searchedTitles());
    }

    #[Test]
    public function it_accepts_a_scout_callback_that_returns_nothing(): void
    {
        $this->allow(['view featured articles']);

        $this->assertSame(['Scout recipes'], $this->searchedTitles());
    }

    #[Test]
    public function it_accepts_a_scout_callback_that_returns_the_builder_it_receives(): void
    {
        $this->allow(['view own articles']);

        $this->assertSame(['Scout primer'], $this->searchedTitles());
    }

    #[Test]
    public function it_throws_when_a_scout_callback_returns_another_builder(): void
    {
        ConfiguredArticleControl::bind(
            AuthorPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->scoutQuery(fn (ScoutBuilder $query, User $user): ScoutBuilder => Article::search('Scout')->where('author_id', $user->getKey())),
        );

        $this->expectException(QueryCallbackReturnedAnotherBuilderException::class);
        $this->expectExceptionMessage(sprintf(
            'The [scoutQuery] callback of the [%s] perimeter in the [%s] control returned another builder.',
            AuthorPerimeter::class,
            ConfiguredArticleControl::class,
        ));

        $this->searchedTitles();
    }

    #[Test]
    public function it_does_not_evaluate_the_scout_callbacks_of_the_other_applicable_perimeters(): void
    {
        $evaluated = [];

        ConfiguredArticleControl::bind(
            FeaturedPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->scoutQuery(function (ScoutBuilder $query) use (&$evaluated): void {
                    $evaluated[] = 'featured';
                    $query->where('featured', true);
                }),
            GlobalPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->unrestricted(),
        );

        $this->assertCount(3, $this->searchedTitles());
        $this->assertSame([], $evaluated);
    }
}
