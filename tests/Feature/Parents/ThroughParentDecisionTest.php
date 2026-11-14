<?php

namespace Lomkit\Access\Tests\Feature\Parents;

use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\Decisions\Draft;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Fixtures\Blog\Comment;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredCommentControl;
use PHPUnit\Framework\Attributes\Test;

class ThroughParentDecisionTest extends ParentTestCase
{
    protected function code(?User $reader, string $ability, mixed $subject): string
    {
        $response = Gate::forUser($reader)->inspect($ability, $subject);

        return $response->allowed() ? 'granted' : (string) $response->code();
    }

    protected function draftCode(User $reader, Comment $draft): string
    {
        return $this->code($reader, 'create', [Comment::class, $draft]);
    }

    protected function delegatingCreate(): void
    {
        ConfiguredCommentControl::bind(ThroughParent::belongsTo('post')->delegates(['view', 'create' => 'view', 'update']));
    }

    #[Test]
    public function it_grants_a_comment_of_a_post_the_reader_may_view_in_one_query(): void
    {
        $this->grant(['view own posts' => ['Ada']]);
        $comment = $this->commentNamed('Ship it');

        $queries = $this->queriesWhile(fn () => $this->assertSame('granted', $this->code($this->ada, 'view', $comment)));

        $this->assertCount(1, $queries);
        $this->assertStringContainsString('exists (select * from', str_replace(['"', '`'], '', $queries[0]));
    }

    #[Test]
    public function it_refuses_a_comment_of_a_post_the_reader_may_not_view(): void
    {
        $this->grant(['view own posts' => ['Ada']]);

        $this->assertSame('outside_rows', $this->code($this->ada, 'view', $this->commentNamed('Add search')));
        $this->assertSame('outside_rows', $this->code($this->ada, 'view', $this->commentNamed('Wrong workspace')));
        $this->assertSame('no_perimeter', $this->code($this->grace, 'view', $this->commentNamed('Ship it')));
        $this->assertFalse(Gate::forUser(null)->allows('view', $this->commentNamed('Ship it')));
    }

    #[Test]
    public function it_refuses_an_ability_it_does_not_delegate_without_a_query(): void
    {
        $this->grant(['view own posts' => ['Ada'], 'update own posts' => ['Ada']]);
        $comment = $this->commentNamed('Ship it');

        $queries = $this->queriesWhile(fn () => $this->assertSame('no_perimeter', $this->code($this->ada, 'update', $comment)));

        $this->assertSame([], $queries);
    }

    #[Test]
    public function it_never_keeps_a_grant_once_the_parent_no_longer_reaches_the_row(): void
    {
        $this->grant(['view client posts' => ['Ada'], 'view own posts' => ['Ada']]);
        $comment = $this->commentNamed('Add search');
        Access::preload($this->ada, 'view', [$comment]);
        $this->assertSame('granted', $this->code($this->ada, 'view', $comment));

        $this->grant(['view client posts' => []]);

        $this->assertSame('outside_rows', $this->code($this->ada, 'view', $comment));
    }

    #[Test]
    public function it_decides_again_once_the_parent_reaches_more_rows(): void
    {
        $this->grant(['view client posts' => [], 'view own posts' => ['Ada']]);
        $comment = $this->commentNamed('Add search');
        Access::preload($this->ada, 'view', [$comment]);
        $this->assertSame('outside_rows', $this->code($this->ada, 'view', $comment));

        $this->grant(['view client posts' => ['Ada']]);

        $this->assertSame('granted', $this->code($this->ada, 'view', $comment));
    }

    #[Test]
    public function it_refuses_to_create_a_comment_when_create_is_not_delegated(): void
    {
        $this->grant(['view own posts' => ['Ada']]);

        $this->assertSame('no_perimeter', $this->draftCode($this->ada, $this->draftOn($this->releaseNotes, $this->ada)));
    }

    #[Test]
    public function it_lets_a_reader_create_a_comment_on_a_post_they_may_view_with_one_query(): void
    {
        $this->delegatingCreate();
        $this->grant(['view own posts' => ['Ada']]);
        $draft = $this->draftOn($this->releaseNotes, $this->ada);

        $queries = $this->queriesWhile(fn () => $this->assertSame('granted', $this->draftCode($this->ada, $draft)));

        $this->assertCount(1, $queries);
        $this->assertStringStartsWith('select * from posts where posts.id = ?', str_replace(['"', '`'], '', $queries[0]));
        $this->assertSame('granted', $this->draftCode($this->ada, $this->draftOn($this->archive, $this->ada)));
    }

