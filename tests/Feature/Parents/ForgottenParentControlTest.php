<?php

namespace Lomkit\Access\Tests\Feature\Parents;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\Exceptions\MissingParentControlException;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Fixtures\Blog\Comment;
use Lomkit\Access\Tests\Fixtures\Blog\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredCommentControl;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use PHPUnit\Framework\Attributes\Test;

class ForgottenParentControlTest extends ParentTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->discoverControls(['Controls']);
        $this->grant(['view own posts' => ['Ada'], 'view client projects' => ['Ada']]);
    }

    protected function assertRefusesLoudly(Closure $path): void
    {
        try {
            $result = $path();

            $this->fail(sprintf('A path answered [%s] instead of refusing.', get_debug_type($result)));
        } catch (MissingParentControlException $exception) {
            $this->assertStringContainsString('so no child row would ever be granted', $exception->getMessage());
        }
    }

    #[Test]
    public function it_shows_the_hand_written_rule_that_opens_every_comment_when_the_control_of_the_parent_is_forgotten(): void
    {
        ConfiguredCommentControl::bind(OwnPerimeter::new()
            ->allowed(fn (User $user): bool => true)
            ->query(fn (Builder $query): Builder => $query->whereHas('post')));

        $this->assertCount(7, $this->visibleComments($this->grace));
    }

    #[Test]
    public function it_refuses_every_comment_of_a_reader_the_parent_refuses(): void
    {
        ConfiguredCommentControl::bind(ThroughParent::belongsTo('post'));

        $this->assertSame([], $this->visibleComments($this->grace));
        $this->assertSame([], $this->bodiesFor($this->grace, 'view'));
    }

    #[Test]
    public function it_throws_on_every_path_rather_than_open_a_child_whose_parent_has_no_control(): void
    {
        ConfiguredPostControl::bind(ThroughParent::belongsTo('project'));
        $comments = Comment::uncontrolled()->get();
        $paths = [
            fn () => Comment::controlledFor($this->ada)->get(),
            fn () => Comment::controlledFor($this->ada)->withAbilities(['view'])->get(),
            fn () => Gate::forUser($this->ada)->inspect('view', $comments->firstOrFail()),
            fn () => Gate::forUser($this->ada)->inspect('create', [Comment::class, $this->draftOn($this->releaseNotes, $this->ada)]),
            fn () => Access::preload($this->ada, 'view', $comments),
            fn () => Access::controlFor(Comment::class)->reachableKeys($this->ada, Ability::view(), $comments),
        ];

        foreach ($paths as $path) {
            $this->assertRefusesLoudly($path);
        }
    }
}
