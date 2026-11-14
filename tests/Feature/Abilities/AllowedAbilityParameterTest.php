<?php

namespace Lomkit\Access\Tests\Feature\Abilities;

use Closure;
use Lomkit\Access\Ability;
use Lomkit\Access\Exceptions\AbilityParameterTypeException;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\MixedHookPerimeter;
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

class AllowedAbilityParameterTest extends PackageTestCase
{
    protected User $ada;

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
        $warehouse = Project::factory()->for($globex)->createOne(['name' => 'Warehouse']);
        $this->roadmap = Post::factory()->for($warehouse)->for($hank, 'author')->createOne(['title' => 'Roadmap']);
    }

    protected function bindGlobal(Closure $allowed): void
    {
        ConfiguredPostControl::bind(GlobalPerimeter::new()->allowed($allowed)->unrestricted());
    }

    /** @return array<string, array{Closure, string}> */
    public static function closuresThatCannotReceiveTheAbility(): array
    {
        return [
            'untyped' => [fn (User $user, $method): bool => $method === 'update', '$method'],
            'typed string' => [fn (User $user, string $method): bool => $method === 'update', 'string $method'],
            'typed nullable string' => [fn (User $user, ?string $method): bool => $method === 'update', '?string $method'],
            'typed int' => [fn (User $user, int $level): bool => $level > 1, 'int $level'],
            'variadic' => [fn (User $user, ...$arguments): bool => $arguments === ['update'], '$arguments'],
        ];
    }

    #[Test]
    #[DataProvider('closuresThatCannotReceiveTheAbility')]
    public function it_refuses_at_declaration_a_closure_whose_second_parameter_cannot_receive_the_ability(Closure $allowed, string $parameter): void
    {
        $this->expectException(AbilityParameterTypeException::class);
        $this->expectExceptionMessage(sprintf(
            'The [allowed] callback of the [%s] perimeter declares its second parameter as [%s], which cannot receive the ability. Type the second parameter Ability $ability, and compare it with $ability->is(\'update\').',
            GlobalPerimeter::class,
            $parameter,
        ));

        GlobalPerimeter::new()->allowed($allowed);
    }

    /** @return array<string, array{Closure}> */
    public static function closuresThatReceiveTheAbility(): array
    {
        return [
            'typed Ability' => [fn (User $user, Ability $ability): bool => $ability->is('update')],
            'typed nullable Ability' => [fn (User $user, ?Ability $ability): bool => $ability?->is('update') === true],
            'typed Ability or string' => [fn (User $user, Ability|string $ability): bool => $ability instanceof Ability && $ability->is('update')],
            'typed Stringable' => [fn (User $user, Stringable $ability): bool => $ability instanceof Ability && $ability->is('update')],
            'typed object' => [fn (User $user, object $ability): bool => $ability instanceof Ability && $ability->is('update')],
            'typed mixed' => [fn (User $user, mixed $ability): bool => $ability instanceof Ability && $ability->is('update')],
        ];
    }

    #[Test]
    #[DataProvider('closuresThatReceiveTheAbility')]
    public function it_passes_the_ability_object_to_a_parameter_that_receives_it(Closure $allowed): void
    {
        $this->bindGlobal($allowed);

        $this->assertTrue($this->ada->can('update', $this->roadmap));
        $this->assertFalse($this->ada->can('view', $this->roadmap));
        $this->assertSame(0, Post::controlledFor($this->ada)->count());
    }

    #[Test]
    public function it_compares_the_ability_with_a_name(): void
    {
        $received = [];
        $this->bindGlobal(function (User $user, Ability $ability) use (&$received): bool {
            $received[] = $ability;

            return $ability->is('view') || $ability->is('approve');
        });

        $this->assertTrue($this->ada->can('view', $this->roadmap));
        $this->assertTrue($this->ada->can('viewAny', Post::class));
        $this->assertTrue($this->ada->can('approve', $this->roadmap));
        $this->assertFalse($this->ada->can('update', $this->roadmap));
        $this->assertSame(1, Post::controlledFor($this->ada)->count());
        $this->assertContainsOnlyInstancesOf(Ability::class, $received);
    }

    #[Test]
    public function it_calls_a_closure_that_only_takes_the_reader(): void
    {
        $this->bindGlobal(fn (User $user): bool => $user->is($this->ada));

        $this->assertTrue($this->ada->can('update', $this->roadmap));
        $this->assertSame(1, Post::controlledFor($this->ada)->count());
    }

    #[Test]
    public function it_passes_the_ability_object_to_a_hook_typed_with_ability(): void
    {
        ConfiguredPostControl::bind(TypedHookPerimeter::new()->unrestricted());

        $this->assertTrue($this->ada->can('view', $this->roadmap));
        $this->assertFalse($this->ada->can('update', $this->roadmap));
    }

    #[Test]
    public function it_passes_the_ability_object_to_a_hook_typed_mixed(): void
    {
        ConfiguredPostControl::bind(MixedHookPerimeter::new()->unrestricted());

        $this->assertTrue($this->ada->can('view', $this->roadmap));
        $this->assertFalse($this->ada->can('update', $this->roadmap));
    }

    #[Test]
    public function it_refuses_a_hook_that_does_not_type_its_ability(): void
    {
        ConfiguredPostControl::bind(UntypedHookPerimeter::new()->unrestricted());

        $this->expectException(AbilityParameterTypeException::class);
        $this->expectExceptionMessage(sprintf(
            'The [%s] perimeter overrides applyAllowedCallback() with its second parameter declared as [$ability], which cannot receive the ability. Type the second parameter Ability $ability',
            UntypedHookPerimeter::class,
        ));

        $this->ada->can('delete', $this->roadmap);
    }

    #[Test]
    public function it_refuses_a_hook_that_does_not_type_its_ability_in_a_controlled_query(): void
    {
        ConfiguredPostControl::bind(UntypedHookPerimeter::new()->unrestricted());

        $this->expectException(AbilityParameterTypeException::class);

        Post::controlledFor($this->ada)->get();
    }
}
