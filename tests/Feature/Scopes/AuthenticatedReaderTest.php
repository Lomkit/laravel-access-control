<?php

namespace Lomkit\Access\Tests\Feature\Scopes;

use Illuminate\Auth\Events\Authenticated;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('auth.providers.users.model', User::class)]
class AuthenticatedReaderTest extends PackageTestCase
{
    protected User $ada;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Blog];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $acme = Client::factory()->createOne(['name' => 'Acme']);
        $globex = Client::factory()->createOne(['name' => 'Globex']);
        $this->ada = User::factory()->for($acme)->createOne(['name' => 'Ada', 'email' => 'ada@example.com']);
        User::factory()->for($acme)->createOne(['name' => 'Grace', 'email' => 'grace@example.com']);
        User::factory()->for($globex)->createOne(['name' => 'Hank', 'email' => 'hank@example.com']);

        Auth::forgetGuards();

        $this->app->make('session.store')->put(Auth::guard('web')->getName(), $this->ada->getKey());
    }

    #[Test]
    public function it_retrieves_a_controlled_user_through_the_guard(): void
    {
        $this->assertTrue($this->ada->is(Auth::user()));
        $this->assertSame(3, User::query()->count());
    }

    #[Test]
    public function it_controls_the_users_for_the_user_it_resolves_from_the_session(): void
    {
        Gate::define('view own users', fn (User $user): bool => true);

        $this->assertSame([$this->ada->getKey()], User::controlled()->pluck('id')->all());
        $this->assertTrue($this->ada->is(Auth::user()));
    }

    #[Test]
    public function it_controls_the_users_for_the_client_of_the_user_it_resolves_from_the_session(): void
    {
        Gate::define('view client users', fn (User $user): bool => true);

        $this->assertSame(['Ada', 'Grace'], User::controlled()->orderBy('name')->pluck('name')->all());
    }

    #[Test]
    public function it_controls_the_queries_run_while_the_guard_authenticates_the_user(): void
    {
        Gate::define('view own users', fn (User $user): bool => true);

        $visibleIds = [];

        Event::listen(Authenticated::class, function () use (&$visibleIds): void {
            $visibleIds[] = User::controlled()->pluck('id')->all();
        });

        Auth::user();

        $this->assertSame([[$this->ada->getKey()]], $visibleIds);
    }
}
