<?php

namespace Lomkit\Access\Tests\Feature\Decisions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Lomkit\Access\Ability;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Exceptions\EmptyQueryCallbackException;
use Lomkit\Access\Exceptions\JoinInPerimeterException;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\SharedPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Blog\WorkspaceBoundary;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use PHPUnit\Framework\Attributes\Test;

class SqlRowCheckTest extends DecisionTestCase
{
    #[Test]
    public function it_grants_a_row_through_the_query_of_a_perimeter_without_a_should_callback(): void
    {
        ConfiguredPostControl::bind($this->ownInSql());

        $queries = $this->queriesWhile(function (): void {
            $this->assertTrue($this->inspect($this->ada, 'update', $this->adaPost)->allowed());
        });

        $this->assertCount(1, $queries);
        $this->assertSame("select case when (((author_id = ?))) then 'granted' else 'outside_rows' end as access_verdict from posts where posts.id = ? limit 1", $queries[0]);
    }

    #[Test]
    public function it_refuses_a_row_outside_the_query_with_the_outside_rows_code(): void
    {
        ConfiguredPostControl::bind($this->ownInSql());

        $response = $this->inspect($this->ada, 'update', $this->teamPost);

        $this->assertTrue($response->denied());
        $this->assertSame('outside_rows', $response->code());
    }

    #[Test]
    public function it_checks_the_queries_of_every_reached_perimeter_in_one_query(): void
    {
        ConfiguredPostControl::bind($this->ownInSql(), $this->clientInSql(), $this->refusing(SharedPerimeter::new()->query(fn (Builder $query) => $query->whereRaw('1=1'))));

        $queries = $this->queriesWhile(function (): void {
            $this->assertTrue($this->inspect($this->ada, 'update', $this->adaPost)->allowed());
            $this->assertTrue($this->inspect($this->ada, 'update', $this->teamPost)->allowed());
            $this->assertSame('outside_rows', $this->inspect($this->ada, 'update', $this->otherClientPost)->code());
        });

        $this->assertCount(3, $queries);
        $this->assertStringContainsString('((author_id = ?) or (exists (select * from projects where posts.project_id = projects.id and client_id = ?)))', $queries[0]);
        $this->assertStringNotContainsString('1=1', $queries[0]);
    }

    #[Test]
    public function it_runs_the_same_query_on_every_decision(): void
    {
        ConfiguredPostControl::bind($this->ownInSql());

        $queries = $this->queriesWhile(function (): void {
            $this->ada->can('update', $this->adaPost);
            $this->ada->can('update', $this->adaPost);
        });

        $this->assertCount(2, $queries);
        $this->assertSame($queries[0], $queries[1]);
    }

    #[Test]
    public function it_refuses_without_sql_when_no_perimeter_applies(): void
    {
        ConfiguredPostControl::bind($this->refusing($this->ownInSql()));

        $queries = $this->queriesWhile(function (): void {
            $this->assertSame('no_perimeter', $this->inspect($this->ada, 'update', $this->adaPost)->code());
        });

        $this->assertSame([], $queries);
    }

    #[Test]
    public function it_refuses_an_unsaved_row_without_sql(): void
    {
        ConfiguredPostControl::bind($this->ownInSql());

        $queries = $this->queriesWhile(function (): void {
            $this->assertSame('unsaved', $this->inspect($this->ada, 'update', new Post(['author_id' => $this->ada->getKey()]))->code());
        });

        $this->assertSame([], $queries);
    }

    #[Test]
    public function it_never_grants_a_draft_through_a_perimeter_checked_in_sql(): void
    {
        ConfiguredPostControl::bind($this->ownInSql());
        $draft = new Post(['author_id' => $this->ada->getKey(), 'workspace_id' => 1]);

        $queries = $this->queriesWhile(function () use ($draft): void {
            $this->assertSame('outside_rows', $this->inspect($this->ada, 'create', [Post::class, $draft])->code());
        });

        $this->assertSame([], $queries);
    }

    #[Test]
    public function it_checks_an_in_memory_boundary_before_any_sql(): void
    {
        ConfiguredPostControl::bindWithin(Boundary::where('workspace_id', fn (User $user): ?int => $user->current_workspace_id), $this->ownInSql());

        $queries = $this->queriesWhile(function (): void {
            $this->assertSame('not_found', $this->inspect($this->ada, 'update', $this->elsewhere)->code());
        });

        $this->assertSame([], $queries);
    }

