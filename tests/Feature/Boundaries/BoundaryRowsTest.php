<?php

namespace Lomkit\Access\Tests\Feature\Boundaries;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Exceptions\InvalidBoundaryValueException;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Tests\Fixtures\Agencies\Agencies;
use Lomkit\Access\Tests\Fixtures\Agencies\Member;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\TwoWorkspacesBoundary;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Blog\WorkspaceBoundary;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class BoundaryRowsTest extends PackageTestCase
{
    protected User $ada;

    protected User $hank;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Blog, new Agencies];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $acme = Client::factory()->createOne(['name' => 'Acme']);
        $globex = Client::factory()->createOne(['name' => 'Globex']);
        $this->ada = User::factory()->for($acme)->createOne(['name' => 'Ada', 'email' => 'ada@example.com', 'current_workspace_id' => 1]);
        $this->hank = User::factory()->for($globex)->createOne(['name' => 'Hank', 'email' => 'hank@example.com', 'current_workspace_id' => 1]);
        $website = Project::factory()->for($acme)->createOne(['name' => 'Website']);
        $warehouse = Project::factory()->for($globex)->createOne(['name' => 'Warehouse']);
        Post::factory()->for($website)->for($this->ada, 'author')->createOne(['title' => 'Release notes', 'workspace_id' => 1]);
        Post::factory()->for($website)->for($this->ada, 'author')->createOne(['title' => 'Archive', 'workspace_id' => 1, 'archived_at' => '2026-01-01 00:00:00']);
        Post::factory()->for($website)->for($this->ada, 'author')->createOne(['title' => 'Elsewhere', 'workspace_id' => 2]);
        Post::factory()->for($warehouse)->for($this->hank, 'author')->createOne(['title' => 'Inventory', 'workspace_id' => 1]);
    }

    /** @param  list<string>  $abilities */
    protected function allow(array $abilities): void
    {
        foreach ($abilities as $ability) {
            Gate::define($ability, fn (User $user): bool => true);
        }
    }

    /** @param  Builder<Post>  $query */
    protected function sql(Builder $query): string
    {
        return str_replace(['"', '`'], '', $query->toSql());
    }

    /** @return list<string> */
    protected function titlesFor(Model $reader): array
    {
        return Post::controlledFor($reader)->orderBy('title')->pluck('title')->all();
    }

    #[Test]
    public function it_keeps_an_unrestricted_level_inside_the_workspace_of_the_reader(): void
    {
        $this->allow(['view global posts']);

        $this->assertSame(['Archive', 'Inventory', 'Release notes'], $this->titlesFor($this->ada));
        $this->assertSame('select * from posts where ((posts.workspace_id = ?))', $this->sql(Post::controlledFor($this->ada)));
    }

    #[Test]
    public function it_applies_the_boundary_as_an_and_around_the_perimeters(): void
    {
        $this->allow(['view client posts', 'view shared posts']);
        Post::query()->where('title', 'Inventory')->firstOrFail()->sharedWith()->attach($this->ada);

        $this->assertSame(['Archive', 'Inventory', 'Release notes'], $this->titlesFor($this->ada));
        $this->assertSame(
            'select * from posts where ((posts.workspace_id = ?) and ((exists (select * from projects where posts.project_id = projects.id and projects.client_id in (?))) or (exists (select * from users inner join post_shares on users.id = post_shares.user_id where posts.id = post_shares.post_id and users.id = ?))))',
            $this->sql(Post::controlledFor($this->ada)),
        );
    }

    #[Test]
    public function it_never_lets_the_or_constraints_of_the_caller_escape_the_boundary(): void
    {
        $this->allow(['view own posts']);

        $query = Post::where('title', 'Release notes')->orWhere('title', 'Elsewhere')->controlledFor($this->ada);

        $this->assertSame(['Release notes'], $query->pluck('title')->all());
        $this->assertStringStartsWith('select * from posts where (title = ? or title = ?) and ((posts.workspace_id = ?) and (', $this->sql($query));
    }

    #[Test]
    public function it_keeps_the_or_constraints_of_a_boundary_class_inside_its_group(): void
    {
        ConfiguredPostControl::bindWithin(
            new TwoWorkspacesBoundary,
            OwnPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->query(function (Builder $query, User $user): void {
                    $query->whereBelongsTo($user, 'author');
                }),
        );

        $this->assertSame(['Inventory'], $this->titlesFor($this->hank));
    }

    #[Test]
    public function it_applies_a_boundary_class_in_sql(): void
    {
        $this->allow(['view global posts']);
        ConfiguredPostControl::bindWithin(new WorkspaceBoundary, ...$this->globalLevel());
        $this->ada->update(['current_workspace_id' => 2]);

        $this->assertSame(['Elsewhere'], $this->titlesFor($this->ada));
    }

    #[Test]
    public function it_keeps_archived_rows_in_the_controlled_queries(): void
    {
        $this->allow(['view own posts']);

        $this->assertSame(['Archive', 'Release notes'], $this->titlesFor($this->ada));
    }

    #[Test]
    public function it_reads_nothing_for_a_reader_without_a_workspace(): void
    {
        $this->allow(['view global posts']);
        $this->ada->update(['current_workspace_id' => null]);

        $query = Post::controlledFor($this->ada);

        $this->assertSame([], $this->titlesFor($this->ada));
        $this->assertSame('select * from posts where (0=1)', $this->sql($query));
        $this->assertStringNotContainsString('is null', $this->sql($query));
    }

    #[Test]
    public function it_reads_nothing_for_a_reader_type_the_boundary_does_not_accept(): void
    {
        $linus = Member::factory()->createOne(['name' => 'Linus', 'email' => 'linus@example.com']);
        ConfiguredPostControl::bind(
            GlobalPerimeter::new()
                ->allowed(fn (Model $reader): bool => true)
                ->unrestricted(),
        );

        $this->assertSame([], $this->titlesFor($linus));
        $this->assertSame(['Archive', 'Inventory', 'Release notes'], $this->titlesFor($this->ada));
    }

    #[Test]
    public function it_throws_for_a_reader_value_that_is_neither_an_int_nor_a_string(): void
    {
        ConfiguredPostControl::bindWithin(
            Boundary::where('workspace_id', fn (User $user): float => 1.5),
            ...$this->globalLevel(),
        );
        $this->allow(['view global posts']);

        $this->expectException(InvalidBoundaryValueException::class);
        $this->expectExceptionMessage(sprintf('The boundary of the [%s] model on [workspace_id] received a value of type [float] from the reader.', Post::class));

        Post::controlledFor($this->ada)->get();
    }

    #[Test]
    public function it_compares_a_string_reader_value_with_the_column(): void
    {
        $this->allow(['view global posts', 'update global posts']);
        ConfiguredPostControl::bindWithin(
            Boundary::where('workspace_id', fn (User $user): string => (string) $user->current_workspace_id),
            ...$this->globalLevel(),
        );

        $this->assertSame(['Archive', 'Inventory', 'Release notes'], $this->titlesFor($this->ada));
        $this->assertTrue($this->ada->can('update', Post::query()->where('title', 'Inventory')->firstOrFail()));
        $this->assertFalse($this->ada->can('update', Post::query()->where('title', 'Elsewhere')->firstOrFail()));
    }

    /** @return list<Levels> */
    protected function globalLevel(): array
    {
        return [
            Levels::of(
                GlobalPerimeter::new()
                    ->allowed(fn (User $user): bool => $user->can('view global posts'))
                    ->unrestricted(),
            ),
        ];
    }
}
