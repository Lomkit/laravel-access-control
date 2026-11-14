<?php

namespace Lomkit\Access\Tests\Feature\Policies;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\Decisions\Draft;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\InMemoryWorkspaceBoundary;
use Lomkit\Access\Tests\Fixtures\Blog\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\PostControl;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Blog\WorkspaceBoundary;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class DraftTest extends PackageTestCase
{
    protected User $ada;

    protected User $hank;

    protected Project $website;

    protected Project $warehouse;

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
        $this->ada = User::factory()->for($acme)->createOne(['name' => 'Ada', 'email' => 'ada@example.com', 'current_workspace_id' => 1]);
        $this->hank = User::factory()->for($globex)->createOne(['name' => 'Hank', 'email' => 'hank@example.com', 'current_workspace_id' => 1]);
        $this->website = Project::factory()->for($acme)->createOne(['name' => 'Website']);
        $this->warehouse = Project::factory()->for($globex)->createOne(['name' => 'Warehouse']);
    }

    /** @param  list<string>  $permissions */
    protected function allow(array $permissions): void
    {
        foreach ($permissions as $permission) {
            Gate::define($permission, fn (User $user): bool => true);
        }
    }

    protected function draft(Project $project, User $author, int $workspace): Post
    {
        return new Post([
            'project_id' => $project->getKey(),
            'author_id' => $author->getKey(),
            'title' => 'Draft',
            'workspace_id' => $workspace,
        ]);
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
    public function it_grants_a_draft_that_lands_in_a_perimeter_of_the_reader(): void
    {
        $this->allow(['create client posts']);

        $this->assertTrue($this->ada->can('create', [Post::class, $this->draft($this->website, $this->hank, 1)]));
    }

    #[Test]
    public function it_refuses_a_draft_outside_the_perimeters_of_the_reader(): void
    {
        $this->allow(['create client posts']);

        $response = Gate::forUser($this->ada)->inspect('create', [Post::class, $this->draft($this->warehouse, $this->ada, 1)]);

        $this->assertTrue($response->denied());
        $this->assertSame('outside_rows', $response->code());
        $this->assertNull($response->status());
    }

    #[Test]
    public function it_refuses_a_draft_outside_the_boundary_with_a_403(): void
    {
        $this->allow(['create global posts']);

        $response = Gate::forUser($this->ada)->inspect('create', [Post::class, $this->draft($this->website, $this->ada, 2)]);

        $this->assertTrue($response->denied());
        $this->assertSame('outside_boundary', $response->code());
        $this->assertNull($response->status());
    }

    #[Test]
    public function it_grants_a_draft_to_an_unrestricted_level_inside_the_boundary_only(): void
    {
        $this->allow(['create global posts']);

        $this->assertTrue($this->ada->can('create', [Post::class, $this->draft($this->warehouse, $this->hank, 1)]));
        $this->assertFalse($this->ada->can('create', [Post::class, $this->draft($this->website, $this->ada, 2)]));
    }

    #[Test]
    public function it_refuses_a_draft_through_a_perimeter_without_an_in_memory_form(): void
    {
        ConfiguredPostControl::bind(
            ClientPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->query(function (Builder $query, User $user): void {
                    $query->whereHas('project', fn (Builder $projects) => $projects->whereBelongsTo($user->client));
                }),
        );

        $queries = $this->queriesWhile(function (): void {
            $this->assertFalse($this->ada->can('create', [Post::class, $this->draft($this->website, $this->ada, 1)]));
        });

        $this->assertSame([], $queries);
        $this->assertTrue($this->ada->can('create', Post::class));
    }

    #[Test]
    public function it_grants_a_draft_through_another_perimeter_that_checks_in_memory(): void
    {
        ConfiguredPostControl::bind(
            ClientPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->query(function (Builder $query, User $user): void {
                    $query->whereHas('project', fn (Builder $projects) => $projects->whereBelongsTo($user->client));
                }),
            OwnPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->should(fn (User $user, Post $post): bool => $post->author_id === $user->getKey()),
        );

        $this->assertTrue($this->ada->can('create', [Post::class, $this->draft($this->website, $this->ada, 1)]));
        $this->assertFalse($this->ada->can('create', [Post::class, $this->draft($this->website, $this->hank, 1)]));
    }

    #[Test]
    public function it_refuses_a_draft_when_the_boundary_has_no_in_memory_form(): void
    {
        ConfiguredPostControl::bindWithin(
            new WorkspaceBoundary,
            GlobalPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->unrestricted(),
        );

        $response = Gate::forUser($this->ada)->inspect('create', [Post::class, $this->draft($this->website, $this->ada, 1)]);

        $this->assertSame('outside_boundary', $response->code());
        $this->assertTrue($this->ada->can('create', Post::class));
    }

    #[Test]
    public function it_judges_a_draft_with_the_in_memory_form_of_a_boundary_class(): void
    {
        ConfiguredPostControl::bindWithin(
            new InMemoryWorkspaceBoundary,
            GlobalPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->unrestricted(),
        );

        $this->assertTrue($this->ada->can('create', [Post::class, $this->draft($this->website, $this->ada, 1)]));
        $this->assertFalse($this->ada->can('create', [Post::class, $this->draft($this->website, $this->ada, 2)]));
    }

    #[Test]
    public function it_judges_a_draft_in_memory_without_any_query(): void
    {
        $this->allow(['create own posts']);

        $queries = $this->queriesWhile(function (): void {
            $this->assertTrue($this->ada->can('create', [Post::class, $this->draft($this->warehouse, $this->ada, 1)]));
            $this->assertFalse($this->ada->can('create', [Post::class, $this->draft($this->warehouse, $this->hank, 1)]));
        });

        $this->assertSame([], $queries);
    }

    #[Test]
    public function it_judges_the_draft_that_laravel_rest_api_passes_to_create(): void
    {
        $this->allow(['create client posts']);
        $gate = Gate::forUser($this->ada);

        $this->assertTrue($gate->inspect('create', [$this->draft($this->website, $this->ada, 1)])->allowed());
        $this->assertSame('outside_rows', $gate->inspect('create', [$this->draft($this->warehouse, $this->ada, 1)])->code());
        $this->assertTrue($gate->inspect('create', [Post::class])->allowed());
    }

    #[Test]
    public function it_keeps_create_without_a_draft_meaning_somewhere(): void
    {
        $this->allow(['create own posts']);

        $this->assertTrue($this->ada->can('create', Post::class));
        $this->assertTrue(Gate::forUser($this->ada)->inspect('create', [Post::class])->allowed());
    }

    #[Test]
    public function it_refuses_a_draft_to_a_reader_without_a_perimeter(): void
    {
        $response = Gate::forUser($this->ada)->inspect('create', [Post::class, $this->draft($this->website, $this->ada, 1)]);

        $this->assertSame('no_perimeter', $response->code());
    }

    #[Test]
    public function it_judges_a_draft_given_to_the_control(): void
    {
        $this->allow(['create own posts']);
        $control = Access::control(PostControl::class);

        $this->assertTrue($control->applies($this->ada, Ability::create(), Draft::of($this->draft($this->website, $this->ada, 1))));
        $this->assertFalse($control->applies($this->ada, Ability::create(), $this->draft($this->website, $this->ada, 1)));
        $this->assertSame('unsaved', $control->decide($this->ada, Ability::create(), $this->draft($this->website, $this->ada, 1))->code());
    }
}
