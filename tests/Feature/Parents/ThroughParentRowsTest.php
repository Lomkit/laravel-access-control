<?php

namespace Lomkit\Access\Tests\Feature\Parents;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Ability;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Fixtures\Blog\Comment;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredCommentControl;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use PHPUnit\Framework\Attributes\Test;

class ThroughParentRowsTest extends ParentTestCase
{
    protected function sqlOf(User $reader): string
    {
        return str_replace(['"', '`'], '', Comment::controlledFor($reader)->toSql());
    }

    #[Test]
    public function it_lists_the_comments_of_the_posts_the_reader_may_view(): void
    {
        $this->grant(['view own posts' => ['Ada']]);

        $this->assertSame(['Ship it', 'Typo in the title', 'Old news'], $this->visibleComments($this->ada));
        $this->assertSame([], $this->visibleComments($this->grace));
    }

    #[Test]
    public function it_adds_up_with_the_other_perimeters_of_the_child(): void
    {
        $this->grant(['view own posts' => ['Ada'], 'view own comments' => ['Ada']]);

        $this->assertSame(['Ship it', 'Typo in the title', 'Add search', 'Old news', 'Too expensive'], $this->visibleComments($this->ada));
    }

    #[Test]
    public function it_checks_the_parent_in_an_exists_subquery_restricted_by_the_control_of_the_parent(): void
    {
        $this->grant(['view own posts' => ['Ada']]);

        $this->assertStringContainsString(
            'where (((exists (select * from posts where comments.post_id = posts.id and ((posts.workspace_id = ?) and ((posts.author_id in (?))))))))',
            $this->sqlOf($this->ada),
        );
    }

    #[Test]
    public function it_applies_the_boundary_of_the_parent(): void
    {
        $this->grant(['view global posts' => ['Root']]);

        $this->assertSame(['Wrong workspace'], $this->visibleComments($this->root));
    }

    #[Test]
    public function it_applies_the_boundary_of_the_child_as_well(): void
    {
        ConfiguredCommentControl::bindWithin(
            Boundary::where('workspace_id', fn (User $user): ?int => $user->current_workspace_id),
            ThroughParent::belongsTo('post'),
        );
        $this->grant(['view global posts' => ['Linus']]);

        $this->assertSame(['Ship it', 'Typo in the title', 'Add search', 'Old news', 'Too expensive'], $this->visibleComments($this->linus));
    }

    #[Test]
    public function it_opens_every_child_of_a_parent_the_reader_reaches_without_restriction(): void
    {
        ConfiguredPostControl::bindWithin(Boundary::none(), GlobalPerimeter::new()
            ->allowed(fn (User $user): bool => $user->is($this->grace))
            ->unrestricted());

        $this->assertCount(7, $this->visibleComments($this->grace));
        $this->assertStringContainsString('exists (select * from posts where comments.post_id = posts.id)', $this->sqlOf($this->grace));
        $this->assertSame([], $this->visibleComments($this->ada));
    }

    #[Test]
    public function it_follows_the_levels_of_the_parent(): void
    {
        $this->grant(['view client posts' => ['Linus'], 'view own posts' => ['Linus']]);

        $this->assertSame(['Too expensive', 'Agreed'], $this->visibleComments($this->linus));
        $this->assertStringContainsString('exists (select * from projects where posts.project_id = projects.id and projects.client_id in (?))', $this->sqlOf($this->linus));
        $this->assertStringNotContainsString('author_id', $this->sqlOf($this->linus));
    }

    #[Test]
    public function it_lists_no_comment_for_a_guest(): void
    {
        $this->grant(['view global posts' => ['Root'], 'view own comments' => ['Root']]);

        $this->assertSame([], $this->visibleComments(null));
    }

    #[Test]
    public function it_lists_no_comment_when_the_control_of_the_parent_reaches_no_perimeter(): void
    {
        $this->grant(['update own posts' => ['Ada']]);

        $this->assertSame([], $this->visibleComments($this->ada));
        $this->assertStringNotContainsString('exists', $this->sqlOf($this->ada));
    }

