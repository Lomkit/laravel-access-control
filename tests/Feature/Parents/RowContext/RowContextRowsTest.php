<?php

namespace Lomkit\Access\Tests\Feature\Parents\RowContext;

use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Fixtures\Blog\Comment;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredCommentControl;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use Lomkit\Access\Tests\Fixtures\Inheritance\Controls\CommentControl;
use Lomkit\Access\Tests\Fixtures\RowContext\RequestedBrandsBoundary;
use PHPUnit\Framework\Attributes\Test;

class RowContextRowsTest extends RowContextTestCase
{
    #[Test]
    public function it_lists_the_comments_of_each_brand_through_the_posts_the_reader_may_view_in_that_brand(): void
    {
        $this->assertSame(['Ship it', 'Typo in the title', 'Add search', 'Old news', 'Wrong workspace', 'Too expensive'], $this->visibleComments($this->ada));
        $this->assertSame(['Wrong workspace'], $this->visibleComments($this->grace));
        $this->assertSame('granted', $this->code($this->grace, 'view', $this->commentNamed('Wrong workspace')));
        $this->assertSame('granted', $this->code($this->ada, 'view', $this->commentNamed('Ship it')));

        foreach ([$this->ada, $this->grace, $this->linus] as $reader) {
            $this->assertControlAgrees(CommentControl::class, $reader, Comment::query()->orderBy('id')->get(), ['view']);
        }
    }

    #[Test]
    public function it_builds_one_branch_per_value_with_the_restriction_of_the_parent_frozen_inside_the_switch(): void
    {
        $query = Comment::controlledFor($this->ada);
        $sql = str_replace(['"', '`'], '', $query->toSql());

        $this->assertSame(2, substr_count($sql, 'exists (select * from posts'));
        $this->assertSame(2, substr_count($sql, 'comments.workspace_id = ?'));
        $this->assertSame([1, 2, 1, 1, 2, 2], $query->getBindings());
        $this->assertSame([1, 2], $this->brands->all());
    }

    #[Test]
    public function it_refuses_a_row_without_a_value_or_with_a_value_outside_the_request(): void
    {
        ConfiguredCommentControl::bindWithin(Boundary::none(), $this->throughPost());
        $this->comment($this->releaseNotes, $this->grace, 'Third brand', 3);

        $this->assertNotContains('No brand', $this->visibleComments($this->ada));
        $this->assertNotContains('Third brand', $this->visibleComments($this->ada));
        $this->assertSame('outside_rows', $this->code($this->ada, 'view', $this->commentNamed('No brand')));
        $this->assertSame('outside_rows', $this->code($this->ada, 'view', $this->commentNamed('Third brand')));
        $this->assertControlAgrees(CommentControl::class, $this->ada, Comment::query()->orderBy('id')->get(), ['view']);
    }

    #[Test]
    public function it_reaches_nothing_when_the_request_covers_no_value(): void
    {
        $this->brands->request([]);
        ConfiguredCommentControl::bindWithin(Boundary::none(), $this->throughPost());

        $this->assertSame([], $this->visibleComments($this->ada));
        $this->assertSame('no_perimeter', $this->code($this->ada, 'viewAny', Comment::class));
        $this->assertSame('no_perimeter', $this->code($this->ada, 'view', $this->commentNamed('Ship it')));
    }

    #[Test]
    public function it_drops_the_branch_where_the_allowed_of_the_through_parent_refuses(): void
    {
        $this->rights['Ada']['view comments'] = [2];

        $this->assertSame(['Wrong workspace'], $this->visibleComments($this->ada));
        $this->assertSame('outside_rows', $this->code($this->ada, 'view', $this->commentNamed('Ship it')));
        $this->assertControlAgrees(CommentControl::class, $this->ada, Comment::query()->orderBy('id')->get(), ['view']);
    }

    #[Test]
    public function it_gives_the_same_answers_with_the_same_queries_as_without_it_under_a_single_brand_with_a_child_boundary_on_the_requested_brands(): void
    {
        $this->brands->request([1]);
        $comments = Comment::query()->orderBy('id')->get();

        $plain = $this->answersOf($this->plainThroughPost(), $comments->all());
        $branched = $this->answersOf($this->throughPost(), $comments->all());

        $this->assertSame($plain, $branched);
        $this->assertSame(['Ship it', 'Typo in the title', 'Add search', 'Old news', 'Too expensive'], $branched['Ada']['listed']);
    }

    #[Test]
    public function it_changes_nothing_for_a_parent_whose_control_reads_the_brand_of_its_row_with_a_child_boundary_on_the_requested_brands(): void
    {
        ConfiguredPostControl::bindWithin(Boundary::none(), $this->postsOfTheRowBrand());
        $comments = Comment::query()->orderBy('id')->get();

        $plain = $this->answersOf($this->throughPostOfTheRowBrand(), $comments->all());
        $branched = $this->answersOf($this->throughPostOfTheRowBrand()->decidedInRowContext('workspace_id'), $comments->all());

        $this->assertSame($plain, $branched);
        $this->assertContains('Sister brand', $branched['Grace']['listed']);
    }

    private function throughPostOfTheRowBrand(): ThroughParent
    {
        return ThroughParent::belongsTo('post', inverse: 'comments')
            ->delegates(['view', 'create' => 'view'])
            ->allowed(fn (User $user, Ability $ability): bool => $this->rightsOf($user, "{$ability} comments") !== []);
    }

    #[Test]
    public function it_no_longer_reaches_a_child_outside_the_requested_brands_without_a_child_boundary_on_them(): void
    {
        $this->brands->request([1]);

        ConfiguredCommentControl::bindWithin(Boundary::none(), $this->plainThroughPost());
        $this->assertContains('Agreed', $this->visibleComments($this->ada));
        $this->assertContains('No brand', $this->visibleComments($this->ada));

        ConfiguredCommentControl::bindWithin(Boundary::none(), $this->throughPost());
        $this->assertNotContains('Agreed', $this->visibleComments($this->ada));
        $this->assertNotContains('No brand', $this->visibleComments($this->ada));
    }

    /**
     * @param  list<Comment>  $comments
     * @return array<string, array{listed: list<string>, codes: list<string>, queries: int}>
     */
    private function answersOf(ThroughParent $throughPost, array $comments): array
    {
        ConfiguredCommentControl::bindWithin(new RequestedBrandsBoundary, $throughPost);
        $answers = [];

        foreach ([$this->ada, $this->grace, $this->linus] as $reader) {
            $listed = [];
            $codes = [];
            $queries = $this->queriesWhile(function () use ($reader, $comments, &$listed, &$codes): void {
                $listed = $this->visibleComments($reader);
                Access::preload($reader, 'view', $comments);
                $codes = array_map(fn (Comment $comment): string => $this->code($reader, 'view', $comment), $comments);
                $codes[] = $this->code($reader, 'viewAny', Comment::class);
                $codes[] = Gate::forUser($reader)->allows('create', Comment::class) ? 'create' : 'no create';
                $codes[] = implode(',', Access::controlFor(Comment::class)->reachableKeys($reader, Ability::view(), $comments));
            });
            $answers[$reader->name] = ['listed' => $listed, 'codes' => $codes, 'queries' => count($queries)];
        }

        return $answers;
    }
}
