<?php

namespace Lomkit\Access\Tests\Feature\Policies;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\Decisions\Draft;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Feature\Parents\ParentTestCase;
use Lomkit\Access\Tests\Fixtures\Blog\Comment;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredCommentControl;
use PHPUnit\Framework\Attributes\Test;

class BusinessAbilityDraftTest extends ParentTestCase
{
    /** @var list<string> */
    protected array $queries = [];

    protected bool $listening = false;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        DB::listen(function (QueryExecuted $query): void {
            if ($this->listening) {
                $this->queries[] = $query->sql;
            }
        });
        $this->grant(['record own posts' => ['Ada'], 'view own posts' => ['Ada', 'Grace'], 'update own posts' => ['Ada']]);
    }

    /**
     * @param  callable(): mixed  $callback
     * @return list<string>
     */
    protected function queriesDuring(callable $callback): array
    {
        Access::forgetDecisions();
        $this->queries = [];
        $this->listening = true;

        try {
            $callback();
        } finally {
            $this->listening = false;
        }

        return $this->queries;
    }

    protected function code(User $reader, string $ability, mixed $arguments): string
    {
        $response = Gate::forUser($reader)->inspect($ability, $arguments);

        return $response->allowed() ? 'granted' : (string) $response->code();
    }

    protected function postDraft(User $author, int $workspace, ?string $archivedAt = null): Post
    {
        return new Post([
            'project_id' => $this->releaseNotes->project_id,
            'author_id' => $author->getKey(),
            'title' => 'Visit',
            'workspace_id' => $workspace,
            'archived_at' => $archivedAt,
        ]);
    }

    #[Test]
    public function it_judges_a_business_ability_on_a_draft_where_the_row_lands_without_any_query(): void
    {
        $queries = $this->queriesDuring(function (): void {
            $this->assertSame('granted', $this->code($this->ada, 'record', [Post::class, Draft::of($this->postDraft($this->ada, 1))]));
            $this->assertSame('outside_rows', $this->code($this->ada, 'record', [Post::class, Draft::of($this->postDraft($this->grace, 1))]));
            $this->assertSame('outside_boundary', $this->code($this->ada, 'record', [Post::class, Draft::of($this->postDraft($this->ada, 2))]));
            $this->assertSame('outside_boundary', $this->code($this->ada, 'record', [Post::class, Draft::of($this->postDraft($this->ada, 1, '2026-01-01 00:00:00'))]));
            $this->assertSame('no_perimeter', $this->code($this->grace, 'record', [Post::class, Draft::of($this->postDraft($this->grace, 1))]));
        });

        $this->assertSame([], $queries);
    }

    #[Test]
    public function it_judges_a_business_ability_on_a_draft_given_to_the_control(): void
    {
        $control = Access::controlFor(Post::class);

        $this->assertTrue($control->applies($this->ada, Ability::of('record'), Draft::of($this->postDraft($this->ada, 1))));
        $this->assertSame('outside_rows', $control->decide($this->ada, Ability::of('record'), Draft::of($this->postDraft($this->grace, 1)))->code());
    }

    #[Test]
    public function it_refuses_the_unsaved_model_itself_rather_than_guess(): void
    {
        $control = Access::controlFor(Post::class);

        $this->assertSame('unsaved', $control->decide($this->ada, Ability::of('record'), $this->postDraft($this->ada, 1))->code());
        $this->assertFalse($control->applies($this->ada, Ability::of('record'), $this->postDraft($this->ada, 1)));
    }

    #[Test]
    public function it_judges_a_saved_row_on_its_stored_values_and_refuses_an_unsaved_model_through_the_gate(): void
    {
        $this->assertSame('outside_rows', $this->code($this->ada, 'record', $this->roadmap));

        $this->roadmap->fill(['author_id' => $this->ada->getKey()]);

        $this->assertSame('outside_rows', $this->code($this->ada, 'record', $this->roadmap));
        $this->assertSame('unsaved', $this->code($this->ada, 'record', [Post::class, $this->postDraft($this->ada, 1)]));
    }

    #[Test]
    public function it_judges_a_business_ability_without_a_row_with_allowed_only(): void
    {
        $queries = $this->queriesDuring(function (): void {
            $this->assertSame('granted', $this->code($this->ada, 'record', Post::class));
            $this->assertSame('no_perimeter', $this->code($this->grace, 'record', Post::class));
        });

        $this->assertSame([], $queries);
    }

    #[Test]
    public function it_judges_a_business_ability_on_a_draft_through_a_parent_that_delegates_it(): void
    {
        ConfiguredCommentControl::bind(ThroughParent::belongsTo('post')->delegates(['view', 'record' => 'update']));
        $control = Access::controlFor(Comment::class);

        $this->assertTrue($control->decide($this->ada, Ability::of('record'), Draft::of($this->draftOn($this->releaseNotes, $this->ada)))->allowed());
        $this->assertTrue($control->applies($this->ada, Ability::of('record'), Draft::of($this->draftOn($this->releaseNotes, $this->ada))));
        $this->assertSame('outside_rows', $control->decide($this->ada, Ability::of('record'), Draft::of($this->draftOn($this->roadmap, $this->ada)))->code());
        $this->assertSame('outside_rows', $control->decide($this->ada, Ability::of('record'), Draft::of($this->draftOn($this->archive, $this->ada)))->code());
    }

    #[Test]
    public function it_refuses_a_business_ability_on_a_draft_through_a_parent_that_does_not_delegate_it(): void
    {
        ConfiguredCommentControl::bind(ThroughParent::belongsTo('post')->delegates(['view', 'update']));

        $this->assertSame(
            'no_perimeter',
            Access::controlFor(Comment::class)->decide($this->ada, Ability::of('record'), Draft::of($this->draftOn($this->releaseNotes, $this->ada)))->code(),
        );
    }
}