    #[Test]
    public function it_refuses_to_create_a_comment_on_a_post_the_reader_may_not_view(): void
    {
        $this->delegatingCreate();
        $this->grant(['view own posts' => ['Ada']]);

        $this->assertSame('outside_rows', $this->draftCode($this->ada, $this->draftOn($this->roadmap, $this->ada)));
        $this->assertSame('outside_rows', $this->draftCode($this->ada, $this->draftOn($this->elsewhere, $this->ada)));
    }

    #[Test]
    public function it_refuses_a_draft_without_a_parent_key_without_a_query(): void
    {
        $this->delegatingCreate();
        $this->grant(['view own posts' => ['Ada']]);
        $draft = $this->draftOn(null, $this->ada);

        $queries = $this->queriesWhile(fn () => $this->assertSame('outside_rows', $this->draftCode($this->ada, $draft)));

        $this->assertSame([], $queries);
    }

    #[Test]
    public function it_refuses_a_draft_whose_parent_does_not_exist(): void
    {
        $this->delegatingCreate();
        $this->grant(['view global posts' => ['Root']]);
        $draft = $this->draftOn(null, $this->root);
        $draft->setAttribute('post_id', 999);

        $this->assertSame('outside_rows', $this->draftCode($this->root, $draft));
    }

    #[Test]
    public function it_judges_the_stored_parent_of_the_foreign_key_rather_than_a_loaded_relation(): void
    {
        $this->delegatingCreate();
        $this->grant(['view own posts' => ['Ada']]);
        $draft = $this->draftOn($this->roadmap, $this->ada);
        $draft->setRelation('post', $this->releaseNotes);
        $roadmap = Post::query()->findOrFail($this->roadmap->getKey());
        $roadmap->setAttribute('author_id', $this->ada->getKey());

        $this->assertSame('outside_rows', $this->draftCode($this->ada, $draft));
    }

    #[Test]
    public function it_grants_no_child_of_a_parent_that_a_global_scope_hides(): void
    {
        $this->delegatingCreate();
        $this->grant(['view own posts' => ['Ada']]);
        $this->releaseNotes->update(['published' => false]);
        $shipIt = $this->commentNamed('Ship it');
        Post::addGlobalScope('published', fn ($query) => $query->where('published', true));

        try {
            $this->assertSame(['Old news'], $this->visibleComments($this->ada));
            $this->assertSame('outside_rows', $this->code($this->ada, 'view', $shipIt));
            $this->assertSame('outside_rows', $this->draftCode($this->ada, $this->draftOn($this->releaseNotes, $this->ada)));
            $this->assertSame('granted', $this->draftCode($this->ada, $this->draftOn($this->archive, $this->ada)));
        } finally {
            Post::clearBootedModels();
        }
    }

    #[Test]
    public function it_decides_a_draft_with_the_mapped_ability_of_the_parent(): void
    {
        ConfiguredCommentControl::bind(ThroughParent::belongsTo('post')->delegates(['create' => 'update']));
        $this->grant(['view own posts' => ['Ada'], 'update own posts' => ['Ada']]);

        $this->assertSame('granted', $this->draftCode($this->ada, $this->draftOn($this->releaseNotes, $this->ada)));
        $this->assertSame('outside_rows', $this->draftCode($this->ada, $this->draftOn($this->archive, $this->ada)));
    }

    #[Test]
    public function it_decides_a_draft_through_the_api_of_the_control(): void
    {
        $this->delegatingCreate();
        $this->grant(['view own posts' => ['Ada']]);
        $control = Access::controlFor(Comment::class);

        $this->assertTrue($control->applies($this->ada, Ability::create(), Draft::of($this->draftOn($this->releaseNotes, $this->ada))));
        $this->assertFalse($control->applies($this->ada, Ability::create(), Draft::of($this->draftOn($this->roadmap, $this->ada))));
        $this->assertTrue($control->applies($this->ada, Ability::create()));
    }

    #[Test]
    public function it_admits_a_stored_row_in_sql_through_the_perimeter(): void
    {
        $this->grant(['view own posts' => ['Ada']]);
        $perimeter = ThroughParent::belongsTo('post')->boundTo(Access::controlFor(Comment::class));

        $this->assertTrue($perimeter->admits($this->ada, Ability::view(), $this->commentNamed('Ship it')));
        $this->assertFalse($perimeter->admits($this->ada, Ability::view(), $this->commentNamed('Add search')));
        $this->assertFalse($perimeter->admits($this->ada, Ability::update(), $this->commentNamed('Ship it')));
    }
}
