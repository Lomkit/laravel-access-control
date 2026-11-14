<?php

namespace Lomkit\Access\Tests\Feature\Boundaries;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\InMemoryWorkspaceBoundary;
use Lomkit\Access\Tests\Fixtures\Blog\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Blog\WorkspaceBoundary;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class BoundaryPolicyTest extends PackageTestCase
{
    protected User $ada;

    protected Post $releaseNotes;

    protected Post $archive;

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

        $acme = Client::factory()->createOne(['name' => 'Acme']);
        $this->ada = User::factory()->for($acme)->createOne(['name' => 'Ada', 'email' => 'ada@example.com', 'current_workspace_id' => 1]);
        $website = Project::factory()->for($acme)->createOne(['name' => 'Website']);
        $this->releaseNotes = Post::factory()->for($website)->for($this->ada, 'author')->createOne(['title' => 'Release notes', 'workspace_id' => 1]);
        $this->archive = Post::factory()->for($website)->for($this->ada, 'author')->createOne(['title' => 'Archive', 'workspace_id' => 1, 'archived_at' => '2026-01-01 00:00:00']);
        $this->elsewhere = Post::factory()->for($website)->for($this->ada, 'author')->createOne(['title' => 'Elsewhere', 'workspace_id' => 2]);
    }

    /** @param  list<string>  $abilities */
    protected function allow(array $abilities): void
    {
        foreach ($abilities as $ability) {
            Gate::define($ability, fn (User $user): bool => true);
        }
    }

    protected function bindGlobalLevelWithin(Boundary $boundary): void
    {
        ConfiguredPostControl::bindWithin(
            $boundary,
            Levels::of(
                GlobalPerimeter::new()
                    ->allowed(fn (User $user): bool => true)
                    ->unrestricted(),
            ),
        );
    }

    /** @return list<string> */
    protected function queriesWhile(callable $decisions): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $decisions();

        DB::disableQueryLog();

        return array_column(DB::getQueryLog(), 'query');
    }

    #[Test]
    public function it_refuses_a_row_outside_the_workspace_even_to_an_unrestricted_level(): void
    {
        $this->allow(['view global posts', 'update global posts']);

        $this->assertTrue($this->ada->can('update', $this->releaseNotes));
        $this->assertFalse($this->ada->can('update', $this->elsewhere));
        $this->assertFalse($this->ada->can('view', $this->elsewhere));
    }

    #[Test]
    public function it_keeps_an_archived_row_readable_but_refuses_the_other_abilities(): void
    {
        $this->allow(['view own posts', 'update own posts', 'delete own posts', 'approve own posts']);

        $this->assertTrue($this->ada->can('view', $this->archive));
        $this->assertFalse($this->ada->can('update', $this->archive));
        $this->assertFalse($this->ada->can('delete', $this->archive));
        $this->assertFalse($this->ada->can('approve', $this->archive));
        $this->assertTrue($this->ada->can('update', $this->releaseNotes));
    }

    #[Test]
    public function it_keeps_the_boundary_it_derives_a_read_only_boundary_from(): void
    {
        $this->allow(['update own posts']);
        $boundary = Boundary::where('workspace_id', fn (User $user): ?int => $user->current_workspace_id);
        $boundary->readOnlyWhenNotNull('archived_at');

        ConfiguredPostControl::bindWithin(
            $boundary,
            OwnPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->should(fn (User $user, Post $post): bool => $post->author_id === $user->getKey()),
        );

        $this->assertTrue($this->ada->can('update', $this->archive));
    }

    #[Test]
    public function it_checks_the_boundary_before_the_rows(): void
    {
        $checked = [];
        ConfiguredPostControl::bind(
            OwnPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->should(function (User $user, Post $post) use (&$checked): bool {
                    $checked[] = $post->title;

                    return true;
                }),
        );

        $this->assertFalse($this->ada->can('update', $this->elsewhere));
        $this->assertFalse($this->ada->can('update', $this->archive));
        $this->assertTrue($this->ada->can('update', $this->releaseNotes));
        $this->assertSame(['Release notes'], $checked);
    }

    #[Test]
    public function it_grants_an_unrestricted_level_without_sql_when_the_boundary_checks_in_memory(): void
    {
        $this->allow(['update global posts']);

        $queries = $this->queriesWhile(function (): void {
            $this->assertTrue($this->ada->can('update', $this->releaseNotes));
            $this->assertFalse($this->ada->can('update', $this->elsewhere));
        });

        $this->assertSame([], $queries);
    }

    #[Test]
    public function it_grants_an_unrestricted_level_without_sql_through_a_boundary_class_that_checks_in_memory(): void
    {
        $this->bindGlobalLevelWithin(new InMemoryWorkspaceBoundary);

        $queries = $this->queriesWhile(function (): void {
            $this->assertTrue($this->ada->can('update', $this->releaseNotes));
            $this->assertFalse($this->ada->can('update', $this->archive));
            $this->assertFalse($this->ada->can('update', $this->elsewhere));
        });

        $this->assertSame([], $queries);
    }

    #[Test]
    public function it_checks_the_boundary_of_an_unrestricted_level_with_one_exists_when_it_has_no_memory_form(): void
    {
        $this->bindGlobalLevelWithin(new WorkspaceBoundary);

        $queries = $this->queriesWhile(function (): void {
            $this->assertTrue($this->ada->can('update', $this->releaseNotes));
            $this->assertFalse($this->ada->can('update', $this->archive));
            $this->assertFalse($this->ada->can('update', $this->elsewhere));
            $this->assertTrue($this->ada->can('view', $this->archive));
        });

        $this->assertCount(4, $queries);
        $this->assertStringStartsWith('select exists(select * from posts where posts.id = ? and (workspace_id = ?', str_replace(['"', '`'], '', $queries[0]));
    }

    #[Test]
    public function it_refuses_every_ability_to_a_reader_without_a_workspace(): void
    {
        $this->allow(['view global posts', 'update global posts', 'create global posts']);
        $this->ada->update(['current_workspace_id' => null]);

        $this->assertFalse($this->ada->can('view', $this->releaseNotes));
        $this->assertFalse($this->ada->can('update', $this->releaseNotes));
        $this->assertFalse($this->ada->can('viewAny', Post::class));
        $this->assertFalse($this->ada->can('create', Post::class));
    }

    #[Test]
    public function it_leaves_the_abilities_without_a_row_to_the_permissions(): void
    {
        $this->allow(['create global posts', 'view own posts']);

        $this->assertTrue($this->ada->can('create', Post::class));
        $this->assertTrue($this->ada->can('viewAny', Post::class));
    }

    #[Test]
    public function it_tells_whether_a_boundary_checks_in_memory(): void
    {
        $this->assertTrue(Boundary::none()->checksInMemory());
        $this->assertTrue(Boundary::where('workspace_id', fn (User $user): ?int => $user->current_workspace_id)->checksInMemory());
        $this->assertTrue((new InMemoryWorkspaceBoundary)->checksInMemory());
        $this->assertFalse((new WorkspaceBoundary)->checksInMemory());
    }
}