    #[Test]
    public function it_checks_a_boundary_without_memory_form_in_the_same_query_as_the_rows(): void
    {
        ConfiguredPostControl::bindWithin(new WorkspaceBoundary, $this->ownInSql());

        $queries = $this->queriesWhile(function (): void {
            $this->assertTrue($this->inspect($this->ada, 'view', $this->adaPost)->allowed());
            $this->assertSame('outside_rows', $this->inspect($this->ada, 'view', $this->teamPost)->code());
            $this->assertSame('not_found', $this->inspect($this->ada, 'view', $this->elsewhere)->code());
        });

        $this->assertCount(3, $queries);
        $this->assertSame("select case when ((workspace_id = ?)) then case when (((author_id = ?))) then 'granted' else 'outside_rows' end else 'outside_boundary' end as access_verdict from posts where posts.id = ? limit 1", $queries[0]);
    }

    #[Test]
    public function it_refuses_a_row_whose_boundary_is_null_in_sql(): void
    {
        ConfiguredPostControl::bindWithin(new WorkspaceBoundary, $this->ownInSql());
        $this->ada->forceFill(['current_workspace_id' => null]);

        $this->assertSame('not_found', $this->inspect($this->ada, 'view', $this->adaPost)->code());
    }

    #[Test]
    public function it_skips_the_sql_when_a_perimeter_checked_in_memory_grants(): void
    {
        ConfiguredPostControl::bind($this->ownInMemory(), $this->clientInSql());

        $queries = $this->queriesWhile(function (): void {
            $this->assertTrue($this->inspect($this->ada, 'update', $this->adaPost)->allowed());
        });

        $this->assertSame([], $queries);
    }

    #[Test]
    public function it_checks_in_sql_the_perimeters_without_memory_form_when_none_in_memory_grants(): void
    {
        ConfiguredPostControl::bind($this->ownInMemory(), $this->clientInSql());

        $queries = $this->queriesWhile(function (): void {
            $this->assertTrue($this->inspect($this->ada, 'update', $this->teamPost)->allowed());
            $this->assertSame('outside_rows', $this->inspect($this->ada, 'update', $this->otherClientPost)->code());
        });

        $this->assertCount(2, $queries);
        $this->assertStringNotContainsString('author_id', $queries[0]);
    }

    #[Test]
    public function it_refuses_without_sql_when_no_perimeter_in_memory_grants_and_none_is_checked_in_sql(): void
    {
        ConfiguredPostControl::bindWithin(Boundary::where('workspace_id', fn (User $user): ?int => $user->current_workspace_id), $this->ownInMemory());

        $queries = $this->queriesWhile(function (): void {
            $this->assertSame('outside_rows', $this->inspect($this->ada, 'update', $this->teamPost)->code());
        });

        $this->assertSame([], $queries);
    }

    #[Test]
    public function it_checks_only_the_boundary_in_sql_when_a_perimeter_checked_in_memory_grants(): void
    {
        ConfiguredPostControl::bindWithin(new WorkspaceBoundary, $this->ownInMemory(), $this->clientInSql());

        $queries = $this->queriesWhile(function (): void {
            $this->assertTrue($this->inspect($this->ada, 'view', $this->adaPost)->allowed());
            $this->assertSame('not_found', $this->inspect($this->ada, 'view', $this->elsewhere)->code());
        });

        $this->assertCount(2, $queries);
        $this->assertSame("select case when ((workspace_id = ?)) then 'granted' else 'outside_boundary' end as access_verdict from posts where posts.id = ? limit 1", $queries[0]);
    }

    #[Test]
    public function it_grants_an_unrestricted_level_without_sql_inside_an_in_memory_boundary_or_without_one(): void
    {
        $global = GlobalPerimeter::new()->allowed(fn (User $user): bool => true)->unrestricted();

        foreach ([Boundary::none(), Boundary::where('workspace_id', fn (User $user): ?int => $user->current_workspace_id)] as $boundary) {
            ConfiguredPostControl::bindWithin($boundary, Levels::of($global, $this->ownInSql()));

            $queries = $this->queriesWhile(function (): void {
                $this->assertTrue($this->inspect($this->ada, 'update', $this->teamPost)->allowed());
            });

            $this->assertSame([], $queries);
        }
    }

