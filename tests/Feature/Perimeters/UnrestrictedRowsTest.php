<?php

namespace Lomkit\Access\Tests\Feature\Perimeters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\SharedPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class UnrestrictedRowsTest extends PackageTestCase
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
        Post::factory()->for($website)->for($this->ada, 'author')->createOne(['title' => 'Release notes']);
        Post::factory()->for($website)->for($this->ada, 'author')->createOne(['title' => 'Draft', 'published' => false]);
        Post::factory()->for($warehouse)->for($hank, 'author')->createOne(['title' => 'Roadmap']);
        $inventory = Post::factory()->for($warehouse)->for($hank, 'author')->createOne(['title' => 'Inventory']);
        $inventory->sharedWith()->attach($this->ada);
    }

    /** @param  list<string>  $abilities */
    protected function allow(array $abilities): void
    {
        foreach ($abilities as $ability) {
            Gate::define($ability, fn (User $user): bool => true);
        }
    }

    #[Test]
    public function it_reads_every_post_through_an_unrestricted_perimeter(): void
    {
        $this->allow(['view global posts']);

        $query = Post::controlledFor($this->ada);

        $this->assertSame(['Draft', 'Inventory', 'Release notes', 'Roadmap'], $query->orderBy('title')->pluck('title')->all());
        $this->assertSame('select * from posts order by title asc', $this->sql($query));
    }

    #[Test]
    public function it_reads_every_post_for_a_reader_with_the_global_and_the_shared_permissions(): void
    {
        $this->allow(['view global posts', 'view shared posts']);

        $query = Post::controlledFor($this->ada);

        $this->assertSame(['Draft', 'Inventory', 'Release notes', 'Roadmap'], $query->orderBy('title')->pluck('title')->all());
        $this->assertSame('select * from posts order by title asc', $this->sql($query));
    }

    #[Test]
    public function it_keeps_the_constraints_of_the_caller_on_an_unrestricted_perimeter(): void
    {
        $this->allow(['view global posts', 'view shared posts']);

        $query = Post::where('published', true)->controlledFor($this->ada);

        $this->assertSame(['Inventory', 'Release notes', 'Roadmap'], $query->orderBy('title')->pluck('title')->all());
        $this->assertSame('select * from posts where published = ? order by title asc', $this->sql($query));
    }

    #[Test]
    public function it_does_not_evaluate_the_queries_of_the_other_applicable_perimeters(): void
    {
        $evaluated = [];

        ConfiguredPostControl::bind(
            SharedPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->query(function (Builder $query) use (&$evaluated): void {
                    $evaluated[] = 'shared';
                    $query->whereRaw('1=1');
                }),
            GlobalPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->unrestricted(),
            ClientPerimeter::new()
                ->allowed(function (User $user) use (&$evaluated): bool {
                    $evaluated[] = 'client';

                    return true;
                })
                ->query(fn (Builder $query) => $query->whereRaw('1=1')),
        );

        $this->assertSame(4, Post::controlledFor($this->ada)->count());
        $this->assertSame([], $evaluated);
    }

    #[Test]
    public function it_reads_every_post_through_an_unrestricted_overlay_before_another_perimeter(): void
    {
        ConfiguredPostControl::bind(
            SharedPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->unrestricted(),
            ClientPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->query(function (Builder $query, User $user): void {
                    $query->whereHas('project', fn (Builder $projects) => $projects->whereBelongsTo($user->client));
                }),
        );

        $this->assertSame(4, Post::controlledFor($this->ada)->count());
    }

    #[Test]
    public function it_restricts_the_rows_when_the_unrestricted_perimeter_does_not_allow_the_reader(): void
    {
        $this->allow(['view shared posts', 'update global posts']);

        $this->assertSame(['Inventory'], Post::controlledFor($this->ada)->pluck('title')->all());
    }

    #[Test]
    public function it_reads_nothing_for_a_null_reader_even_with_an_unrestricted_perimeter(): void
    {
        $this->allow(['view global posts']);

        $this->assertSame(0, Post::controlledFor(null)->count());
    }

    /** @param  Builder<Post>  $query */
    private function sql(Builder $query): string
    {
        return str_replace(['"', '`'], '', $query->toSql());
    }
}
