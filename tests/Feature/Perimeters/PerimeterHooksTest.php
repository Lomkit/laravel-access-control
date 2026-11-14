<?php

namespace Lomkit\Access\Tests\Feature\Perimeters;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Ability;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Exceptions\MissingPerimeterCallbackException;
use Lomkit\Access\Exceptions\OverriddenPerimeterHookException;
use Lomkit\Access\Exceptions\PerimeterCallbackResultException;
use Lomkit\Access\Exceptions\UnrestrictedPerimeterCallbackException;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Tests\Fixtures\Blog\AllowingPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\EditorialPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\RecordingPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use Lomkit\Access\Tests\Fixtures\HookRecorder;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class PerimeterHooksTest extends PackageTestCase
{
    protected User $ada;

    protected Post $releaseNotes;

    protected Post $draft;

    protected Post $roadmap;

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
        $grace = User::factory()->for($acme)->createOne(['name' => 'Grace', 'email' => 'grace@example.com']);
        $hank = User::factory()->for($globex)->createOne(['name' => 'Hank', 'email' => 'hank@example.com']);
        $website = Project::factory()->for($acme)->createOne(['name' => 'Website']);
        $warehouse = Project::factory()->for($globex)->createOne(['name' => 'Warehouse']);
        $this->releaseNotes = Post::factory()->for($website)->for($grace, 'author')->createOne(['title' => 'Release notes']);
        $this->draft = Post::factory()->for($website)->for($this->ada, 'author')->createOne(['title' => 'Draft']);
        $this->roadmap = Post::factory()->for($warehouse)->for($hank, 'author')->createOne(['title' => 'Roadmap']);
    }

    /** @param  list<string>  $abilities */
    protected function allow(array $abilities): void
    {
        foreach ($abilities as $ability) {
            Gate::define($ability, fn (User $user): bool => true);
        }
    }

    /** @return list<string> */
    protected function readTitles(): array
    {
        return Post::controlledFor($this->ada)->orderBy('title')->pluck('title')->all();
    }

    #[Test]
    public function it_reads_the_client_posts_and_updates_only_its_own_through_the_hooks_of_a_class(): void
    {
        ConfiguredPostControl::bind(EditorialPerimeter::new());
        $this->allow(['view editorial posts', 'update editorial posts']);

        $this->assertTrue($this->ada->can('view', $this->releaseNotes));
        $this->assertFalse($this->ada->can('update', $this->releaseNotes));
        $this->assertTrue($this->ada->can('update', $this->draft));
        $this->assertFalse($this->ada->can('view', $this->roadmap));
        $this->assertSame(['Draft', 'Release notes'], $this->readTitles());
    }

    #[Test]
    public function it_asks_the_allows_hook_with_the_ability_of_the_decision(): void
    {
        ConfiguredPostControl::bind(EditorialPerimeter::new());
        $this->allow(['view editorial posts']);

        $this->assertTrue($this->ada->can('view', $this->draft));
        $this->assertFalse($this->ada->can('update', $this->draft));
    }

    #[Test]
    public function it_passes_the_reader_and_the_ability_to_each_hook_in_one_context(): void
    {
        $this->app->instance(HookRecorder::class, $recorder = new HookRecorder);
        ConfiguredPostControl::bind(RecordingPerimeter::new());

        $this->assertTrue($this->ada->can('update', $this->draft));
        $this->readTitles();

        $this->assertSame(['allows', 'includes', 'allows', 'scope'], array_column($recorder->calls, 0));
        $this->assertContainsOnlyInstancesOf(AccessContext::class, array_column($recorder->calls, 1));
        $this->assertSame([$this->ada, $this->ada, $this->ada, $this->ada], array_map(
            fn (AccessContext $context): User => $context->reader(),
            array_column($recorder->calls, 1),
        ));
        $this->assertSame(['update', 'update', 'view', 'view'], array_map(
            fn (AccessContext $context): string => $context->ability()->name(),
            array_column($recorder->calls, 1),
        ));
    }

    #[Test]
    public function it_passes_the_ability_to_a_should_closure(): void
    {
        ConfiguredPostControl::bind(
            ClientPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->should(fn (User $user, Post $post, Ability $ability): bool => $ability->is('view')
                    ? $post->project->client()->is($user->client)
                    : $post->author()->is($user))
                ->query(function (Builder $query, User $user): void {
                    $query->whereHas('project', fn (Builder $projects) => $projects->whereBelongsTo($user->client));
                }),
        );

        $this->assertTrue($this->ada->can('view', $this->releaseNotes));
        $this->assertFalse($this->ada->can('update', $this->releaseNotes));
        $this->assertTrue($this->ada->can('update', $this->draft));
        $this->assertFalse($this->ada->can('view', $this->roadmap));
    }

    #[Test]
    public function it_passes_the_ability_to_a_query_closure(): void
    {
        $received = [];
        ConfiguredPostControl::bind(
            ClientPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->query(function (Builder $query, User $user, Ability $ability) use (&$received): void {
                    $received[] = $ability;
                    $query->whereBelongsTo($user, 'author');
                }),
        );

        $this->assertSame(['Draft'], $this->readTitles());
        $this->assertCount(1, $received);
        $this->assertTrue($received[0]->is(Ability::view()));
    }

    /** @return array<string, array{Closure(Perimeter): Perimeter, string, string}> */
    public static function callbacksOfOverriddenHooks(): array
    {
        return [
            'allowed' => [fn (Perimeter $perimeter): Perimeter => $perimeter->allowed(fn (User $user, Ability $ability): bool => true), 'allows', 'allowed'],
            'should' => [fn (Perimeter $perimeter): Perimeter => $perimeter->should(fn (User $user, Post $post): bool => true), 'includes', 'should'],
            'query' => [fn (Perimeter $perimeter): Perimeter => $perimeter->query(fn (Builder $query) => null), 'scope', 'query'],
        ];
    }

    /** @param  Closure(Perimeter): Perimeter  $declare */
    #[Test]
    #[DataProvider('callbacksOfOverriddenHooks')]
    public function it_refuses_a_callback_for_a_hook_the_class_overrides(Closure $declare, string $hook, string $callback): void
    {
        $this->expectException(OverriddenPerimeterHookException::class);
        $this->expectExceptionMessage(sprintf(
            'The [%s] perimeter overrides %s(), so its [%s] callback would never decide anything.',
            EditorialPerimeter::class,
            $hook,
            $callback,
        ));

        $declare(EditorialPerimeter::new());
    }

    #[Test]
    public function it_accepts_the_callbacks_of_the_hooks_a_class_does_not_override(): void
    {
        ConfiguredPostControl::bind(
            AllowingPerimeter::new()
                ->should(fn (User $user, Post $post): bool => $post->author()->is($user))
                ->query(function (Builder $query, User $user): void {
                    $query->whereBelongsTo($user, 'author');
                }),
        );

        $this->assertTrue($this->ada->can('update', $this->draft));
        $this->assertFalse($this->ada->can('update', $this->releaseNotes));
        $this->assertSame(['Draft'], $this->readTitles());

        $this->expectException(OverriddenPerimeterHookException::class);

        AllowingPerimeter::new()->allowed(fn (User $user, Ability $ability): bool => true);
    }

    #[Test]
    public function it_refuses_to_make_unrestricted_a_class_that_overrides_a_row_hook(): void
    {
        $this->expectException(UnrestrictedPerimeterCallbackException::class);
        $this->expectExceptionMessage(sprintf(
            'The [%s] perimeter is unrestricted and overrides includes().',
            EditorialPerimeter::class,
        ));

        EditorialPerimeter::new()->unrestricted();
    }

    #[Test]
    public function it_makes_unrestricted_a_class_that_only_overrides_allows(): void
    {
        ConfiguredPostControl::bind(AllowingPerimeter::new()->unrestricted());

        $this->assertTrue($this->ada->can('update', $this->roadmap));
        $this->assertSame(['Draft', 'Release notes', 'Roadmap'], $this->readTitles());
    }

    #[Test]
    public function it_names_the_hook_to_override_when_a_callback_is_missing(): void
    {
        ConfiguredPostControl::bind(AllowingPerimeter::new());

        $this->expectException(MissingPerimeterCallbackException::class);
        $this->expectExceptionMessage(sprintf(
            'The [%s] perimeter has no [should] callback. Declare it with ->should(...), or override includes() in the perimeter',
            AllowingPerimeter::class,
        ));

        $this->ada->can('update', $this->draft);
    }

    /** @return array<string, array{Closure(Perimeter): Perimeter, string, string}> */
    public static function callbacksThatDoNotDecide(): array
    {
        return [
            'allowed returning a string' => [fn (Perimeter $perimeter): Perimeter => $perimeter->allowed(fn (User $user) => 'yes'), 'allowed', 'string'],
            'allowed returning an integer' => [fn (Perimeter $perimeter): Perimeter => $perimeter->allowed(fn (User $user) => 1), 'allowed', 'int'],
            'should returning nothing' => [fn (Perimeter $perimeter): Perimeter => $perimeter->allowed(fn (User $user): bool => true)->should(function (User $user, Post $post): void {}), 'should', 'null'],
        ];
    }

    /** @param  Closure(Perimeter): Perimeter  $declare */
    #[Test]
    #[DataProvider('callbacksThatDoNotDecide')]
    public function it_refuses_a_decision_that_is_not_a_boolean(Closure $declare, string $callback, string $type): void
    {
        ConfiguredPostControl::bind($declare(ClientPerimeter::new()));

        $this->expectException(PerimeterCallbackResultException::class);
        $this->expectExceptionMessage(sprintf(
            'The [%s] callback of the [%s] perimeter returned [%s] instead of a boolean.',
            $callback,
            ClientPerimeter::class,
            $type,
        ));

        $this->ada->can('update', $this->draft);
    }
}
