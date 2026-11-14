<?php

namespace Lomkit\Access\Tests\Feature\Abilities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Ability;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class StringClosureTest extends PackageTestCase
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

    #[Test]
    public function it_keeps_a_closure_typed_with_a_string_method_working(): void
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
    public function it_keeps_a_strict_comparison_on_a_string_method_working(): void
    {
        ConfiguredPostControl::bind(
            GlobalPerimeter::new()
                ->allowed(fn (User $user, string $method): bool => match ($method) {
                    'view', 'update' => true,
                    default => false,
                })
                ->unrestricted(),
        );

        $this->assertTrue($this->ada->can('view', $this->roadmap));
        $this->assertTrue($this->ada->can('update', $this->roadmap));
        $this->assertFalse($this->ada->can('delete', $this->roadmap));
        $this->assertFalse($this->ada->can('approve', $this->roadmap));
    }

    #[Test]
    public function it_passes_the_ability_object_to_an_untyped_parameter(): void
    {
        $received = [];
        ConfiguredPostControl::bind(
            GlobalPerimeter::new()
                ->allowed(function (User $user, $ability) use (&$received): bool {
                    $received[] = $ability;

                    return $ability === 'view';
                })
                ->unrestricted(),
        );

        $this->assertFalse($this->ada->can('view', $this->roadmap));
        $this->assertInstanceOf(Ability::class, $received[0]);
        $this->assertTrue($received[0]->is('view'));
    }
}
