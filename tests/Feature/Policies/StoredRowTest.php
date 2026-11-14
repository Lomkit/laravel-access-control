<?php

namespace Lomkit\Access\Tests\Feature\Policies;

use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Access;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Blog\WorkspaceBoundary;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class StoredRowTest extends PackageTestCase
{
    protected Client $acme;

    protected User $ada;

    protected User $hank;

    protected Post $releaseNotes;

    protected Post $roadmap;

    protected Post $elsewhere;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Blog];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->acme = Client::factory()->createOne(['name' => 'Acme']);
        $globex = Client::factory()->createOne(['name' => 'Globex']);
        $this->ada = User::factory()->for($this->acme)->createOne(['name' => 'Ada', 'email' => 'ada@example.com', 'current_workspace_id' => 1]);
        $this->hank = User::factory()->for($globex)->createOne(['name' => 'Hank', 'email' => 'hank@example.com', 'current_workspace_id' => 1]);
        $website = Project::factory()->for($this->acme)->createOne(['name' => 'Website']);
        $this->releaseNotes = Post::factory()->for($website)->for($this->ada, 'author')->createOne(['title' => 'Release notes', 'workspace_id' => 1]);
        $this->roadmap = Post::factory()->for($website)->for($this->hank, 'author')->createOne(['title' => 'Roadmap', 'workspace_id' => 1]);
        $this->elsewhere = Post::factory()->for($website)->for($this->ada, 'author')->createOne(['title' => 'Elsewhere', 'workspace_id' => 2]);
    }

    /** @param  list<string>  $permissions */
    protected function allow(array $permissions): void
    {
        foreach ($permissions as $permission) {
            Gate::define($permission, fn (User $user): bool => true);
        }
    }

    #[Test]
    public function it_judges_a_filled_row_on_the_values_it_has_in_the_database(): void
    {
        $this->allow(['update own posts']);

        $this->roadmap->fill(['author_id' => $this->ada->getKey()]);

        $this->assertFalse($this->ada->can('update', $this->roadmap));
        $this->assertSame($this->ada->getKey(), $this->roadmap->author_id);
        $this->assertTrue($this->roadmap->isDirty('author_id'));
    }

    #[Test]
    public function it_keeps_granting_a_row_filled_with_values_outside_the_perimeter(): void
    {
        $this->allow(['update own posts']);

        $this->releaseNotes->fill(['author_id' => $this->hank->getKey()]);

        $this->assertTrue($this->ada->can('update', $this->releaseNotes));
    }

    #[Test]
    public function it_checks_the_boundary_in_memory_on_the_stored_row(): void
    {
        $this->allow(['update own posts']);

        $this->elsewhere->fill(['workspace_id' => 1]);

        $this->assertFalse($this->ada->can('update', $this->elsewhere));
    }

    #[Test]
    public function it_checks_the_boundary_in_sql_on_the_stored_key(): void
    {
        ConfiguredPostControl::bindWithin(
            new WorkspaceBoundary,
            Levels::of(
                GlobalPerimeter::new()
                    ->allowed(fn (User $user): bool => true)
                    ->unrestricted(),
            ),
        );

        $this->elsewhere->fill(['id' => $this->releaseNotes->getKey(), 'workspace_id' => 1]);

        $this->assertFalse($this->ada->can('update', $this->elsewhere));
    }

    #[Test]
    public function it_hands_the_should_callback_a_copy_with_the_stored_values(): void
    {
        $received = [];
        ConfiguredPostControl::bind(
            OwnPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->should(function (User $user, Post $post) use (&$received): bool {
                    $received[] = $post;

                    return true;
                }),
        );

        $this->roadmap->fill(['title' => 'Changed']);
        $this->ada->can('update', $this->roadmap);

        $this->assertCount(1, $received);
        $this->assertNotSame($this->roadmap, $received[0]);
        $this->assertSame('Roadmap', $received[0]->title);
        $this->assertTrue($received[0]->exists);
        $this->assertFalse($received[0]->isDirty());
        $this->assertSame('Changed', $this->roadmap->title);
    }

    #[Test]
    public function it_hands_the_should_callback_the_row_itself_when_nothing_changed(): void
    {
        $received = [];
        ConfiguredPostControl::bind(
            OwnPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->should(function (User $user, Post $post) use (&$received): bool {
                    $received[] = $post;

                    return true;
                }),
        );

        $this->roadmap->load('project');
        $this->ada->can('update', $this->roadmap);

        $this->assertSame([$this->roadmap], $received);
        $this->assertTrue($received[0]->relationLoaded('project'));
    }

    #[Test]
    public function it_judges_a_filled_user_on_its_stored_client_through_an_automatic_policy(): void
    {
        Access::registerPolicies();
        Gate::define('update client users', fn (User $user): bool => true);
        $grace = User::factory()->for($this->acme)->createOne(['name' => 'Grace', 'email' => 'grace@example.com']);

        $this->hank->fill(['client_id' => $this->acme->getKey()]);
        $grace->fill(['client_id' => $this->hank->getRawOriginal('client_id')]);

        $this->assertFalse($this->ada->can('update', $this->hank));
        $this->assertTrue($this->ada->can('update', $grace));
    }
}