    #[Test]
    public function it_delegates_only_view_by_default(): void
    {
        $this->grant(['view own posts' => ['Ada'], 'update own posts' => ['Ada'], 'delete own posts' => ['Ada']]);

        $this->assertSame(['Ship it', 'Typo in the title', 'Old news'], $this->bodiesFor($this->ada, 'view'));
        $this->assertSame([], $this->bodiesFor($this->ada, 'update'));
        $this->assertSame([], $this->bodiesFor($this->ada, 'delete'));
    }

    #[Test]
    public function it_delegates_the_listed_abilities_to_the_same_ability_of_the_parent(): void
    {
        ConfiguredCommentControl::bind(ThroughParent::belongsTo('post')->delegates(['view', 'update']));
        $this->grant(['view own posts' => ['Ada'], 'update own posts' => ['Ada'], 'delete own posts' => ['Ada']]);

        $this->assertSame(['Ship it', 'Typo in the title', 'Old news'], $this->bodiesFor($this->ada, 'view'));
        $this->assertSame(['Ship it', 'Typo in the title'], $this->bodiesFor($this->ada, 'update'));
        $this->assertSame([], $this->bodiesFor($this->ada, 'delete'));
    }

    #[Test]
    public function it_maps_a_child_ability_to_another_ability_of_the_parent(): void
    {
        ConfiguredCommentControl::bind(ThroughParent::belongsTo('post')->delegates(['delete' => 'update', 'restore' => Ability::view()]));
        $this->grant(['view own posts' => ['Ada'], 'update own posts' => ['Ada']]);

        $this->assertSame(['Ship it', 'Typo in the title'], $this->bodiesFor($this->ada, 'delete'));
        $this->assertSame(['Ship it', 'Typo in the title', 'Old news'], $this->bodiesFor($this->ada, 'restore'));
        $this->assertSame([], $this->bodiesFor($this->ada, 'view'));
        $this->assertSame([], $this->bodiesFor($this->ada, 'update'));
    }

    #[Test]
    public function it_adds_up_two_parents(): void
    {
        ConfiguredCommentControl::bind(ThroughParent::belongsTo('post'), ThroughParent::belongsTo('author'));
        $this->grant(['view own posts' => ['Grace'], 'view own users' => ['Grace']]);

        $this->assertSame(['Ship it', 'Add search', 'Old news', 'Wrong workspace'], $this->visibleComments($this->grace));
    }

    #[Test]
    public function it_inherits_through_nested_parents(): void
    {
        ConfiguredPostControl::bindWithin(Boundary::none(), ThroughParent::belongsTo('project'));
        $this->grant(['view client projects' => ['Linus']]);

        $this->assertSame(['Too expensive', 'Agreed'], $this->visibleComments($this->linus));
        $this->assertStringContainsString(
            'exists (select * from posts where comments.post_id = posts.id and (((exists (select * from projects where posts.project_id = projects.id and (((client_id = ?))))))))',
            $this->sqlOf($this->linus),
        );
    }

    #[Test]
    public function it_stands_as_a_level_of_a_group(): void
    {
        ConfiguredCommentControl::bind(Levels::of(
            GlobalPerimeter::new()
                ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} global comments"))
                ->unrestricted(),
            ThroughParent::belongsTo('post'),
        ));
        $this->grant(['view global comments' => ['Grace'], 'view own posts' => ['Ada', 'Grace']]);

        $this->assertCount(7, $this->visibleComments($this->grace));
        $this->assertSame(['Ship it', 'Typo in the title', 'Old news'], $this->visibleComments($this->ada));
    }

    #[Test]
    public function it_keeps_the_constraints_of_the_application_query(): void
    {
        $this->grant(['view own posts' => ['Ada']]);

        $bodies = Comment::controlledFor($this->ada)
            ->where(fn (Builder $query) => $query->where('author_id', $this->linus->getKey())->orWhere('author_id', $this->ada->getKey()))
            ->pluck('body')
            ->all();

        $this->assertSame(['Typo in the title'], $bodies);
    }
}
