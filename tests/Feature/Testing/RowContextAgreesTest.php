<?php

namespace Lomkit\Access\Tests\Feature\Testing;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Exceptions\RowContextSwitchException;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Feature\Parents\RowContext\RowContextTestCase;
use Lomkit\Access\Tests\Fixtures\Blog\Comment;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredCommentControl;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use Lomkit\Access\Tests\Fixtures\Inheritance\Controls\CommentControl;
use Lomkit\Access\Tests\Fixtures\RowContext\RequestedBrandsBoundary;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\Test;

class RowContextAgreesTest extends RowContextTestCase
{
    /** @var list<int> */
    private array $team = [1, 2];

    #[Test]
    public function it_passes_when_each_row_gets_the_answer_of_a_request_of_its_own_brand(): void
    {
        foreach ([$this->ada, $this->grace, $this->linus] as $reader) {
            $this->assertRowContextAgrees(CommentControl::class, $reader, Comment::query()->orderBy('id')->get(), alone: $this->aloneIn(...));
        }
    }

    #[Test]
    public function it_fails_when_the_switch_forgets_a_state_that_the_parent_reads(): void
    {
        Access::decisionContextUsing(fn (): array => [...$this->brands->decisionContext(), 'team' => implode(',', $this->team)]);
        ConfiguredPostControl::bindWithin(Boundary::none(), GlobalPerimeter::new()
            ->allowed(fn (User $user): bool => $this->teamRights($user) !== [])
            ->query(function (Builder $query, User $user): void {
                $query->whereIn('workspace_id', $this->teamRights($user));
            }));
        $alone = function (int|string $brand, Closure $check): mixed {
            $team = $this->team;
            $this->team = [(int) $brand];

            try {
                return $this->aloneIn($brand, $check);
            } finally {
                $this->team = $team;
            }
        };

        $this->assertTrue($this->grace->can('view', $this->commentNamed('Sister brand')));
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage(sprintf('- row [%s], ability [view]: [granted] under the request, [refused] in a request of [1] alone', $this->commentNamed('Sister brand')->getKey()));

        $this->assertRowContextAgrees(CommentControl::class, $this->grace, Comment::query()->orderBy('id')->get(), alone: $alone);
    }

    #[Test]
    public function it_fails_when_a_perimeter_of_the_child_reads_the_request_instead_of_the_row(): void
    {
        $this->rights['Grace']['view own comments'] = [2];
        ConfiguredCommentControl::bindWithin(new RequestedBrandsBoundary, $this->throughPost(), OwnPerimeter::new()
            ->allowed(fn (User $user, Ability $ability): bool => $this->brandsWhere($user, "{$ability} own comments") !== [])
            ->query(fn (Builder $query, User $user): Builder => $query->where('author_id', $user->getKey())));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage(sprintf('- row [%s], ability [view]: [granted] under the request, [refused] in a request of [1] alone', $this->commentNamed('Ship it')->getKey()));

        $this->assertRowContextAgrees(CommentControl::class, $this->grace, Comment::query()->orderBy('id')->get(), alone: $this->aloneIn(...));
    }

    #[Test]
    public function it_fails_when_the_alone_closure_does_not_decide(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('[granted] under the request, [not decided: the alone closure did not call its callback] in a request of [1] alone');

        $this->assertRowContextAgrees(CommentControl::class, $this->ada, [$this->commentNamed('Ship it')], alone: fn (int|string $brand, Closure $check): mixed => null);
    }

    #[Test]
    public function it_throws_when_the_values_stay_those_of_the_request_within_a_value(): void
    {
        Access::rowContextUsing(
            values: fn (): array => [1, 2],
            within: fn (int|string $brand, Closure $decide): mixed => $this->brands->actingIn([(int) $brand], $decide),
        );

        $this->expectException(RowContextSwitchException::class);
        $this->expectExceptionMessage('called the decide callback for the value [1] while the values closure returned [1, 2].');

        $this->assertRowContextAgrees(CommentControl::class, $this->ada, Comment::query()->orderBy('id')->get(), alone: $this->aloneIn(...));
    }

    #[Test]
    public function it_fails_without_a_row_of_a_value_of_the_request(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('assertRowContextAgrees() received no saved ['.Comment::class.'] row whose [workspace_id] is one of the values of the request');

        $this->assertRowContextAgrees(CommentControl::class, $this->ada, [$this->commentNamed('No brand')], alone: $this->aloneIn(...));
    }

    #[Test]
    public function it_fails_on_a_control_without_a_through_parent_decided_in_the_row_context(): void
    {
        ConfiguredCommentControl::bind(ThroughParent::belongsTo('post'));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('assertRowContextAgrees() needs exactly one column decidedInRowContext() on the ThroughParent perimeters of the ['.CommentControl::class.'] control, found [].');

        $this->assertRowContextAgrees(CommentControl::class, $this->ada, Comment::query()->get(), alone: $this->aloneIn(...));
    }

    /** @return list<int> */
    private function teamRights(User $user): array
    {
        return array_values(array_intersect($this->rightsOf($user, 'view brand posts'), $this->team));
    }
}