    #[Test]
    public function it_checks_only_the_highest_reached_level_in_sql(): void
    {
        ConfiguredPostControl::bind(Levels::of($this->refusing($this->clientInSql()), $this->ownInSql()));

        $this->assertTrue($this->ada->can('update', $this->adaPost));
        $this->assertFalse($this->ada->can('update', $this->teamPost));

        ConfiguredPostControl::bind(Levels::of($this->clientInSql(), $this->ownInSql()));

        $this->assertTrue($this->ada->can('update', $this->teamPost));
    }

    #[Test]
    public function it_refuses_a_row_deleted_since_it_was_loaded(): void
    {
        ConfiguredPostControl::bind($this->ownInSql());
        DB::table('posts')->where('id', $this->adaPost->getKey())->delete();

        $this->assertSame('outside_rows', $this->inspect($this->ada, 'update', $this->adaPost)->code());

        ConfiguredPostControl::bindWithin(new WorkspaceBoundary, $this->ownInSql());

        $this->assertSame('not_found', $this->inspect($this->ada, 'update', $this->adaPost)->code());
    }

    #[Test]
    public function it_decides_the_stored_row_in_sql_whatever_its_attributes_in_memory(): void
    {
        ConfiguredPostControl::bind($this->ownInSql());
        $this->teamPost->author_id = $this->ada->getKey();

        $this->assertFalse($this->ada->can('update', $this->teamPost));
    }

    #[Test]
    public function it_decides_through_applies_and_the_ability_of_a_business_method(): void
    {
        $control = ConfiguredPostControl::bind($this->ownInSql());

        $this->assertTrue($control->applies($this->ada, Ability::of('approve'), $this->adaPost));
        $this->assertFalse($control->applies($this->ada, Ability::of('approve'), $this->teamPost));
    }

    #[Test]
    public function it_passes_the_ability_to_the_query_callback_of_the_decision(): void
    {
        ConfiguredPostControl::bind(OwnPerimeter::new()
            ->allowed(fn (User $user): bool => true)
            ->query(function (Builder $query, User $user, Ability $ability): void {
                $query->where('author_id', $ability->is('view') ? $user->getKey() : 0);
            }));

        $this->assertTrue($this->ada->can('view', $this->adaPost));
        $this->assertFalse($this->ada->can('update', $this->adaPost));
    }

    #[Test]
    public function it_refuses_a_query_callback_that_adds_no_constraint_to_a_decision(): void
    {
        ConfiguredPostControl::bind(OwnPerimeter::new()->allowed(fn (User $user): bool => true)->query(fn (Builder $query) => $query));

        $this->expectException(EmptyQueryCallbackException::class);

        $this->ada->can('update', $this->teamPost);
    }

    #[Test]
    public function it_refuses_a_query_callback_that_joins_in_a_decision(): void
    {
        ConfiguredPostControl::bind(OwnPerimeter::new()->allowed(fn (User $user): bool => true)->query(function (Builder $query): void {
            $query->join('projects', 'projects.id', '=', 'posts.project_id')->where('projects.client_id', 1);
        }));

        $this->expectException(JoinInPerimeterException::class);

        $this->ada->can('update', $this->teamPost);
    }

    #[Test]
    public function it_checks_in_sql_a_row_of_a_perimeter_class_that_overrides_scope_but_not_includes(): void
    {
        $perimeter = new class extends Perimeter
        {
            #[\Override]
            protected function allows(AccessContext $context): bool
            {
                return true;
            }

            #[\Override]
            protected function scope(Builder $query, AccessContext $context): void
            {
                $query->where('author_id', $context->reader()->getKey());
            }
        };
        ConfiguredPostControl::bind($perimeter);

        $queries = $this->queriesWhile(function () use ($perimeter): void {
            $this->assertFalse($perimeter->checksInMemory());
            $this->assertTrue($this->ada->can('update', $this->adaPost));
            $this->assertFalse($this->ada->can('update', $this->teamPost));
        });

        $this->assertCount(2, $queries);
    }
}
