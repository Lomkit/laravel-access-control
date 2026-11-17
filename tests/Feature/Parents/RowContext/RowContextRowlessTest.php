<?php

namespace Lomkit\Access\Tests\Feature\Parents\RowContext;

use Lomkit\Access\Tests\Fixtures\Blog\Comment;
use Lomkit\Access\Tests\Fixtures\ConfiguredCommentControl;
use Lomkit\Access\Tests\Fixtures\RowContext\RequestedBrandsBoundary;
use PHPUnit\Framework\Attributes\Test;

class RowContextRowlessTest extends RowContextTestCase
{
    #[Test]
    public function it_refuses_a_reader_who_holds_the_child_in_one_brand_and_the_parent_in_another(): void
    {
        $this->assertSame('no_perimeter', $this->code($this->linus, 'viewAny', Comment::class));
        $this->assertSame('no_perimeter', $this->code($this->linus, 'create', Comment::class));
        $this->assertSame([], $this->visibleComments($this->linus));
    }

    #[Test]
    public function it_granted_that_reader_before_deciding_in_the_row_context(): void
    {
        ConfiguredCommentControl::bindWithin(new RequestedBrandsBoundary, $this->plainThroughPost());

        $this->assertSame('granted', $this->code($this->linus, 'viewAny', Comment::class));
        $this->assertSame(['Wrong workspace', 'Sister brand'], $this->visibleComments($this->linus));
    }

    #[Test]
    public function it_grants_a_reader_who_holds_the_child_and_the_parent_in_one_brand(): void
    {
        $this->assertSame('granted', $this->code($this->grace, 'viewAny', Comment::class));
        $this->assertSame('granted', $this->code($this->grace, 'create', Comment::class));
    }

    #[Test]
    public function it_refuses_when_the_allowed_of_the_through_parent_refuses_in_every_brand_where_the_parent_is_reached(): void
    {
        $this->rights['Grace']['view comments'] = [1];

        $this->assertSame('no_perimeter', $this->code($this->grace, 'viewAny', Comment::class));
    }
}
