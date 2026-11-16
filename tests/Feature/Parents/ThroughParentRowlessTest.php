<?php

namespace Lomkit\Access\Tests\Feature\Parents;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Fixtures\Blog\Comment;
use Lomkit\Access\Tests\Fixtures\Blog\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredCommentControl;
use PHPUnit\Framework\Attributes\Test;

class ThroughParentRowlessTest extends ParentTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->grant([
            'view own posts' => ['Ada'],
            'view comments' => ['Ada', 'Grace', 'Root'],
            'create comments' => ['Ada', 'Grace'],
        ]);
        ConfiguredCommentControl::bind($this->throughPost());
    }

    protected function throughPost(): ThroughParent
    {
        return ThroughParent::belongsTo('post')
            ->delegates(['view', 'create' => 'view'])
            ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} comments"));
    }

    protected function ownComments(): OwnPerimeter
    {
        return OwnPerimeter::new()
            ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} own comments"))
            ->query(fn (Builder $query, User $user): Builder => $query->where('author_id', $user->getKey()));
    }

    protected function code(User $reader, string $ability, mixed $subject): string
    {
        $response = Gate::forUser($reader)->inspect($ability, $subject);

        return $response->allowed() ? 'granted' : (string) $response->code();
    }

    #[Test]
    public function it_refuses_view_any_with_no_perimeter_without_access_to_the_parent_and_grants_it_with_access(): void
    {
        $this->assertSame('no_perimeter', $this->code($this->grace, 'viewAny', Comment::class));
        $this->assertSame('granted', $this->code($this->ada, 'viewAny', Comment::class));

        $this->grant(['view own posts' => ['Ada', 'Grace']]);
        Access::forgetDecisions();

        $this->assertSame('granted', $this->code($this->grace, 'viewAny', Comment::class));
    }

    #[Test]
    public function it_refuses_create_without_a_draft_with_no_perimeter_without_access_to_the_parent(): void
    {
        $this->assertSame('no_perimeter', $this->code($this->grace, 'create', Comment::class));
        $this->assertSame('no_perimeter', $this->code($this->grace, 'create', [Comment::class, $this->draftOn($this->roadmap, $this->grace)]));
        $this->assertSame('granted', $this->code($this->ada, 'create', Comment::class));

        ConfiguredCommentControl::bind($this->throughPost(), $this->ownComments());
        $this->grant(['create own comments' => ['Grace']]);

        $this->assertSame('granted', $this->code($this->grace, 'create', Comment::class));
        $this->assertSame('outside_rows', $this->code($this->grace, 'create', [Comment::class, $this->draftOn($this->roadmap, $this->grace)]));
    }

    #[Test]
    public function it_lists_no_row_without_access_to_the_parent(): void
    {
        $this->assertSame([], $this->visibleComments($this->grace));
        $this->assertSame('select * from comments where (0=1)', str_replace(['"', '`'], '', Comment::controlledFor($this->grace)->toSql()));
        $this->assertSame(['Ship it', 'Typo in the title', 'Old news'], $this->visibleComments($this->ada));
    }

    #[Test]
    public function it_does_not_reach_a_parent_perimeter_without_allowed_for_a_reader_without_access_to_the_parent(): void
    {
        ConfiguredCommentControl::bind(ThroughParent::belongsTo('post')->delegates(['view', 'create' => 'view']));

        $this->assertSame('no_perimeter', $this->code($this->linus, 'viewAny', Comment::class));
        $this->assertSame('no_perimeter', $this->code($this->linus, 'create', Comment::class));
        $this->assertSame([], $this->visibleComments($this->linus));
        $this->assertSame('granted', $this->code($this->ada, 'viewAny', Comment::class));
    }

    #[Test]
    public function it_grants_view_any_and_lists_nothing_for_a_reader_who_reaches_the_parent_without_rows(): void
    {
        $this->grant(['view own posts' => ['Ada', 'Root']]);

        $this->assertSame('granted', $this->code($this->root, 'viewAny', Comment::class));
        $this->assertSame([], $this->visibleComments($this->root));
    }

    #[Test]
    public function it_refuses_view_any_with_no_perimeter_when_the_boundary_of_the_parent_does_not_admit_the_reader(): void
    {
        $this->grant(['view own posts' => ['Ada', 'Grace']]);

        $this->assertSame('granted', $this->code($this->grace, 'viewAny', Comment::class));

        $this->grace->forceFill(['current_workspace_id' => null])->save();

        $this->assertSame('no_perimeter', $this->code($this->grace, 'viewAny', Comment::class));
        $this->assertSame('no_perimeter', $this->code($this->grace, 'create', Comment::class));
        $this->assertSame([], $this->visibleComments($this->grace));
        $this->assertSame('granted', $this->code($this->ada, 'viewAny', Comment::class));
    }

    #[Test]
    public function it_keeps_the_own_rows_of_a_lower_level_without_access_to_the_parent(): void
    {
        ConfiguredCommentControl::bind(Levels::of($this->throughPost(), $this->ownComments()));
        $this->grant(['view own comments' => ['Ada', 'Grace']]);

        $this->assertSame('granted', $this->code($this->grace, 'viewAny', Comment::class));
        $this->assertSame(['Ship it', 'Old news', 'Wrong workspace'], $this->visibleComments($this->grace));
        $this->assertSame(['Ship it', 'Typo in the title', 'Old news'], $this->visibleComments($this->ada));
    }
}
