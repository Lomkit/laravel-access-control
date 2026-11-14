<?php

namespace Lomkit\Access\Tests\Feature\Scopes;

use Illuminate\Support\Facades\Auth;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\SearchTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
#[WithConfig('auth.providers.users.model', User::class)]
class ScoutControlledTest extends SearchTestCase
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
        Article::factory()->createOne(['author_id' => $grace->getKey(), 'title' => 'Scout internals']);
        $this->allow(['view own articles']);
    }

    #[Test]
    public function it_scopes_the_search_to_the_signed_in_user(): void
    {
        $this->actingAs($this->ada);

        $this->assertSame(['Scout primer', 'Scout recipes'], $this->searchedTitles());
    }

    #[Test]
    public function it_scopes_the_search_to_the_user_it_resolves_from_the_session(): void
    {
        Auth::forgetGuards();

        $this->app->make('session.store')->put(Auth::guard('web')->getName(), $this->ada->getKey());

        $this->assertSame(['Scout primer', 'Scout recipes'], $this->searchedTitles());
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
