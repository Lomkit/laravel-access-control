<?php

namespace Lomkit\Access\Tests\Feature\Decisions;

use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Exceptions\InvalidBatchRowException;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Blog\WorkspaceBoundary;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use PHPUnit\Framework\Attributes\Test;

class ReachableKeysTest extends BatchTestCase
{
    /** @return list<int|string> */
    protected function grantedRowByRow(User $reader, string $ability): array
    {
        Access::forgetDecisions();
        $keys = [];

        foreach ($this->everyPost() as $post) {
            if (Gate::forUser($reader)->allows($ability, $post)) {
                $keys[] = $post->getKey();
            }
        }

        return $keys;
    }

    #[Test]
    public function it_returns_the_keys_of_the_rows_the_reader_may_act_on_in_one_query(): void
    {
        $control = ConfiguredPostControl::bind($this->ownInSql(), $this->clientInSql());
        $everyPost = $this->everyPost();

        $queries = $this->queriesWhile(function () use ($control, $everyPost, &$keys): void {
            $keys = $control->reachableKeys($this->ada, Ability::update(), $everyPost);
        });

        $this->assertCount(1, $queries);
        $this->assertSame([$this->adaPost->getKey(), $this->teamPost->getKey()], $keys);
        $this->assertSame($this->grantedRowByRow($this->ada, 'update'), $keys);
    }

    #[Test]
    public function it_combines_the_batch_with_the_boundary_and_the_perimeters_checked_in_memory(): void
    {
        $control = ConfiguredPostControl::bindWithin(
            Boundary::where('workspace_id', fn (User $user): ?int => $user->current_workspace_id)->readOnlyWhenNotNull('archived_at'),
            $this->ownInMemory(),
            $this->clientInSql(),
        );
        $everyPost = $this->everyPost();

        $queries = $this->queriesWhile(function () use ($control, $everyPost, &$keys): void {
            $keys = $control->reachableKeys($this->ada, Ability::update(), $everyPost);
        });

        $this->assertCount(1, $queries);
        $this->assertSame([$this->adaPost->getKey(), $this->teamPost->getKey()], $keys);
        $this->assertSame($this->grantedRowByRow($this->ada, 'update'), $keys);
    }

    #[Test]
    public function it_keeps_the_rows_inside_a_boundary_without_memory_form(): void
    {
        $control = ConfiguredPostControl::bindWithin(new WorkspaceBoundary, $this->ownInSql());

        $keys = $control->reachableKeys($this->ada, Ability::update(), $this->everyPost());

        $this->assertSame([$this->adaPost->getKey()], $keys);
        $this->assertSame($this->grantedRowByRow($this->ada, 'update'), $keys);
    }

    #[Test]
    public function it_needs_one_query_even_for_a_reader_it_cannot_remember(): void
    {
        $control = ConfiguredPostControl::bind($this->ownInSql());
        $nobody = new User(['name' => 'Nobody', 'client_id' => $this->ada->client_id, 'current_workspace_id' => 1]);
        $everyPost = $this->everyPost();

        $queries = $this->queriesWhile(function () use ($control, $nobody, $everyPost, &$keys): void {
            $keys = $control->reachableKeys($nobody, Ability::update(), $everyPost);
        });

        $this->assertCount(1, $queries);
        $this->assertSame([], $keys);
        $this->assertSame(0, $this->memo()->count());
    }

    #[Test]
    public function it_leaves_out_the_unsaved_rows_and_each_key_once(): void
    {
        $control = ConfiguredPostControl::bind($this->ownInSql());

        $keys = $control->reachableKeys($this->ada, Ability::update(), [$this->adaPost, new Post(['author_id' => $this->ada->getKey()]), $this->adaPost]);

        $this->assertSame([$this->adaPost->getKey()], $keys);
    }

    #[Test]
    public function it_returns_no_key_without_sql_when_no_perimeter_applies(): void
    {
        $control = ConfiguredPostControl::bind($this->ownInSql($this->refusal()));
        $everyPost = $this->everyPost();

        $queries = $this->queriesWhile(function () use ($control, $everyPost, &$keys): void {
            $keys = $control->reachableKeys($this->ada, Ability::update(), $everyPost);
        });

        $this->assertSame([], $queries);
        $this->assertSame([], $keys);
    }

    #[Test]
    public function it_remembers_the_verdicts_for_the_gate(): void
    {
        $control = ConfiguredPostControl::bind($this->ownInSql());
        $control->reachableKeys($this->ada, Ability::update(), $this->everyPost());

        $queries = $this->queriesWhile(function (): void {
            $this->assertTrue($this->ada->can('update', $this->adaPost));
            $this->assertFalse($this->ada->can('update', $this->teamPost));
        });

        $this->assertSame([], $queries);
    }

    #[Test]
    public function it_refuses_rows_of_another_model(): void
    {
        $control = ConfiguredPostControl::bind($this->ownInSql());

        $this->expectException(InvalidBatchRowException::class);
        $this->expectExceptionMessage(sprintf('control decides rows of the [%s] model, but received a row of the [%s] model.', Post::class, User::class));

        $control->reachableKeys($this->ada, Ability::update(), [$this->adaPost, $this->grace]);
    }
}
