<?php

namespace Lomkit\Access\Tests\Feature\Perimeters;

use Illuminate\Database\Eloquent\Builder;
use Laravel\Scout\Builder as ScoutBuilder;
use Laravel\Scout\ScoutServiceProvider;
use Lomkit\Access\Ability;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Exceptions\MissingPerimeterCallbackException;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredArticleControl;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\AuthorPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\Search;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class MissingScoutCallbackTest extends PackageTestCase
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

        ConfiguredArticleControl::bind(
            AuthorPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->query(function (Builder $query, User $user): void {
                    $query->where('author_id', $user->getKey());
                }),
        );
    }

    #[Test]
    public function it_throws_when_the_scout_query_callback_of_an_allowed_perimeter_is_missing(): void
    {
        $this->actingAs($this->ada);

        $this->expectException(MissingPerimeterCallbackException::class);
        $this->expectExceptionMessage(sprintf('The [%s] perimeter has no [scoutQuery] callback.', AuthorPerimeter::class));

        Article::search('Scout')->controlled();
    }

    #[Test]
    public function it_needs_no_scout_query_callback_for_an_eloquent_query(): void
    {
        Article::factory()->createOne(['author_id' => $this->ada->getKey(), 'title' => 'Scout primer']);
        Article::factory()->createOne(['title' => 'Scout internals']);

        $this->assertSame(['Scout primer'], Article::controlledFor($this->ada)->pluck('title')->all());
    }

    #[Test]
    public function it_throws_when_a_perimeter_without_callbacks_is_applied_to_a_search(): void
    {
        $this->expectException(MissingPerimeterCallbackException::class);
        $this->expectExceptionMessage(sprintf('The [%s] perimeter has no [scoutQuery] callback.', Perimeter::class));

        Perimeter::new()->applyScoutQueryCallback(new ScoutBuilder(new Article, 'Scout'), AccessContext::of($this->ada, Ability::view()));
    }
}
