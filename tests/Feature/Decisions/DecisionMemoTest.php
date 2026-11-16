<?php

namespace Lomkit\Access\Tests\Feature\Decisions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Decisions\DecisionKey;
use Lomkit\Access\Decisions\DecisionMemo;
use Lomkit\Access\Decisions\Verdict;
use Lomkit\Access\Decisions\VerdictRecorder;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\Editor;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Blog\WorkspaceBoundary;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use PHPUnit\Framework\Attributes\Test;

class DecisionMemoTest extends DecisionTestCase
{
    #[Test]
    public function it_answers_a_memoized_verdict_instead_of_the_sql(): void
    {
        ConfiguredPostControl::bind($this->ownInSql());
        $this->record($this->ada, 'update', $this->teamPost, Verdict::Granted);
        $this->record($this->ada, 'update', $this->adaPost, Verdict::OutsideRows);

        $queries = $this->queriesWhile(function (): void {
            $this->assertTrue($this->ada->can('update', $this->teamPost));
            $this->assertSame('outside_rows', $this->inspect($this->ada, 'update', $this->adaPost)->code());
        });

        $this->assertSame([], $queries);
    }

    #[Test]
    public function it_files_each_verdict_under_its_ability(): void
    {
        ConfiguredPostControl::bind($this->ownInSql());
        $this->record($this->ada, 'update', $this->teamPost, Verdict::Granted);

        $this->assertFalse($this->ada->can('delete', $this->teamPost));
        $this->assertFalse($this->ada->can('approve', $this->teamPost));
    }

    #[Test]
    public function it_keeps_the_not_found_logic_for_a_memoized_outside_boundary(): void
    {
        ConfiguredPostControl::bindWithin(new WorkspaceBoundary, $this->ownInSql());
        $this->record($this->ada, 'update', $this->adaPost, Verdict::OutsideBoundary);
        $this->record($this->ada, 'update', $this->elsewhere, Verdict::OutsideBoundary);

        $this->assertSame('outside_boundary', $this->inspect($this->ada, 'update', $this->adaPost)->code());
        $this->assertSame('not_found', $this->inspect($this->ada, 'update', $this->elsewhere)->code());
    }

    #[Test]
    public function it_still_checks_an_in_memory_boundary_before_the_memo(): void
    {
        ConfiguredPostControl::bindWithin(Boundary::where('workspace_id', fn (User $user): ?int => $user->current_workspace_id), $this->ownInSql());
        $this->record($this->ada, 'update', $this->elsewhere, Verdict::Granted);

        $this->assertSame('not_found', $this->inspect($this->ada, 'update', $this->elsewhere)->code());
    }

    #[Test]
    public function it_refuses_a_reader_that_no_perimeter_allows_any_more_after_a_flush(): void
    {
        $allowed = true;
        ConfiguredPostControl::bind($this->ownInSql(function (User $user) use (&$allowed): bool {
            return $allowed;
        }));
        $this->record($this->ada, 'update', $this->teamPost, Verdict::Granted);
        $allowed = false;
        Access::forgetDecisions();

        $this->assertSame('no_perimeter', $this->inspect($this->ada, 'update', $this->teamPost)->code());
    }

    #[Test]
    public function it_checks_in_sql_again_when_the_reader_reaches_other_perimeters_after_a_flush(): void
    {
        $client = true;
        ConfiguredPostControl::bind(Levels::of(
            $this->clientInSql(function (User $user) use (&$client): bool {
                return $client;
            }),
            $this->ownInSql(),
        ));
        $this->record($this->ada, 'update', $this->teamPost, Verdict::Granted);
        $client = false;
        Access::forgetDecisions();

        $queries = $this->queriesWhile(function (): void {
            $this->assertFalse($this->ada->can('update', $this->teamPost));
        });

        $this->assertCount(1, $queries);
    }

    #[Test]
    public function it_grants_through_a_memory_perimeter_with_the_memoized_answer_of_the_boundary(): void
    {
        ConfiguredPostControl::bindWithin(new WorkspaceBoundary, $this->ownInMemory(), $this->clientInSql());
        $this->record($this->ada, 'update', $this->adaPost, Verdict::OutsideRows);
        $this->record($this->ada, 'update', $this->elsewhere, Verdict::OutsideBoundary);

        $queries = $this->queriesWhile(function (): void {
            $this->assertTrue($this->ada->can('update', $this->adaPost));
            $this->assertFalse($this->ada->can('update', $this->elsewhere));
        });

        $this->assertCount(1, $queries);
        $this->assertStringStartsWith('select exists(select * from posts where posts.id = ?', $queries[0]);
    }

    #[Test]
    public function it_never_shares_a_verdict_between_two_readers(): void
    {
        ConfiguredPostControl::bind($this->ownInSql());
        $this->record($this->ada, 'update', $this->otherClientPost, Verdict::Granted);

        $this->assertTrue($this->ada->can('update', $this->otherClientPost));
        $this->assertFalse($this->grace->can('update', $this->otherClientPost));
    }

    #[Test]
    public function it_never_shares_a_verdict_between_readers_of_two_morph_classes_with_the_same_key(): void
    {
        ConfiguredPostControl::bind($this->ownInSql());
        $editor = (new Editor)->newFromBuilder($this->grace->getAttributes());
        $this->record($this->grace, 'update', $this->adaPost, Verdict::Granted);

        $this->assertSame($this->grace->getKey(), $editor->getKey());
        $this->assertTrue($this->grace->can('update', $this->adaPost));
        $this->assertFalse($editor->can('update', $this->adaPost));
    }

