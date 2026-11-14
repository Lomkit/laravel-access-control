<?php

namespace Lomkit\Access\Tests\Feature\Decisions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Exceptions\InvalidAbilityException;
use Lomkit\Access\Exceptions\MissingControlledReaderException;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\SharedPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Blog\WorkspaceBoundary;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use PHPUnit\Framework\Attributes\Test;

class WithAbilitiesTest extends BatchTestCase
{
    protected const array ABILITIES = ['view', 'update', 'delete'];

    protected function bindRules(): void
    {
        ConfiguredPostControl::bindWithin(
            Boundary::where('workspace_id', fn (User $user): ?int => $user->current_workspace_id)->readOnlyWhenNotNull('archived_at'),
            Levels::of($this->clientInSql(), $this->ownInSql()),
        );
    }

    /** @return Collection<int, Post> */
    protected function batch(User $reader): Collection
    {
        return Post::controlledFor($reader)
            ->withAbilities(self::ABILITIES)
            ->orderBy('id')
            ->get();
    }

    #[Test]
    public function it_decides_the_abilities_of_every_row_in_the_query_that_loads_them(): void
    {
        $this->bindRules();

        $queries = $this->queriesWhile(function (): void {
            $this->batch($this->ada);
        });

        $this->assertCount(1, $queries);
        $this->assertStringContainsString("then 'granted' else 'outside_rows' end", $queries[0]);
    }

    #[Test]
    public function it_answers_the_gate_from_the_batch_without_any_query(): void
    {
        $this->bindRules();
        $posts = $this->batch($this->ada);

        $queries = $this->queriesWhile(function () use ($posts): void {
            $this->answers($this->ada, self::ABILITIES, $posts);
        });

        $this->assertSame([], $queries);
    }

    #[Test]
    public function it_gives_the_same_answers_as_the_row_by_row_decisions(): void
    {
        $this->bindRules();
        $posts = $this->batch($this->ada);
        $batched = $this->answers($this->ada, self::ABILITIES, $posts);

        $this->assertSame($this->rowByRow($this->ada, self::ABILITIES, $posts), $batched);
        $this->assertSame('outside_boundary', $batched[Post::class.'#'.$this->archived->getKey()]['update']);
        $this->assertSame('granted', $batched[Post::class.'#'.$this->teamPost->getKey()]['update']);
    }

    #[Test]
    public function it_reads_the_not_found_answer_of_the_view_ability_from_the_batch(): void
    {
        ConfiguredPostControl::bindWithin(new WorkspaceBoundary, $this->ownInSql());
        $posts = $this->batch($this->ada);
        $everyPost = $this->everyPost();

        foreach (self::ABILITIES as $ability) {
            Access::preload($this->ada, $ability, $everyPost);
        }

        $queries = $this->queriesWhile(function () use ($everyPost, &$batched): void {
            $batched = $this->answers($this->ada, self::ABILITIES, $everyPost);
        });

        $this->assertSame([], $queries);
        $this->assertSame($this->rowByRow($this->ada, self::ABILITIES, $everyPost), $batched);
        $this->assertSame('outside_boundary', $batched[Post::class.'#'.$this->archived->getKey()]['update']);
        $this->assertSame('not_found', $batched[Post::class.'#'.$this->elsewhere->getKey()]['update']);
        $this->assertCount(2, $posts);
    }

    #[Test]
    public function it_accepts_abilities_as_names_or_objects_and_maps_policy_methods(): void
    {
        $this->bindRules();

        Post::controlledFor($this->ada)->withAbilities(['viewAny', Ability::update(), 'approve'])->get();

        $queries = $this->queriesWhile(function (): void {
            $this->assertTrue($this->ada->can('view', $this->teamPost));
            $this->assertTrue($this->ada->can('update', $this->teamPost));
            $this->assertTrue($this->ada->can('approve', $this->teamPost));
        });
        $this->assertSame([], $queries);
    }

    #[Test]
    public function it_refuses_an_ability_the_control_cannot_map(): void
    {
        $this->bindRules();

        $this->expectException(InvalidAbilityException::class);

        Post::controlledFor($this->ada)->withAbilities(['bulk update']);
    }

