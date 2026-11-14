<?php

namespace Lomkit\Access\Tests\Feature\Abilities;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Ability;
use Lomkit\Access\Exceptions\UntypedAbilityHookException;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\TypedHookPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\UntypedHookPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Stringable;

class AllowedArgumentTest extends PackageTestCase
{
    protected User $ada;

    protected Post $releaseNotes;

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
        $hank = User::factory()->for($globex)->createOne(['name' => 'Hank', 'email' => 'hank@example.com']);
        $website = Project::factory()->for($acme)->createOne(['name' => 'Website']);
        $warehouse = Project::factory()->for($globex)->createOne(['name' => 'Warehouse']);
        $this->releaseNotes = Post::factory()->for($website)->for($hank, 'author')->createOne(['title' => 'Release notes']);
        $this->roadmap = Post::factory()->for($warehouse)->for($hank, 'author')->createOne(['title' => 'Roadmap']);
    }

    protected function bindGlobal(Closure $allowed): void
    {
        ConfiguredPostControl::bind(GlobalPerimeter::new()->allowed($allowed)->unrestricted());
    }

    #[Test]
    public function it_denies_the_abilities_an_untyped_match_lists_before_its_default_arm(): void
    {
        $this->bindGlobal(fn (User $user, $method): bool => match ($method) {
            'view', 'update', 'approve' => false,
            default => true,
        });

        $this->assertFalse($this->ada->can('view', $this->roadmap));
        $this->assertFalse($this->ada->can('viewAny', Post::class));
        $this->assertFalse($this->ada->can('update', $this->roadmap));
        $this->assertFalse($this->ada->can('approve', $this->roadmap));
        $this->assertTrue($this->ada->can('delete', $this->roadmap));
        $this->assertSame(0, Post::controlledFor($this->ada)->count());
    }

    #[Test]
    public function it_compares_an_untyped_parameter_with_a_string(): void
    {
        $this->bindGlobal(fn (User $user, $method): bool => $method === 'view' || in_array($method, ['approve'], true));

        $this->assertTrue($this->ada->can('view', $this->roadmap));
        $this->assertTrue($this->ada->can('viewAny', Post::class));
        $this->assertTrue($this->ada->can('approve', $this->roadmap));
        $this->assertFalse($this->ada->can('update', $this->roadmap));
        $this->assertSame(2, Post::controlledFor($this->ada)->count());
    }

    /** @return array<string, array{Closure}> */
    public static function closuresReceivingTheName(): array
    {
        return [
            'untyped' => [fn (User $user, $method): bool => is_string($method) && $method === 'update'],
            'typed string' => [fn (User $user, string $method): bool => $method === 'update'],
            'typed nullable string' => [fn (User $user, ?string $method): bool => $method === 'update'],
            'typed mixed' => [fn (User $user, mixed $method): bool => $method === 'update'],
            'variadic' => [fn (User $user, ...$arguments): bool => $arguments === ['update']],
        ];
    }

    #[Test]
    #[DataProvider('closuresReceivingTheName')]
    public function it_passes_the_name_to_a_parameter_that_does_not_take_an_ability(Closure $allowed): void
    {
        $this->bindGlobal($allowed);

        $this->assertTrue($this->ada->can('update', $this->roadmap));
        $this->assertFalse($this->ada->can('view', $this->roadmap));
    }

    /** @return array<string, array{Closure}> */
    public static function closuresReceivingTheAbility(): array
    {
        return [
            'typed Ability' => [fn (User $user, Ability $ability): bool => $ability->is('update')],
            'typed nullable Ability' => [fn (User $user, ?Ability $ability): bool => $ability?->is('update') === true],
            'typed Ability or string' => [fn (User $user, Ability|string $ability): bool => $ability instanceof Ability && $ability->is('update')],
            'typed Stringable' => [fn (User $user, Stringable $ability): bool => $ability instanceof Ability && $ability->is('update')],
            'typed object' => [fn (User $user, object $ability): bool => $ability instanceof Ability && $ability->is('update')],
        ];
    }

    #[Test]
    #[DataProvider('closuresReceivingTheAbility')]
    public function it_passes_the_ability_to_a_parameter_that_takes_an_ability(Closure $allowed): void
    {
        $this->bindGlobal($allowed);

        $this->assertTrue($this->ada->can('update', $this->roadmap));
        $this->assertFalse($this->ada->can('view', $this->roadmap));
    }

    #[Test]
    public function it_passes_the_ability_object_to_a_closure_typed_with_ability(): void
    {
        $received = [];
        $this->bindGlobal(function (User $user, Ability $ability) use (&$received): bool {
            $received[] = $ability;

            return $ability->is('update');
        });

        $this->assertTrue($this->ada->can('update', $this->roadmap));
        $this->assertFalse($this->ada->can('view', $this->roadmap));
        $this->assertContainsOnlyInstancesOf(Ability::class, $received);
        $this->assertTrue($received[0]->is(Ability::update()));
    }

    #[Test]
    public function it_passes_the_name_to_a_closure_typed_with_string(): void
    {
        $received = [];
        ConfiguredPostControl::bind(
            ClientPerimeter::new()
                ->allowed(function (User $user, string $method) use (&$received): bool {
                    $received[] = $method;

                    return $user->can("{$method} client posts");
                })
                ->should(fn (User $user, Post $post): bool => $post->project->client()->is($user->client))
                ->query(function (Builder $query, User $user): void {
                    $query->whereHas('project', fn (Builder $projects) => $projects->whereBelongsTo($user->client));
                }),
        );
        Gate::define('view client posts', fn (User $user): bool => true);
        Gate::define('approve client posts', fn (User $user): bool => true);

        $this->assertTrue($this->ada->can('viewAny', Post::class));
        $this->assertTrue($this->ada->can('view', $this->releaseNotes));
        $this->assertFalse($this->ada->can('view', $this->roadmap));
        $this->assertFalse($this->ada->can('update', $this->releaseNotes));
        $this->assertTrue($this->ada->can('approve', $this->releaseNotes));
        $this->assertSame(['Release notes'], Post::controlledFor($this->ada)->pluck('title')->all());
        $this->assertSame(['view', 'view', 'view', 'update', 'approve', 'view'], $received);
    }

    #[Test]
    public function it_calls_a_closure_that_only_takes_the_reader(): void
    {
        $this->bindGlobal(fn (User $user): bool => $user->is($this->ada));

        $this->assertTrue($this->ada->can('update', $this->roadmap));
        $this->assertSame(2, Post::controlledFor($this->ada)->count());
    }

    #[Test]
    public function it_passes_the_ability_object_to_a_hook_typed_with_ability(): void
    {
        ConfiguredPostControl::bind(TypedHookPerimeter::new()->unrestricted());

        $this->assertTrue($this->ada->can('view', $this->roadmap));
        $this->assertFalse($this->ada->can('update', $this->roadmap));
    }

    #[Test]
    public function it_refuses_a_hook_that_does_not_type_its_ability(): void
    {
        ConfiguredPostControl::bind(UntypedHookPerimeter::new()->unrestricted());

        $this->expectException(UntypedAbilityHookException::class);
        $this->expectExceptionMessage(sprintf(
            'The [%s] perimeter overrides applyAllowedCallback() without typing its second parameter Ability',
            UntypedHookPerimeter::class,
        ));

        $this->ada->can('delete', $this->roadmap);
    }

    #[Test]
    public function it_refuses_a_hook_that_does_not_type_its_ability_in_a_controlled_query(): void
    {
        ConfiguredPostControl::bind(UntypedHookPerimeter::new()->unrestricted());

        $this->expectException(UntypedAbilityHookException::class);

        Post::controlledFor($this->ada)->get();
    }
}
