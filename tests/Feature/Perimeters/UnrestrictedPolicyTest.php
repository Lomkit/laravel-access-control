<?php

namespace Lomkit\Access\Tests\Feature\Perimeters;

use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Ability;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\SharedPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class UnrestrictedPolicyTest extends PackageTestCase
{
    protected User $ada;

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
        $warehouse = Project::factory()->for($globex)->createOne(['name' => 'Warehouse']);
        $this->roadmap = Post::factory()->for($warehouse)->for($hank, 'author')->createOne(['title' => 'Roadmap']);
        $this->inventory = Post::factory()->for($warehouse)->for($hank, 'author')->createOne(['title' => 'Inventory']);
        $this->inventory->sharedWith()->attach($this->ada);
    }

    /** @param  list<string>  $abilities */
    protected function allow(array $abilities): void
    {
        foreach ($abilities as $ability) {
            Gate::define($ability, fn (User $user): bool => true);
        }
    }

    #[Test]
    public function it_grants_every_post_through_an_unrestricted_perimeter(): void
    {
        $this->allow(['view global posts', 'update global posts', 'create global posts']);

        $this->assertTrue($this->ada->can('view', $this->roadmap));
        $this->assertTrue($this->ada->can('update', $this->roadmap));
        $this->assertTrue($this->ada->can('viewAny', Post::class));
        $this->assertTrue($this->ada->can('create', Post::class));
    }

    #[Test]
    public function it_grants_every_post_for_a_reader_with_the_global_and_the_shared_permissions(): void
    {
        $this->allow(['update global posts', 'update shared posts']);

        $this->assertTrue($this->ada->can('update', $this->roadmap));
        $this->assertTrue($this->ada->can('update', $this->inventory));
    }

    #[Test]
    public function it_grants_only_the_abilities_the_unrestricted_perimeter_allows(): void
    {
        $this->allow(['view global posts']);

        $this->assertTrue($this->ada->can('view', $this->roadmap));
        $this->assertFalse($this->ada->can('update', $this->roadmap));
        $this->assertFalse($this->ada->can('delete', $this->inventory));
    }

    #[Test]
    public function it_grants_the_row_without_calling_the_should_callbacks(): void
    {
        $checked = [];

        $control = ConfiguredPostControl::bind(
            SharedPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->should(function (User $user, Post $post) use (&$checked): bool {
                    $checked[] = $post->title;

                    return false;
                }),
            GlobalPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->unrestricted(),
        );

        $this->assertTrue($control->applies($this->ada, Ability::update(), $this->roadmap));
        $this->assertSame([], $checked);
    }

    #[Test]
    public function it_checks_the_row_when_the_unrestricted_perimeter_does_not_allow_the_reader(): void
    {
        $this->allow(['update shared posts', 'view global posts']);

        $this->assertTrue($this->ada->can('update', $this->inventory));
        $this->assertFalse($this->ada->can('update', $this->roadmap));
    }
}