    #[Test]
    public function it_refuses_a_query_that_names_no_reader(): void
    {
        $this->bindRules();

        $this->expectException(MissingControlledReaderException::class);
        $this->expectExceptionMessage(sprintf('withAbilities() on a query of the [%s] model has no reader to decide for. Call controlled() or controlledFor($reader) before withAbilities().', Post::class));

        Post::query()->withAbilities(['update']);
    }

    #[Test]
    public function it_decides_for_the_signed_in_user_through_controlled(): void
    {
        $this->bindRules();
        $this->actingAs($this->ada);

        Post::controlled()->withAbilities(['update'])->get();

        $queries = $this->queriesWhile(function (): void {
            $this->assertTrue($this->ada->can('update', $this->teamPost));
        });
        $this->assertSame([], $queries);
    }

    #[Test]
    public function it_loads_nothing_and_decides_nothing_for_a_guest(): void
    {
        $this->bindRules();

        $this->assertCount(0, Post::controlled()->withAbilities(['update'])->get());
        $this->assertSame(0, $this->memo()->count());
    }

    #[Test]
    public function it_decides_for_a_reader_resolved_in_a_job(): void
    {
        $this->bindRules();
        $reader = User::query()->findOrFail($this->ada->getKey());

        Post::controlledFor($reader)->withAbilities(['update'])->get();

        $queries = $this->queriesWhile(function (): void {
            $this->assertTrue($this->ada->can('update', $this->teamPost));
            $this->assertFalse($this->grace->can('update', $this->otherClientPost));
        });
        $this->assertCount(1, $queries);
    }

    #[Test]
    public function it_leaves_no_reserved_column_in_the_attributes_the_original_or_the_json(): void
    {
        $this->bindRules();

        foreach ($this->batch($this->ada) as $post) {
            $this->assertSame([], array_values(array_filter(array_keys([...$post->getAttributes(), ...$post->getRawOriginal(), ...$post->toArray()]), fn (string $name): bool => str_starts_with($name, '__access'))));
            $this->assertFalse($post->isDirty());
            $this->assertStringNotContainsString('__access', $post->toJson());
        }
    }

    #[Test]
    public function it_keeps_the_columns_the_query_selects(): void
    {
        $this->bindRules();

        $post = Post::controlledFor($this->ada)->withAbilities(['update'])->select(['posts.id', 'posts.title'])->orderBy('id')->firstOrFail();

        $this->assertSame(['id', 'title'], array_keys($post->getAttributes()));
        $this->assertSame(1, $this->memo()->count());
    }

    #[Test]
    public function it_files_each_verdict_under_the_key_of_the_row_even_when_a_join_overwrites_it(): void
    {
        $this->bindRules();
        DB::table('comments')->insert(['id' => $this->teamPost->getKey(), 'post_id' => $this->archived->getKey(), 'author_id' => $this->ada->getKey(), 'body' => 'Collides', 'created_at' => now(), 'updated_at' => now()]);

        Post::uncontrolled()
            ->join('comments', 'comments.post_id', '=', 'posts.id')
            ->where('comments.id', $this->teamPost->getKey())
            ->select('posts.*', 'comments.id')
            ->controlledFor($this->ada)
            ->withAbilities(['update'])
            ->get();

        $this->assertSame(1, $this->memo()->count());
        $this->assertFalse($this->memoizedUpdate($this->ada, $this->archived));
        $this->assertNull($this->memoizedUpdate($this->ada, $this->teamPost));
    }

    #[Test]
    public function it_works_with_every_way_of_running_the_query(): void
    {
        $this->bindRules();
        $runs = [
            'paginate' => fn (Builder $query) => $query->paginate(10)->items(),
            'simplePaginate' => fn (Builder $query) => $query->simplePaginate(10)->items(),
            'cursorPaginate' => fn (Builder $query) => $query->cursorPaginate(10)->items(),
            'chunk' => function (Builder $query): array {
                $rows = [];
                $query->chunk(2, function (Collection $chunk) use (&$rows): void {
                    array_push($rows, ...$chunk->all());
                });

                return $rows;
            },
            'lazy' => fn (Builder $query) => $query->lazy(2)->all(),
            'cursor' => fn (Builder $query) => $query->cursor()->all(),
            'first' => fn (Builder $query) => [$query->firstOrFail()],
        ];

        foreach ($runs as $name => $run) {
            Access::forgetDecisions();
            $rows = $run(Post::controlledFor($this->ada)->withAbilities(['update'])->orderBy('id'));

            $this->assertNotSame([], $rows, $name);
            $queries = $this->queriesWhile(function () use ($rows): void {
                $this->answers($this->ada, ['update'], $rows);
            });
            $this->assertSame([], $queries, $name);
            foreach ($rows as $row) {
                $this->assertArrayNotHasKey('__access_key', $row->getAttributes(), $name);
            }
        }
    }

