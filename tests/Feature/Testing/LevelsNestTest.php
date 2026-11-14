<?php

namespace Lomkit\Access\Tests\Feature\Testing;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\PostControl;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\SharedPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use Lomkit\Access\Tests\Fixtures\Testing\AccessTestCase;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\Test;

class LevelsNestTest extends AccessTestCase
{
    protected User $ada;

    protected User $grace;

    protected User $hank;

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
        $this->grace = User::factory()->for($acme)->createOne(['name' => 'Grace', 'email' => 'grace@example.com']);
        $this->hank = User::factory()->for($globex)->createOne(['name' => 'Hank', 'email' => 'hank@example.com']);
        $website = Project::factory()->for($acme)->createOne(['name' => 'Website']);
        $warehouse = Project::factory()->for($globex)->createOne(['name' => 'Warehouse']);
        Post::factory()->for($website)->for($this->ada, 'author')->createOne(['title' => 'Release notes']);
        Post::factory()->for($website)->for($this->grace, 'author')->createOne(['title' => 'Roadmap', 'archived_at' => '2026-01-01 00:00:00']);
        Post::factory()->for($warehouse)->for($this->hank, 'author')->createOne(['title' => 'Inventory']);
        Post::factory()->for($warehouse)->for($this->ada, 'author')->createOne(['title' => 'Guest post', 'workspace_id' => 2]);
    }

    protected function client(): Perimeter
    {
        return ClientPerimeter::new()
            ->allowed(fn (User $user): bool => true)
            ->query(function (Builder $query, User $user): void {
                $query->whereHas('project', fn (Builder $projects) => $projects->whereBelongsTo($user->client));
            });
    }

    protected function own(): Perimeter
    {
        return OwnPerimeter::new()
            ->allowed(fn (User $user): bool => true)
            ->query(function (Builder $query, User $user): void {
                $query->whereBelongsTo($user, 'author');
            });
    }

    #[Test]
    public function it_passes_on_the_levels_of_the_blog_control(): void
    {
        $this->assertLevelsNest(PostControl::class, readers: [$this->ada, $this->grace, $this->hank]);
    }

    #[Test]
    public function it_fails_when_a_lower_level_reaches_rows_the_upper_level_does_not(): void
    {
        ConfiguredPostControl::bind(Levels::of($this->own(), $this->client()));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage(sprintf(
            'The levels of the [%s] control do not nest for the [%s] reader with key [%s]: rows of the [%s] level are missing from the [%s] level above it.',
            PostControl::class,
            User::class,
            $this->grace->getKey(),
            ClientPerimeter::class,
            OwnPerimeter::class,
        ));

        $this->assertLevelsNest(PostControl::class, readers: [$this->grace]);
    }

    #[Test]
    public function it_is_not_fooled_by_null_values_in_the_upper_level(): void
    {
        ConfiguredPostControl::bind(Levels::of(
            SharedPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->query(fn (Builder $query) => $query->where('archived_at', '<', '2030-01-01 00:00:00')),
            $this->client(),
        ));
        $naiveMissingRows = Post::query()
            ->whereHas('project', fn (Builder $projects) => $projects->whereBelongsTo($this->grace->client))
            ->whereRaw('not (archived_at < ?)', ['2030-01-01 00:00:00'])
            ->count();

        $this->assertSame(0, $naiveMissingRows);
        $this->expectException(AssertionFailedError::class);

        $this->assertLevelsNest(PostControl::class, readers: [$this->grace]);
    }

    #[Test]
    public function it_checks_each_group_and_each_reader(): void
    {
        ConfiguredPostControl::bind(
            Levels::of($this->client(), $this->own()),
            Levels::of($this->own(), $this->client()),
        );

        $this->expectException(AssertionFailedError::class);

        $this->assertLevelsNest(PostControl::class, readers: [$this->hank, $this->ada]);
    }

    #[Test]
    public function it_ignores_the_rows_outside_the_boundary(): void
    {
        ConfiguredPostControl::bind(Levels::of($this->client(), $this->own()));

        $this->assertLevelsNest(PostControl::class, readers: [$this->ada]);
    }

    #[Test]
    public function it_runs_no_query_below_an_unrestricted_level(): void
    {
        ConfiguredPostControl::bind(Levels::of(
            GlobalPerimeter::new()
                ->allowed(fn (User $user): bool => false)
                ->unrestricted(),
            $this->own(),
        ));
        DB::enableQueryLog();

        $this->assertLevelsNest(PostControl::class, readers: [$this->ada]);

        $this->assertSame([], DB::getQueryLog());
    }

    #[Test]
    public function it_fails_for_a_control_without_levels(): void
    {
        ConfiguredPostControl::bind($this->own());

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage(sprintf('The [%s] control declares no Levels group, so it has no levels to nest.', PostControl::class));

        $this->assertLevelsNest(PostControl::class, readers: [$this->ada]);
    }

    #[Test]
    public function it_fails_without_readers(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage(sprintf('assertLevelsNest() received no reader for the [%s] control', PostControl::class));

        $this->assertLevelsNest(PostControl::class, readers: []);
    }
}
