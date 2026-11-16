<?php

namespace Lomkit\Access\Tests\Feature\Policies;

use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Ability;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\RemappedPostControl;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class BusinessAbilityTest extends PackageTestCase
{
    protected User $ada;

    protected Post $releaseNotes;

    protected Post $roadmap;

    protected Post $archive;

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
        $this->archive = Post::factory()->for($website)->for($hank, 'author')->createOne(['title' => 'Archive', 'archived_at' => '2026-01-01 00:00:00']);
    }

    #[Test]
    public function it_decides_a_business_ability_on_a_row_through_the_helper(): void
    {
        Gate::define('approve client posts', fn (User $user): bool => true);

        $this->assertTrue($this->ada->can('approve', $this->releaseNotes));
        $this->assertSame('outside_rows', Gate::forUser($this->ada)->inspect('approve', $this->roadmap)->code());
        $this->assertSame('outside_boundary', Gate::forUser($this->ada)->inspect('approve', $this->archive)->code());
        $this->assertSame('unsaved', Gate::forUser($this->ada)->inspect('approve', new Post)->code());
    }

    #[Test]
    public function it_decides_a_business_ability_named_decide(): void
    {
        Gate::define('decide client posts', fn (User $user): bool => true);

        $this->assertTrue($this->ada->can('decide', $this->releaseNotes));
        $this->assertSame('outside_rows', Gate::forUser($this->ada)->inspect('decide', $this->roadmap)->code());
    }

    #[Test]
    public function it_never_lets_the_gate_call_the_helpers_of_the_policy(): void
    {
        Gate::define('decideByControl client posts', fn (User $user): bool => true);
        Gate::define('declaredControl client posts', fn (User $user): bool => true);

        $this->assertTrue(Gate::forUser($this->ada)->denies('decideByControl', $this->releaseNotes));
        $this->assertTrue(Gate::forUser($this->ada)->denies('declaredControl', $this->releaseNotes));
    }

    #[Test]
    public function it_decides_a_business_ability_without_a_row_through_the_helper(): void
    {
        $this->assertSame('no_perimeter', Gate::forUser($this->ada)->inspect('export', Post::class)->code());

        Gate::define('export own posts', fn (User $user): bool => true);

        $this->assertTrue($this->ada->can('export', Post::class));
    }

    #[Test]
    public function it_decides_a_business_ability_on_a_draft_through_the_helper(): void
    {
        Gate::define('duplicate client posts', fn (User $user): bool => true);
        $inClient = $this->releaseNotes->replicate();
        $elsewhere = $this->roadmap->replicate();

        $this->assertTrue($this->ada->can('duplicate', [$this->releaseNotes, $inClient]));
        $this->assertFalse($this->ada->can('duplicate', [$this->releaseNotes, $elsewhere]));
    }

    #[Test]
    public function it_maps_the_business_ability_through_the_control(): void
    {
        $received = [];
        RemappedPostControl::bind(
            OwnPerimeter::new()
                ->allowed(function (User $user, Ability $ability) use (&$received): bool {
                    $received[] = $ability->name();

                    return true;
                })
                ->should(fn (User $user, Post $post): bool => true),
        );

        $this->assertTrue($this->ada->can('approve', $this->releaseNotes));
        $this->assertSame(['update'], $received);
    }
}
