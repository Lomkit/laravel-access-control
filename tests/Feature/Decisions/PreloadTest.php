<?php

namespace Lomkit\Access\Tests\Feature\Decisions;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Decisions\VerdictBatch;
use Lomkit\Access\Exceptions\InvalidAbilityException;
use Lomkit\Access\Exceptions\InvalidBatchRowException;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Blog\WorkspaceBoundary;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class PreloadTest extends BatchTestCase
{
    protected const array ABILITIES = ['view', 'update', 'delete', 'approve'];

    /** @return array<string, array{Closure(self): void}> */
    public static function ruleShapes(): array
    {
        return [
            'one perimeter in SQL' => [fn (self $test) => ConfiguredPostControl::bind($test->ownInSql())],
            'perimeters in SQL that add up' => [fn (self $test) => ConfiguredPostControl::bind($test->ownInSql(), $test->clientInSql())],
            'levels in SQL' => [fn (self $test) => ConfiguredPostControl::bind(Levels::of($test->clientInSql(), $test->ownInSql()))],
            'memory and SQL perimeters' => [fn (self $test) => ConfiguredPostControl::bind($test->ownInMemory(), $test->clientInSql())],
            'in-memory boundary with a read-only column' => [fn (self $test) => ConfiguredPostControl::bindWithin(
                Boundary::where('workspace_id', fn (User $user): ?int => $user->current_workspace_id)->readOnlyWhenNotNull('archived_at'),
                Levels::of($test->clientInSql(), $test->ownInSql()),
            )],
            'boundary without memory form' => [fn (self $test) => ConfiguredPostControl::bindWithin(new WorkspaceBoundary, $test->ownInSql(), $test->clientInSql())],
            'boundary without memory form and memory perimeters' => [fn (self $test) => ConfiguredPostControl::bindWithin(new WorkspaceBoundary, $test->ownInMemory(), $test->clientInSql())],
            'unrestricted level inside a boundary without memory form' => [fn (self $test) => ConfiguredPostControl::bindWithin(
                new WorkspaceBoundary,
                Levels::of(GlobalPerimeter::new()->allowed(fn (User $user, Ability $ability): bool => $ability->is('view'))->unrestricted(), $test->ownInSql()),
            )],
            'no perimeter for some abilities' => [fn (self $test) => ConfiguredPostControl::bind($test->ownInSql()->allowed(fn (User $user, Ability $ability): bool => ! $ability->is('delete')))],
        ];
    }

    #[Test]
    #[DataProvider('ruleShapes')]
    public function it_gives_the_same_answers_as_the_row_by_row_decisions(Closure $rules): void
    {
        $rules($this);

        foreach ([$this->ada, $this->grace] as $reader) {
            $everyPost = $this->everyPost();
            Access::forgetDecisions();

            foreach (self::ABILITIES as $ability) {
                Access::preload($reader, $ability, $everyPost);
            }

            $queries = $this->queriesWhile(function () use ($reader, $everyPost, &$batched): void {
                $batched = $this->answers($reader, self::ABILITIES, $everyPost);
            });

            $this->assertSame([], $queries);
            $this->assertSame($this->rowByRow($reader, self::ABILITIES, $everyPost), $batched);
        }
    }

    #[Test]
    public function it_runs_one_query_per_ability(): void
    {
        ConfiguredPostControl::bind($this->ownInSql(), $this->clientInSql());
        $everyPost = $this->everyPost();

        $queries = $this->queriesWhile(function () use ($everyPost): void {
            Access::preload($this->ada, 'update', $everyPost);
            Access::preload($this->ada, Ability::delete(), $everyPost);
        });

        $this->assertCount(2, $queries);
        $this->assertStringContainsString('where posts.id in (', $queries[0]);
    }

    #[Test]
    public function it_splits_a_large_collection_in_chunks(): void
    {
        ConfiguredPostControl::bind($this->ownInSql());
        $rows = [];

        foreach (range(1, VerdictBatch::CHUNK + 1) as $key) {
            $rows[] = (new Post)->newFromBuilder(['id' => $key]);
        }

        $queries = $this->queriesWhile(function () use ($rows): void {
            Access::preload($this->ada, 'update', $rows);
        });

        $this->assertCount(2, $queries);
    }

    #[Test]
    public function it_groups_the_rows_of_several_models(): void
    {
        ConfiguredPostControl::bind($this->ownInSql());
        Gate::define('update client users', fn (User $user): bool => true);
        $users = User::query()->get()->all();
        $rows = [...$this->everyPost(), ...$users];

        $queries = $this->queriesWhile(function () use ($rows): void {
            Access::preload($this->ada, 'update', $rows);
        });

        $this->assertCount(1, $queries);
        $this->assertStringContainsString('from posts', $queries[0]);
        $this->assertSame($this->rowByRow($this->ada, ['update'], $this->everyPost()), $this->answers($this->ada, ['update'], $this->everyPost()));
    }

    #[Test]
    public function it_skips_the_unsaved_rows(): void
    {
        ConfiguredPostControl::bind($this->ownInSql());

        $queries = $this->queriesWhile(function (): void {
            Access::preload($this->ada, 'update', [new Post(['author_id' => $this->ada->getKey()])]);
        });

        $this->assertSame([], $queries);
        $this->assertSame(0, $this->memo()->count());
    }

    #[Test]
    public function it_runs_no_query_for_a_reader_it_cannot_remember(): void
    {
        ConfiguredPostControl::bind($this->ownInSql());
        $everyPost = $this->everyPost();

        $queries = $this->queriesWhile(function () use ($everyPost): void {
            Access::preload(new User(['name' => 'Nobody']), 'update', $everyPost);
        });

        $this->assertSame([], $queries);
    }

    #[Test]
    public function it_runs_no_query_when_no_perimeter_applies_or_no_sql_is_needed(): void
    {
        ConfiguredPostControl::bind($this->refusing($this->ownInSql()));
        $everyPost = $this->everyPost();

        $queries = $this->queriesWhile(function () use ($everyPost): void {
            Access::preload($this->ada, 'update', $everyPost);
            ConfiguredPostControl::bind($this->ownInMemory());
            Access::preload($this->ada, 'update', $everyPost);
        });

        $this->assertSame([], $queries);
    }

    #[Test]
    public function it_refuses_a_value_that_is_not_a_model(): void
    {
        ConfiguredPostControl::bind($this->ownInSql());

        $this->expectException(InvalidBatchRowException::class);
        $this->expectExceptionMessage('A batch of decisions received a value of type [int]. Pass the Eloquent models to decide');

        Access::preload($this->ada, 'update', [$this->adaPost, 7]);
    }

    #[Test]
    public function it_refuses_an_ability_the_control_cannot_map(): void
    {
        ConfiguredPostControl::bind($this->ownInSql());

        $this->expectException(InvalidAbilityException::class);

        Access::preload($this->ada, '', $this->everyPost());
    }

    #[Test]
    public function it_files_a_verdict_only_for_the_rows_still_in_the_database(): void
    {
        ConfiguredPostControl::bind($this->ownInSql());
        $everyPost = $this->everyPost();
        DB::table('posts')->where('id', $this->adaPost->getKey())->delete();

        Access::preload($this->ada, 'update', $everyPost);

        $this->assertSame(count($everyPost) - 1, $this->memo()->count());
        $this->assertSame('outside_rows', $this->inspect($this->ada, 'update', $this->adaPost)->code());
    }
}
