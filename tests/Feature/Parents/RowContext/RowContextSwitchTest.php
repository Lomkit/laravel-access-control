<?php

namespace Lomkit\Access\Tests\Feature\Parents\RowContext;

use Closure;
use Lomkit\Access\Access;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Decisions\RowContextSwitch;
use Lomkit\Access\Exceptions\InvalidRowContextValuesException;
use Lomkit\Access\Exceptions\MissingRowContextException;
use Lomkit\Access\Exceptions\RowContextSwitchException;
use Lomkit\Access\Tests\Fixtures\Blog\Comment;
use Lomkit\Access\Tests\Fixtures\ConfiguredCommentControl;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use Lomkit\Access\Tests\Fixtures\Inheritance\Controls\CommentControl;
use Lomkit\Access\Tests\Fixtures\RowContext\RequestedBrandsBoundary;
use PHPUnit\Framework\Attributes\Test;

class RowContextSwitchTest extends RowContextTestCase
{
    private function switchUsing(Closure $within): void
    {
        Access::rowContextUsing(values: fn (): array => $this->brands->all(), within: $within);
    }

    #[Test]
    public function it_throws_before_any_query_when_the_application_declares_no_row_context(): void
    {
        $this->app->forgetInstance(RowContextSwitch::class);

        $queries = $this->queriesWhile(function (): void {
            try {
                ConfiguredCommentControl::bindWithin(new RequestedBrandsBoundary, $this->throughPost());
                Access::control(CommentControl::class);
                $this->fail('The control was built without a row context.');
            } catch (MissingRowContextException $exception) {
                $this->assertSame(sprintf("The ThroughParent perimeter of the [%s] control through the [post] relation is decidedInRowContext('workspace_id'), but the application declares no row context. Call Access::rowContextUsing(values: ..., within: ...) in the boot() method of a service provider: values returns the values of the request, such as its brands, and within runs a callback while the application acts in one of them.", ConfiguredCommentControl::class), $exception->getMessage());
            }
        });

        $this->assertSame([], $queries);
    }

    #[Test]
    public function it_throws_when_the_values_are_not_a_list(): void
    {
        Access::rowContextUsing(values: fn (): array => ['brand' => 1], within: fn (int|string $brand, Closure $decide): mixed => $decide());

        $this->expectException(InvalidRowContextValuesException::class);
        $this->expectExceptionMessage('The values closure given to Access::rowContextUsing() returned a value of type [array with keys].');

        $this->visibleComments($this->ada);
    }

    #[Test]
    public function it_throws_when_a_value_is_not_an_int_or_a_string(): void
    {
        Access::rowContextUsing(values: fn (): array => [1, 2.5], within: fn (int|string $brand, Closure $decide): mixed => $decide());

        $this->expectException(InvalidRowContextValuesException::class);
        $this->expectExceptionMessage('returned a value of type [float] at position 1.');

        $this->visibleComments($this->ada);
    }

    #[Test]
    public function it_throws_when_the_switch_does_not_decide(): void
    {
        $this->switchUsing(fn (int|string $brand, Closure $decide): mixed => null);

        $this->expectException(RowContextSwitchException::class);
        $this->expectExceptionMessage('returned without calling the decide callback for the value [1].');

        $this->visibleComments($this->ada);
    }

    #[Test]
    public function it_throws_when_the_switch_decides_twice(): void
    {
        $this->switchUsing(fn (int|string $brand, Closure $decide): mixed => $this->brands->actingIn([(int) $brand], function () use ($decide): mixed {
            $decide();

            return $decide();
        }));

        $this->expectException(RowContextSwitchException::class);
        $this->expectExceptionMessage('called the decide callback more than once for the value [1].');

        $this->visibleComments($this->ada);
    }

    #[Test]
    public function it_throws_when_the_switch_leaves_the_context_of_the_value(): void
    {
        $this->switchUsing(function (int|string $brand, Closure $decide): mixed {
            $this->brands->request([(int) $brand]);

            return $decide();
        });

        $this->expectException(RowContextSwitchException::class);
        $this->expectExceptionMessage('left the decision context on {"brands":"1"} after deciding for the value [1], instead of restoring {"brands":"1,2"}.');

        $this->visibleComments($this->ada);
    }

    #[Test]
    public function it_throws_when_the_switch_leaves_the_decision_context_of_several_values(): void
    {
        $this->switchUsing(fn (int|string $brand, Closure $decide): mixed => $decide());

        $this->expectException(RowContextSwitchException::class);
        $this->expectExceptionMessage('called the decide callback for the value [1] while the decision context was still {"brands":"1,2"}, the context of the whole request.');

        $this->visibleComments($this->ada);
    }

    #[Test]
    public function it_accepts_a_switch_without_effect_for_a_single_value(): void
    {
        $this->brands->request([1]);
        $this->switchUsing(fn (int|string $brand, Closure $decide): mixed => $decide());

        $this->assertSame(['Ship it', 'Typo in the title', 'Add search', 'Old news', 'Too expensive'], $this->visibleComments($this->ada));
    }

    #[Test]
    public function it_throws_from_every_path_and_restores_the_state_of_the_application(): void
    {
        $this->switchUsing(fn (int|string $brand, Closure $decide): mixed => $decide());
        ConfiguredPostControl::bindWithin(Boundary::none(), $this->postsOfTheRequestedBrands());
        $comment = $this->commentNamed('Ship it');

        foreach ([fn () => $this->ada->can('view', $comment), fn () => $this->ada->can('viewAny', Comment::class), fn () => $this->ada->can('create', [Comment::class, $this->draftIn($this->releaseNotes, $this->ada, 1)])] as $decision) {
            try {
                $decision();
                $this->fail('The decision ran with a switch without effect.');
            } catch (RowContextSwitchException) {
                $this->assertSame([1, 2], $this->brands->all());
            }
        }
    }
}
