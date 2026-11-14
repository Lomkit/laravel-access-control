<?php

namespace Lomkit\Access\Tests\Feature\Combination;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Ability;
use Lomkit\Access\Exceptions\EmptyLevelsException;
use Lomkit\Access\Exceptions\EmptyQueryCallbackException;
use Lomkit\Access\Exceptions\NestedLevelsException;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\SharedPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class LevelsTest extends PackageTestCase
{
    protected User $ada;

    protected User $grace;

    protected Post $draft;

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
        $this->grace = User::factory()->for($acme)->createOne(['name' => 'Grace', 'email' => 'grace@example.com']);
        $hank = User::factory()->for($globex)->createOne(['name' => 'Hank', 'email' => 'hank@example.com']);
        $website = Project::factory()->for($acme)->createOne(['name' => 'Website']);
        $warehouse = Project::factory()->for($globex)->createOne(['name' => 'Warehouse']);
        Post::factory()->for($website)->for($this->ada, 'author')->createOne(['title' => 'Release notes']);
        $this->draft = Post::factory()->for($website)->for($this->ada, 'author')->createOne(['title' => 'Draft']);
        $this->roadmap = Post::factory()->for($website)->for($this->grace, 'author')->createOne(['title' => 'Roadmap']);
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

    /** @return list<string> */
    protected function titlesFor(User $reader): array
    {
        return Post::controlledFor($reader)->orderBy('title')->pluck('title')->all();
    }

    protected function unreachable(string $level): Perimeter
    {
        return OwnPerimeter::new()
            ->allowed(function (User $user) use ($level): bool {
                $this->fail("The {$level} level was evaluated below the level the reader reaches.");
            })
            ->should(function (User $user, Post $post) use ($level): bool {
                $this->fail("The {$level} level was evaluated below the level the reader reaches.");
            })
            ->query(function (Builder $query) use ($level): void {
                $this->fail("The {$level} level was evaluated below the level the reader reaches.");
            });
    }

    #[Test]
    public function it_refuses_a_group_without_levels(): void
    {
        $this->expectException(EmptyLevelsException::class);
        $this->expectExceptionMessage(sprintf('%s::of() received no level.', Levels::class));

        Levels::of();
    }

    #[Test]
    public function it_refuses_a_group_nested_in_another_group(): void
    {
        $this->expectException(NestedLevelsException::class);
        $this->expectExceptionMessage(sprintf('Level #2 given to %s::of() is another Levels group.', Levels::class));

        Levels::of(GlobalPerimeter::new(), Levels::of(ClientPerimeter::new(), OwnPerimeter::new()));
    }

    #[Test]
    public function it_applies_only_the_highest_level_the_reader_reaches(): void
    {
        $this->allow(['view client posts', 'view own posts']);

        $query = Post::controlledFor($this->ada);

        $this->assertSame(['Draft', 'Release notes', 'Roadmap'], $this->titlesFor($this->ada));
        $this->assertStringNotContainsString('author_id', str_replace(['"', '`'], '', $query->toSql()));
    }

    #[Test]
    public function it_falls_back_to_the_next_level_the_reader_reaches(): void
    {
        $this->allow(['view own posts']);

        $this->assertSame(['Draft', 'Release notes'], $this->titlesFor($this->ada));
    }

    #[Test]
    public function it_chooses_the_level_for_each_ability(): void
    {
        $this->allow(['view client posts', 'update own posts']);

        $this->assertSame(['Draft', 'Release notes', 'Roadmap'], $this->titlesFor($this->ada));
        $this->assertTrue($this->ada->can('view', $this->roadmap));
        $this->assertTrue($this->ada->can('update', $this->draft));
        $this->assertFalse($this->ada->can('update', $this->roadmap));
        $this->assertFalse($this->ada->can('view', $this->inventory));
    }

    #[Test]
    public function it_does_not_evaluate_the_levels_below_the_reached_one(): void
    {
        ConfiguredPostControl::bind(
            Levels::of(
                ClientPerimeter::new()
                    ->allowed(fn (User $user): bool => true)
                    ->should(fn (User $user, Post $post): bool => $post->project->client()->is($user->client))
                    ->query(function (Builder $query, User $user): void {
                        $query->whereHas('project', fn (Builder $projects) => $projects->whereBelongsTo($user->client));
                    }),
                $this->unreachable('own'),
            ),
        );

        $this->assertSame(['Draft', 'Release notes', 'Roadmap'], $this->titlesFor($this->ada));
        $this->assertTrue($this->ada->can('update', $this->roadmap));
        $this->assertFalse($this->ada->can('update', $this->inventory));
    }

    #[Test]
    public function it_adds_the_perimeters_outside_the_group_to_the_reached_level(): void
    {
        $this->allow(['view client posts', 'view shared posts', 'update own posts', 'update shared posts']);

        $this->assertSame(['Draft', 'Inventory', 'Release notes', 'Roadmap'], $this->titlesFor($this->ada));
        $this->assertTrue($this->ada->can('update', $this->inventory));
        $this->assertTrue($this->ada->can('update', $this->draft));
        $this->assertFalse($this->ada->can('update', $this->roadmap));
    }

    #[Test]
    public function it_adds_up_a_perimeter_wherever_it_is_written(): void
    {
        $shared = SharedPerimeter::new()
            ->allowed(fn (User $user): bool => true)
            ->query(function (Builder $query, User $user): void {
                $query->whereHas('sharedWith', fn (Builder $users) => $users->whereKey($user->getKey()));
            });
        $levels = Levels::of(
            OwnPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->query(function (Builder $query, User $user): void {
                    $query->whereBelongsTo($user, 'author');
                }),
        );

        ConfiguredPostControl::bind($shared, $levels);
        $before = $this->titlesFor($this->ada);

        ConfiguredPostControl::bind($levels, $shared);

        $this->assertSame(['Draft', 'Inventory', 'Release notes'], $before);
        $this->assertSame($before, $this->titlesFor($this->ada));
    }

    #[Test]
    public function it_adds_up_loose_perimeters_and_several_groups(): void
    {
        ConfiguredPostControl::bind(
            Levels::of(
                OwnPerimeter::new()
                    ->allowed(fn (User $user): bool => true)
                    ->query(function (Builder $query, User $user): void {
                        $query->whereBelongsTo($user, 'author');
                    }),
            ),
            Levels::of(
                ClientPerimeter::new()
                    ->allowed(fn (User $user): bool => false)
                    ->query(fn (Builder $query) => $query->whereRaw('1=1')),
                SharedPerimeter::new()
                    ->allowed(fn (User $user): bool => true)
                    ->query(function (Builder $query, User $user): void {
                        $query->whereHas('sharedWith', fn (Builder $users) => $users->whereKey($user->getKey()));
                    }),
            ),
            GlobalPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->query(fn (Builder $query) => $query->where('title', 'Roadmap')),
        );

        $this->assertSame(['Draft', 'Inventory', 'Release notes', 'Roadmap'], $this->titlesFor($this->ada));
    }

    #[Test]
    public function it_reads_every_post_for_an_administrator_who_also_has_the_shared_permission(): void
    {
        $this->allow(['view global posts', 'view shared posts']);

        $query = Post::controlledFor($this->ada);

        $this->assertSame(['Draft', 'Inventory', 'Release notes', 'Roadmap'], $this->titlesFor($this->ada));
        $this->assertSame('select * from posts where ((posts.workspace_id = ?))', str_replace(['"', '`'], '', $query->toSql()));
    }

    #[Test]
    public function it_does_not_evaluate_the_perimeters_after_an_unrestricted_level(): void
    {
        ConfiguredPostControl::bind(
            Levels::of(
                GlobalPerimeter::new()
                    ->allowed(fn (User $user): bool => true)
                    ->unrestricted(),
                $this->unreachable('client'),
            ),
            $this->unreachable('shared'),
        );

        $this->assertSame(4, Post::controlledFor($this->ada)->count());
        $this->assertTrue($this->ada->can('delete', $this->inventory));
    }

    #[Test]
    public function it_keeps_the_empty_query_guard_inside_a_group(): void
    {
        $this->allow(['view shared posts']);

        ConfiguredPostControl::bind(
            Levels::of(
                ClientPerimeter::new()
                    ->allowed(fn (User $user): bool => true)
                    ->query(fn (Builder $query) => $query),
            ),
            SharedPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->query(function (Builder $query, User $user): void {
                    $query->whereHas('sharedWith', fn (Builder $users) => $users->whereKey($user->getKey()));
                }),
        );

        $this->expectException(EmptyQueryCallbackException::class);

        Post::controlledFor($this->ada)->get();
    }

    #[Test]
    public function it_reads_nothing_when_no_level_and_no_perimeter_allows_the_reader(): void
    {
        $this->allow(['update own posts']);

        $this->assertSame([], $this->titlesFor($this->ada));
        $this->assertFalse($this->ada->can('view', $this->draft));
    }

    #[Test]
    public function it_lists_the_reached_level_and_the_loose_perimeters_as_applicable(): void
    {
        $client = ClientPerimeter::new()->allowed(fn (User $user, Ability $ability): bool => $ability->is('view'));
        $own = OwnPerimeter::new()->allowed(fn (User $user): bool => true);
        $shared = SharedPerimeter::new()->allowed(fn (User $user, Ability $ability): bool => $ability->is('update'));
        $global = GlobalPerimeter::new()->allowed(fn (User $user, Ability $ability): bool => $ability->is('delete'))->unrestricted();
        $control = ConfiguredPostControl::bind($shared, Levels::of($global, $client, $own));

        $this->assertSame([$client], $control->applicableTo($this->ada, Ability::view()));
        $this->assertSame([$shared, $own], $control->applicableTo($this->ada, Ability::update()));
        $this->assertSame([$global], $control->applicableTo($this->ada, Ability::delete()));
    }
}