    #[Test]
    public function it_never_memoizes_a_reader_without_a_key_or_that_is_not_saved(): void
    {
        ConfiguredPostControl::bind($this->ownInSql());

        foreach ([new User(['name' => 'Nobody']), new User(['id' => $this->ada->getKey(), 'name' => 'Ada'])] as $reader) {
            $this->record($reader, 'update', $this->teamPost, Verdict::Granted);

            $this->assertSame(0, $this->memo()->count());
            $this->assertFalse(Access::controlFor(Post::class)->applies($reader, Ability::update(), $this->teamPost));
        }
    }

    #[Test]
    public function it_memoizes_only_the_verdicts_of_the_sql_check(): void
    {
        ConfiguredPostControl::bind($this->ownInSql());

        foreach ([Verdict::NoPerimeter, Verdict::Unsaved] as $verdict) {
            $this->record($this->ada, 'update', $this->teamPost, $verdict);
        }

        $this->assertSame(0, $this->memo()->count());
    }

    #[Test]
    public function it_memoizes_nothing_for_a_check_that_needs_no_sql(): void
    {
        ConfiguredPostControl::bind($this->ownInMemory());
        $this->record($this->ada, 'update', $this->teamPost, Verdict::Granted);

        ConfiguredPostControl::bind($this->ownInSql($this->refusal()));
        $this->record($this->ada, 'update', $this->teamPost, Verdict::Granted);

        $this->assertSame(0, $this->memo()->count());
    }

    #[Test]
    public function it_forgets_every_decision_when_a_model_is_saved(): void
    {
        ConfiguredPostControl::bind($this->ownInSql());
        $this->record($this->ada, 'update', $this->teamPost, Verdict::Granted);

        Client::query()->firstOrFail()->update(['name' => 'Acme Corp']);

        $this->assertSame(0, $this->memo()->count());
        $this->assertFalse($this->ada->can('update', $this->teamPost));
    }

    #[Test]
    public function it_forgets_every_decision_when_a_model_is_deleted(): void
    {
        ConfiguredPostControl::bind($this->ownInSql());
        $this->record($this->ada, 'update', $this->teamPost, Verdict::Granted);

        $this->elsewhere->delete();

        $this->assertSame(0, $this->memo()->count());
    }

    #[Test]
    public function it_forgets_every_decision_when_a_transaction_rolls_back(): void
    {
        ConfiguredPostControl::bind($this->ownInSql());

        DB::beginTransaction();
        $this->record($this->ada, 'update', $this->teamPost, Verdict::Granted);
        DB::rollBack();

        $this->assertSame(0, $this->memo()->count());
    }

    #[Test]
    public function it_forgets_every_decision_on_demand(): void
    {
        ConfiguredPostControl::bind($this->ownInSql());
        $this->record($this->ada, 'update', $this->teamPost, Verdict::Granted);

        Access::forgetDecisions();

        $this->assertSame(0, $this->memo()->count());
    }

    #[Test]
    public function it_starts_each_request_or_job_with_an_empty_memo(): void
    {
        ConfiguredPostControl::bind($this->ownInSql());
        $this->record($this->ada, 'update', $this->teamPost, Verdict::Granted);
        $memo = $this->memo();

        $this->app->forgetScopedInstances();

        $this->assertNotSame($memo, $this->memo());
        $this->assertSame(0, $this->memo()->count());
        $this->assertFalse($this->ada->can('update', $this->teamPost));
    }

    #[Test]
    public function it_forgets_every_decision_when_a_request_is_handled(): void
    {
        ConfiguredPostControl::bind($this->ownInSql());
        Route::get('/decisions', fn (): string => 'decided');
        $this->assertTrue($this->ada->can('update', $this->adaPost));
        $this->record($this->ada, 'update', $this->teamPost, Verdict::Granted);

        $this->get('/decisions')->assertOk();

        $this->assertSame(0, $this->memo()->count());
        $this->assertSame(0, $this->memo()->reachCount());
    }

    #[Test]
    public function it_keeps_the_decisions_through_writes_that_send_no_eloquent_event(): void
    {
        ConfiguredPostControl::bind($this->ownInSql());
        $this->record($this->ada, 'update', $this->teamPost, Verdict::Granted);

        $this->teamPost->forceFill(['title' => 'Quiet'])->saveQuietly();
        Client::withoutEvents(fn () => Client::query()->firstOrFail()->update(['name' => 'Silent']));
        DB::table('posts')->update(['title' => 'Mass update']);

        $this->assertSame(1, $this->memo()->count());
    }

    #[Test]
    public function it_drops_the_oldest_decision_beyond_its_cap(): void
    {
        $control = ConfiguredPostControl::bind($this->ownInSql());
        $memo = $this->memo();
        $recorder = VerdictRecorder::for($control, $this->ada, Ability::update(), $memo);

        foreach (range(1, DecisionMemo::MAX_ENTRIES + 1) as $key) {
            $recorder->record(Post::class, $key, Verdict::Granted);
        }

        $context = AccessContext::of($this->ada, Ability::update());
        $this->assertSame(DecisionMemo::MAX_ENTRIES, $memo->count());
        $this->assertNull($memo->recall($this->keyOf($context, 1)));
        $this->assertSame(Verdict::Granted, $memo->recall($this->keyOf($context, 2)));
        $this->assertSame(Verdict::Granted, $memo->recall($this->keyOf($context, DecisionMemo::MAX_ENTRIES + 1)));
    }

    protected function keyOf(AccessContext $context, int $row): DecisionKey
    {
        return DecisionKey::of($context, Post::class, $row, 'rows:#0') ?? $this->fail('The decision key is missing.');
    }
}
