<?php

namespace Lomkit\Access\Tests\Feature\Abilities;

use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Ability;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class PolicyAbilityTest extends PackageTestCase
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

    /**
     * @param  list<Ability>  $received
     */
    protected function recordAbilities(array &$received): void
    {
        ConfiguredPostControl::bind(
            GlobalPerimeter::new()
                ->allowed(function (User $user, Ability $ability) use (&$received): bool {
                    $received[] = $ability;

                    return true;
                })
                ->unrestricted(),
        );
    }

    #[Test]
    public function it_passes_the_ability_of_each_policy_method_to_the_allowed_callback(): void
    {
        $received = [];
        $this->recordAbilities($received);

        $this->assertTrue($this->ada->can('viewAny', Post::class));
        $this->assertTrue($this->ada->can('view', $this->releaseNotes));
        $this->assertTrue($this->ada->can('create', Post::class));
        $this->assertTrue($this->ada->can('update', $this->releaseNotes));
        $this->assertTrue($this->ada->can('delete', $this->releaseNotes));
        $this->assertTrue($this->ada->can('restore', $this->releaseNotes));
        $this->assertTrue($this->ada->can('forceDelete', $this->releaseNotes));

        $this->assertContainsOnlyInstancesOf(Ability::class, $received);
        $this->assertSame(
            ['view', 'view', 'create', 'update', 'delete', 'restore', 'forceDelete'],
            array_map(fn (Ability $ability): string => $ability->name(), $received),
        );
    }

    #[Test]
    public function it_passes_a_business_ability_to_the_allowed_callback(): void
    {
        $received = [];
        $this->recordAbilities($received);

        $this->assertTrue($this->ada->can('approve', $this->releaseNotes));

        $this->assertCount(1, $received);
        $this->assertInstanceOf(Ability::class, $received[0]);
        $this->assertTrue($received[0]->is(Ability::of('approve')));
    }

    #[Test]
    public function it_decides_a_business_ability_with_the_perimeters_of_the_control(): void
    {
        Gate::define('approve client posts', fn (User $user): bool => true);
        Gate::define('update client posts', fn (User $user): bool => true);

        $this->assertTrue($this->ada->can('approve', $this->releaseNotes));
        $this->assertFalse($this->ada->can('approve', $this->roadmap));
        $this->assertFalse($this->ada->can('view', $this->releaseNotes));
    }

    #[Test]
    public function it_passes_the_view_ability_to_the_allowed_callback_of_a_controlled_query(): void
    {
        $received = [];
        $this->recordAbilities($received);

        $this->assertSame(2, Post::controlledFor($this->ada)->count());

        $this->assertCount(1, $received);
        $this->assertInstanceOf(Ability::class, $received[0]);
        $this->assertTrue($received[0]->is(Ability::view()));
    }
}
