<?php

namespace Lomkit\Access\Tests\Feature\Parents\RowContext;

use Lomkit\Access\Access;
use Lomkit\Access\Tests\Fixtures\Blog\Comment;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use PHPUnit\Framework\Attributes\Test;

class RowContextMemoTest extends RowContextTestCase
{
    #[Test]
    public function it_evaluates_the_parent_once_per_brand_and_keeps_it_under_that_brand(): void
    {
        $comments = Comment::query()->orderBy('id')->get();
        Access::forgetDecisions();
        $this->postAllowedCalls = [];
        $this->parentAllowedCalls = [];

        foreach ($comments as $comment) {
            $this->ada->can('view', $comment);
        }

        $this->assertSame(['Ada:view:1', 'Ada:view:2'], $this->postAllowedCalls);
        $this->assertSame(['Ada:view:1', 'Ada:view:2'], $this->parentAllowedCalls);

        $this->brands->request([2]);

        $this->assertSame(['Elsewhere'], Post::controlledFor($this->ada)->pluck('title')->all());
        $this->assertSame(['Wrong workspace'], $this->visibleCommentsKeepingDecisions());
        $this->assertSame(['Ada:view:1', 'Ada:view:2'], $this->postAllowedCalls);
        $this->assertSame(['Ada:view:1', 'Ada:view:2'], $this->parentAllowedCalls);
    }

    #[Test]
    public function it_never_reuses_the_decision_of_the_child_kept_under_the_request_for_another_context(): void
    {
        $sister = $this->commentNamed('Sister brand');

        $this->assertFalse($this->grace->can('view', $sister));
        $this->brands->request([2]);

        $this->assertSame('not_found', $this->code($this->grace, 'view', $sister));

        $this->brands->request([1]);

        $this->assertFalse($this->grace->can('view', $sister));

        $this->brands->request([1, 2]);

        $this->assertFalse($this->grace->can('view', $sister));
    }

    #[Test]
    public function it_switches_once_per_value_for_the_fingerprint_of_a_request_whatever_the_number_of_rows(): void
    {
        $comments = Comment::query()->orderBy('id')->get();
        Access::forgetDecisions();
        $this->switches = 0;

        Access::preload($this->ada, 'view', $comments);
        $preload = $this->switches;

        foreach ($comments as $comment) {
            $this->ada->can('view', $comment);
        }

        $this->assertSame($preload, $this->switches);
        $this->assertLessThanOrEqual(6, $preload);
    }

    /** @return list<string> */
    private function visibleCommentsKeepingDecisions(): array
    {
        return Comment::controlledFor($this->ada)
            ->orderBy('id')
            ->pluck('body')
            ->all();
    }
}
