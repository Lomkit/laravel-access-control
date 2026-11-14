<?php

namespace Lomkit\Access\Tests\Feature\Scopes;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Laravel\Scout\ScoutServiceProvider;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\Search;
use Lomkit\Access\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
#[WithConfig('auth.providers.users.model', User::class)]
class ScoutControlledTest extends PackageTestCase
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
        Article::factory()->createOne(['author_id' => $this->ada->getKey(), 'title' => 'Scout recipes']);
        Article::factory()->createOne(['author_id' => $grace->getKey(), 'title' => 'Scout internals']);
        Gate::define('view own articles', fn (User $user): bool => true);
    }

    #[Test]
    public function it_scopes_the_search_to_the_signed_in_user(): void
    {
        $this->actingAs($this->ada);

        $this->assertSame(['Scout primer', 'Scout recipes'], Article::search('Scout')->controlled()->get()->pluck('title')->sort()->values()->all());
    }

    #[Test]
    public function it_scopes_the_search_to_the_user_it_resolves_from_the_session(): void
    {
        Auth::forgetGuards();

        $this->app->make('session.store')->put(Auth::guard('web')->getName(), $this->ada->getKey());

        $this->assertSame(['Scout primer', 'Scout recipes'], Article::search('Scout')->controlled()->get()->pluck('title')->sort()->values()->all());
        $this->assertTrue($this->ada->is(Auth::user()));
    }

    #[Test]
    public function it_returns_nothing_without_a_signed_in_user(): void
    {
        $search = Article::search('Scout')->controlled();

        $this->assertCount(0, $search->get());
        $this->assertSame(0, $search->paginate(10)->total());
    }
}
