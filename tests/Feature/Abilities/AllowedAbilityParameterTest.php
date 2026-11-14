<?php

namespace Lomkit\Access\Tests\Feature\Abilities;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Ability;
use Lomkit\Access\Exceptions\AbilityParameterTypeException;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Stringable;

class AllowedAbilityParameterTest extends PackageTestCase
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

    /** @return array<string, array{Closure}> */
    public static function closuresReceivingTheAbility(): array
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
    #[DataProvider('closuresReceivingTheAbility')]
    public function it_passes_the_ability_to_a_parameter_that_takes_an_ability(Closure $allowed): void
    {
        $this->bindGlobal($allowed);

        $this->assertTrue($this->ada->can('update', $this->roadmap));
        $this->assertFalse($this->ada->can('view', $this->roadmap));
    }

    #[Test]
    public function it_passes_the_mapped_ability_of_each_policy_method(): void
    {
        $received = [];
        ConfiguredPostControl::bind(
            ClientPerimeter::new()
                ->allowed(function (User $user, Ability $ability) use (&$received): bool {
                    $received[] = $ability->name();

                    return $user->can("{$ability} client posts");
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

    /** @return array<string, array{string, Closure(Perimeter): Perimeter}> */
    public static function abilityParametersOfAnotherType(): array
    {
        return [
            'untyped allowed' => ['allowed', fn (Perimeter $perimeter): Perimeter => $perimeter->allowed(fn (User $user, $method): bool => $method !== 'delete')],
            'string allowed' => ['allowed', fn (Perimeter $perimeter): Perimeter => $perimeter->allowed(fn (User $user, string $method): bool => $method === 'view')],
            'nullable string allowed' => ['allowed', fn (Perimeter $perimeter): Perimeter => $perimeter->allowed(fn (User $user, ?string $method): bool => $method === 'view')],
            'variadic allowed' => ['allowed', fn (Perimeter $perimeter): Perimeter => $perimeter->allowed(fn (User $user, ...$arguments): bool => true)],
            'untyped should' => ['should', fn (Perimeter $perimeter): Perimeter => $perimeter->should(fn (User $user, Post $post, $method): bool => $method === 'view')],
            'string query' => ['query', fn (Perimeter $perimeter): Perimeter => $perimeter->query(fn (Builder $query, User $user, string $method) => $query->whereRaw('1=1'))],
            'untyped scoutQuery' => ['scoutQuery', fn (Perimeter $perimeter): Perimeter => $perimeter->scoutQuery(fn ($query, User $user, $method) => null)],
        ];
    }

    /** @param  Closure(Perimeter): Perimeter  $declare */
    #[Test]
    #[DataProvider('abilityParametersOfAnotherType')]
    public function it_refuses_at_declaration_an_ability_parameter_that_does_not_take_an_ability(string $callback, Closure $declare): void
    {
        $this->expectException(AbilityParameterTypeException::class);
        $this->expectExceptionMessage(sprintf(
            'The [%s] callback of the [%s] perimeter declares its ability parameter as',
            $callback,
            ClientPerimeter::class,
        ));

        $declare(ClientPerimeter::new());
    }
}
