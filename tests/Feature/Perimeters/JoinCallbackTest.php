<?php

namespace Lomkit\Access\Tests\Feature\Perimeters;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Exceptions\EmptyQueryCallbackException;
use Lomkit\Access\Exceptions\JoinInPerimeterException;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\JoiningWorkspaceBoundary;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class JoinCallbackTest extends PackageTestCase
{
    protected User $ada;

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
        Post::factory()->for($website)->for($hank, 'author')->createOne(['title' => 'Release notes']);
        Post::factory()->for($warehouse)->for($hank, 'author')->createOne(['title' => 'Roadmap']);
    }

    protected function controlPostsWith(Closure $query): void
    {
        ConfiguredPostControl::bind(
            ClientPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->query($query),
        );
    }

    /** @return list<string> */
    protected function readTitles(): array
    {
        return Post::controlledFor($this->ada)->orderBy('title')->pluck('title')->all();
    }

    #[Test]
    public function it_throws_when_a_query_callback_adds_a_join_and_a_constraint(): void
    {
        $this->controlPostsWith(function (Builder $query, User $user): void {
            $query->join('projects', 'projects.id', '=', 'posts.project_id')->where('projects.client_id', $user->client_id);
        });

        $this->expectException(JoinInPerimeterException::class);
        $this->expectExceptionMessage(sprintf(
            'The [query] callback of the [%s] perimeter in the [%s] control adds a join.',
            ClientPerimeter::class,
            ConfiguredPostControl::class,
        ));
        $this->expectExceptionMessage('use whereHas() or an exists subquery instead.');

        $this->readTitles();
    }

    #[Test]
    public function it_keeps_throwing_that_a_join_alone_adds_no_constraint(): void
    {
        $this->controlPostsWith(function (Builder $query): void {
            $query->join('projects', 'projects.id', '=', 'posts.project_id');
        });

        $this->expectException(EmptyQueryCallbackException::class);

        $this->readTitles();
    }

    #[Test]
    public function it_accepts_a_where_has_constraint(): void
    {
        $this->controlPostsWith(function (Builder $query, User $user): void {
            $query->whereHas('project', fn (Builder $projects) => $projects->where('client_id', $user->client_id));
        });

        $this->assertSame(['Release notes'], $this->readTitles());
    }

    #[Test]
    public function it_accepts_an_exists_subquery(): void
    {
        $this->controlPostsWith(function (Builder $query, User $user): void {
            $query->whereExists(function ($projects) use ($user): void {
                $projects->selectRaw('1')
                    ->from('projects')
                    ->whereColumn('projects.id', 'posts.project_id')
                    ->where('projects.client_id', $user->client_id);
            });
        });

        $this->assertSame(['Release notes'], $this->readTitles());
    }

    #[Test]
    public function it_throws_when_a_boundary_scope_adds_a_join(): void
    {
        ConfiguredPostControl::bindWithin(
            new JoiningWorkspaceBoundary,
            Levels::of(
                GlobalPerimeter::new()
                    ->allowed(fn (User $user): bool => true)
                    ->unrestricted(),
            ),
        );

        $this->expectException(JoinInPerimeterException::class);
        $this->expectExceptionMessage(sprintf(
            'The scope() of the [%s] boundary adds a join to the query of the [%s] model.',
            JoiningWorkspaceBoundary::class,
            Post::class,
        ));

        $this->readTitles();
    }

    #[Test]
    public function it_throws_when_the_policy_checks_a_row_with_a_joining_boundary(): void
    {
        ConfiguredPostControl::bindWithin(
            new JoiningWorkspaceBoundary,
            Levels::of(
                GlobalPerimeter::new()
                    ->allowed(fn (User $user): bool => true)
                    ->unrestricted(),
            ),
        );

        $this->expectException(JoinInPerimeterException::class);

        $this->ada->can('update', Post::query()->firstOrFail());
    }
}
