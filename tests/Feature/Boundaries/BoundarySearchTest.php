<?php

namespace Lomkit\Access\Tests\Feature\Boundaries;

use Illuminate\Support\Facades\Gate;
use Laravel\Scout\Builder as ScoutBuilder;
use Laravel\Scout\ScoutServiceProvider;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Exceptions\UnsearchableBoundaryException;
use Lomkit\Access\Perimeters\Levels;
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
class BoundarySearchTest extends PackageTestCase
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
        Gate::define('view global articles', fn (User $user): bool => true);
    }

    #[Test]
    public function it_refuses_to_search_through_a_control_that_declares_a_boundary(): void
    {
        $control = ConfiguredArticleControl::bindWithin(
            Boundary::where('author_id', fn (User $user): int => $user->getKey()),
            Levels::of(
                GlobalPerimeter::new()
                    ->allowed(fn (User $user): bool => true)
                    ->unrestricted(),
            ),
        );
        $this->actingAs($this->ada);

        $this->expectException(UnsearchableBoundaryException::class);
        $this->expectExceptionMessage(sprintf('The [%s] control declares a boundary, and a Scout search cannot apply it', $control::class));

        Article::search('Scout')->controlled()->get();
    }

    #[Test]
    public function it_searches_the_reached_level_and_the_perimeters_outside_the_group(): void
    {
        ConfiguredArticleControl::bind(
            Levels::of(
                GlobalPerimeter::new()
                    ->allowed(fn (User $user): bool => false)
                    ->unrestricted(),
                AuthorPerimeter::new()
                    ->allowed(fn (User $user): bool => true)
                    ->scoutQuery(fn (ScoutBuilder $query, User $user): ScoutBuilder => $query->where('author_id', $user->getKey())),
            ),
            FeaturedPerimeter::new()
                ->allowed(fn (User $user): bool => false)
                ->scoutQuery(fn (ScoutBuilder $query): ScoutBuilder => $query->where('featured', true)),
        );
        $this->actingAs($this->ada);

        $this->assertSame(['Scout primer'], Article::search('Scout')->controlled()->get()->pluck('title')->all());
    }

    #[Test]
    public function it_searches_every_article_through_an_unrestricted_level(): void
    {
        $this->actingAs($this->ada);

        $this->assertCount(3, Article::search('Scout')->controlled()->get());
    }
}
