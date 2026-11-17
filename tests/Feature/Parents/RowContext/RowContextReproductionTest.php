<?php

namespace Lomkit\Access\Tests\Feature\Parents\RowContext;

use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\Tests\Fixtures\Blog\Comment;
use Lomkit\Access\Tests\Fixtures\Inheritance\Controls\CommentControl;
use PHPUnit\Framework\Attributes\Test;

class RowContextReproductionTest extends RowContextTestCase
{
    #[Test]
    public function it_refuses_a_comment_of_one_brand_on_a_post_whose_control_reads_the_requested_brands_and_opens_it_only_in_another(): void
    {
        $sister = $this->commentNamed('Sister brand');
        $comments = Comment::query()->orderBy('id')->get();

        $this->assertSame(['Wrong workspace'], $this->visibleComments($this->grace));
        $this->assertSame('outside_rows', $this->code($this->grace, 'view', $sister));
        $this->assertSame([], Comment::controlledFor($this->grace)->withAbilities(['view'])->whereKey($sister->getKey())->get()->all());
        $this->assertNotContains($sister->getKey(), Access::controlFor(Comment::class)->reachableKeys($this->grace, Ability::view(), $comments));

        Access::forgetDecisions();
        Access::preload($this->grace, 'view', $comments);

        $this->assertFalse($this->grace->can('view', $sister));
        $this->assertControlAgrees(CommentControl::class, $this->grace, $comments, ['view']);
    }

    #[Test]
    public function it_refuses_a_comment_of_another_brand_on_a_post_whose_control_opens_it_only_in_its_own_brand(): void
    {
        $this->assertSame(['Ship it', 'Typo in the title', 'Add search', 'Old news', 'Wrong workspace', 'Too expensive'], $this->visibleComments($this->ada));
        $this->assertSame('outside_rows', $this->code($this->ada, 'view', $this->commentNamed('Agreed')));
        $this->assertSame('outside_rows', $this->code($this->ada, 'view', $this->commentNamed('Sister brand')));
        $this->assertControlAgrees(CommentControl::class, $this->ada, Comment::query()->orderBy('id')->get(), ['view']);
    }
}