    #[Test]
    public function it_costs_only_the_queries_of_a_page_for_every_gate_check_on_it(): void
    {
        $this->bindRules();

        $queries = $this->queriesWhile(function (): void {
            $page = Post::controlledFor($this->ada)->withAbilities(self::ABILITIES)->orderBy('id')->paginate(10);
            $this->answers($this->ada, self::ABILITIES, $page->items());
        });

        $this->assertCount(2, $queries);
    }

    #[Test]
    public function it_decides_the_rows_of_an_eager_loaded_controlled_relation(): void
    {
        ConfiguredPostControl::bind($this->ownInSql(), SharedPerimeter::new()
            ->allowed(fn (User $user): bool => true)
            ->query(function (Builder $query, User $user): void {
                $query->whereHas('sharedWith', fn (Builder $users) => $users->whereKey($user->getKey()));
            }));
        $this->teamPost->sharedWith()->attach($this->ada);

        $user = User::query()
            ->with([
                'posts' => fn ($posts) => $posts->controlledFor($this->ada)->withAbilities(['update']),
                'sharedPosts' => fn ($posts) => $posts->controlledFor($this->ada)->withAbilities(['update']),
            ])
            ->findOrFail($this->ada->getKey());

        $loaded = [...$user->posts, ...$user->sharedPosts];
        $queries = $this->queriesWhile(function () use ($loaded, &$batched): void {
            $batched = $this->answers($this->ada, ['update'], $loaded);
        });

        $this->assertSame([], $queries);
        $this->assertSame($this->rowByRow($this->ada, ['update'], $loaded), $batched);
        $this->assertSame('granted', $batched[Post::class.'#'.$this->teamPost->getKey()]['update']);
        $this->assertCount(1, $user->sharedPosts);
        $this->assertArrayNotHasKey('__access_key', $user->sharedPosts->firstOrFail()->getAttributes());
    }

    #[Test]
    public function it_combines_the_batch_with_the_perimeters_checked_in_memory(): void
    {
        ConfiguredPostControl::bindWithin(new WorkspaceBoundary, $this->ownInMemory(), $this->clientInSql());
        $everyPost = $this->everyPost();
        Post::controlledFor($this->ada)->withAbilities(self::ABILITIES)->get();
        Access::preload($this->ada, 'view', $everyPost);

        $batched = $this->answers($this->ada, self::ABILITIES, $everyPost);

        $this->assertSame($this->rowByRow($this->ada, self::ABILITIES, $everyPost), $batched);
    }

    #[Test]
    public function it_files_the_batch_under_the_decision_context(): void
    {
        $brand = 1;
        Access::decisionContextUsing(function () use (&$brand): array {
            return ['brand' => $brand];
        });
        $this->bindRules();
        Post::controlledFor($this->ada)->withAbilities(['update'])->get();
        $brand = 2;

        $queries = $this->queriesWhile(function (): void {
            $this->ada->can('update', $this->teamPost);
        });

        $this->assertCount(1, $queries);
    }

    #[Test]
    public function it_decides_without_sql_an_unrestricted_level_and_memoizes_nothing_for_it(): void
    {
        ConfiguredPostControl::bindWithin(
            Boundary::where('workspace_id', fn (User $user): ?int => $user->current_workspace_id),
            GlobalPerimeter::new()->allowed(fn (User $user): bool => true)->unrestricted(),
        );

        $queries = $this->queriesWhile(function (): void {
            $posts = Post::controlledFor($this->ada)->withAbilities(['update'])->get();
            $this->answers($this->ada, ['update'], $posts);
        });

        $this->assertCount(1, $queries);
        $this->assertStringNotContainsString('case when', $queries[0]);
        $this->assertSame(0, $this->memo()->count());
    }

    protected function memoizedUpdate(User $reader, Post $post): ?bool
    {
        $queries = $this->queriesWhile(function () use ($reader, $post, &$allowed): void {
            $allowed = $reader->can('update', $post);
        });

        return $queries === [] ? $allowed : null;
    }
}
