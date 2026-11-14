<?php

namespace Lomkit\Access\Tests\Feature\Abilities;

use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\PostControl;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class AbilityDecisionTest extends PackageTestCase
{
    protected Control $control;

    protected User $ada;

    protected Post $releaseNotes;

    protected Post $roadmap;

    protected Post $inventory;

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
        $this->inventory = Post::factory()->for($warehouse)->for($hank, 'author')->createOne(['title' => 'Inventory']);
        $this->inventory->sharedWith()->attach($this->ada);
        $this->control = Access::control(PostControl::class);
    }

    /** @param  list<string>  $permissions */
    protected function allow(array $permissions): void
    {
        foreach ($permissions as $permission) {
            Gate::define($permission, fn (User $user): bool => true);
        }
    }

    #[Test]
    public function it_refuses_every_ability_when_no_perimeter_allows_the_reader(): void
    {
        $this->assertFalse($this->control->applies($this->ada, Ability::create(), new Post));
        $this->assertFalse($this->control->applies($this->ada, Ability::view(), $this->releaseNotes));
        $this->assertFalse($this->control->applies($this->ada, Ability::of('approve'), $this->releaseNotes));
    }

    #[Test]
    public function it_decides_each_ability_with_the_perimeter_that_allows_it(): void
    {
        $this->allow(['view client posts', 'update client posts']);

        $this->assertTrue($this->control->applies($this->ada, Ability::view(), $this->releaseNotes));
        $this->assertTrue($this->control->applies($this->ada, Ability::update(), $this->releaseNotes));
        $this->assertFalse($this->control->applies($this->ada, Ability::delete(), $this->releaseNotes));
        $this->assertFalse($this->control->applies($this->ada, Ability::view(), $this->roadmap));
    }

    #[Test]
    public function it_does_not_grant_an_ability_through_the_permission_of_another(): void
    {
        $this->allow(['update client posts', 'update global posts']);

        $this->assertFalse($this->ada->can('view', $this->releaseNotes));
        $this->assertFalse($this->ada->can('viewAny', Post::class));
        $this->assertTrue($this->ada->can('update', $this->roadmap));
    }

    #[Test]
    public function it_decides_view_any_and_create_without_a_row(): void
    {
        $this->allow(['view client posts', 'create client posts']);

        $this->assertTrue($this->ada->can('viewAny', Post::class));
        $this->assertTrue($this->ada->can('create', Post::class));
        $this->assertFalse($this->ada->can('update', $this->releaseNotes));
    }

    #[Test]
    public function it_decides_each_standard_ability_through_the_policy(): void
    {
        $this->allow(['view global posts', 'update client posts', 'delete client posts', 'restore client posts', 'forceDelete client posts']);

        $this->assertTrue($this->ada->can('view', $this->roadmap));
        $this->assertTrue($this->ada->can('update', $this->releaseNotes));
        $this->assertFalse($this->ada->can('update', $this->roadmap));
        $this->assertTrue($this->ada->can('delete', $this->releaseNotes));
        $this->assertTrue($this->ada->can('restore', $this->releaseNotes));
        $this->assertTrue($this->ada->can('forceDelete', $this->releaseNotes));
        $this->assertFalse($this->ada->can('forceDelete', $this->roadmap));
    }

    #[Test]
    public function it_adds_the_shared_perimeter_to_the_next_one_for_the_same_ability(): void
    {
        $this->allow(['delete shared posts', 'delete client posts']);

        $this->assertTrue($this->ada->can('delete', $this->inventory));
        $this->assertTrue($this->ada->can('delete', $this->releaseNotes));
        $this->assertFalse($this->ada->can('delete', $this->roadmap));
        $this->assertFalse($this->ada->can('update', $this->inventory));
    }
}
