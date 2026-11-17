<?php

namespace Lomkit\Access\Tests\Feature\Parents\RowContext;

use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Tests\Fixtures\Blog\Comment;
use Lomkit\Access\Tests\Fixtures\ConfiguredCommentControl;
use PHPUnit\Framework\Attributes\Test;

class RowContextDraftTest extends RowContextTestCase
{
    private function draftCode(Comment $draft): string
    {
        return $this->code($this->grace, 'create', [Comment::class, $draft]);
    }

    #[Test]
    public function it_refuses_a_draft_of_a_brand_where_the_reader_may_not_view_the_parent_without_loading_it(): void
    {
        $draft = $this->draftIn($this->elsewhere, $this->grace, 1);

        $queries = $this->queriesWhile(fn () => $this->assertSame('outside_rows', $this->draftCode($draft)));

        $this->assertSame([], $queries);
    }

    #[Test]
    public function it_grants_a_draft_of_the_brand_where_the_reader_may_view_the_parent(): void
    {
        $draft = $this->draftIn($this->elsewhere, $this->grace, 2);

        $queries = $this->queriesWhile(fn () => $this->assertSame('granted', $this->draftCode($draft)));

        $this->assertCount(1, $queries);
    }

    #[Test]
    public function it_refuses_a_draft_without_a_value_or_with_a_value_outside_the_request_without_a_query(): void
    {
        ConfiguredCommentControl::bindWithin(Boundary::none(), $this->throughPost());

        foreach ([null, 3] as $brand) {
            $draft = $this->draftIn($this->elsewhere, $this->grace, $brand);

            $queries = $this->queriesWhile(fn () => $this->assertSame('outside_rows', $this->draftCode($draft)));

            $this->assertSame([], $queries);
        }
    }

    #[Test]
    public function it_reads_a_value_given_as_a_string_like_the_stored_one(): void
    {
        $draft = $this->draftIn($this->elsewhere, $this->grace, null);
        $draft->setAttribute('workspace_id', '2');
        ConfiguredCommentControl::bindWithin(Boundary::none(), $this->throughPost());

        $this->assertSame('granted', $this->draftCode($draft));
    }
}
