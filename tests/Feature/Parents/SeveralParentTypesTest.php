<?php

namespace Lomkit\Access\Tests\Feature\Parents;

use BadMethodCallException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Comment;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Links\ConfiguredMemoControl;
use Lomkit\Access\Tests\Fixtures\Links\LinkedSubjectsPerimeter;
use Lomkit\Access\Tests\Fixtures\Links\Links;
use Lomkit\Access\Tests\Fixtures\Links\Memo;
use Lomkit\Access\Tests\Fixtures\Links\MemoControl;
use PHPUnit\Framework\Attributes\Test;

class SeveralParentTypesTest extends ParentTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Blog, new Links];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->grant([
            'view own posts' => ['Ada', 'Grace'],
            'view client posts' => ['Linus'],
            'view own users' => ['Ada'],
            'view client users' => ['Linus'],
            'view memos' => ['Ada', 'Linus', 'Root'],
        ]);
        $this->memo('Release notes memo', $this->releaseNotes);
        $this->memo('Roadmap memo', $this->roadmap);
        $this->memo('Memo on Ada', $this->ada);
        $this->memo('Pricing and roadmap memo', $this->pricing, $this->roadmap);
        $this->memo('Other workspace memo', $this->elsewhere);
        $this->memo('Unlinked memo');
        $this->memo('Memo on Root', $this->root);
    }

    protected function memo(string $body, Model ...$subjects): Memo
    {
        $memo = Memo::query()->create(['body' => $body]);

        foreach ($subjects as $subject) {
            $memo->links()->create(['subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey()]);
        }

        return $memo;
    }

    /** @return list<string> */
    protected function visibleMemos(User $reader): array
    {
        return Memo::controlledFor($reader)
            ->orderBy('id')
            ->pluck('body')
            ->all();
    }

    protected function memoNamed(string $body): Memo
    {
        return Memo::query()->where('body', $body)->firstOrFail();
    }

    #[Test]
    public function it_lists_a_memo_when_the_reader_may_view_one_of_its_subjects_of_any_listed_type(): void
    {
        $this->assertSame(['Release notes memo', 'Memo on Ada'], $this->visibleMemos($this->ada));
        $this->assertSame(['Pricing and roadmap memo', 'Memo on Root'], $this->visibleMemos($this->linus));
        $this->assertSame([], $this->visibleMemos($this->grace));
        $this->assertSame([], $this->visibleMemos($this->root));
    }

    #[Test]
    public function it_decides_a_memo_the_same_way_on_every_path(): void
    {
        $memos = Memo::query()->orderBy('id')->get();

        foreach ([$this->ada, $this->grace, $this->linus, $this->root] as $reader) {
            $this->assertControlAgrees(MemoControl::class, $reader, $memos);
        }
    }

    #[Test]
    public function it_reaches_the_parents_of_the_reader_it_decides_for_rather_than_the_signed_in_user(): void
    {
        $this->actingAs($this->linus);

        $this->assertSame(['Release notes memo', 'Memo on Ada'], $this->visibleMemos($this->ada));
        $this->assertTrue(Gate::forUser($this->ada)->allows('view', $this->memoNamed('Memo on Ada')));
        $this->assertFalse(Gate::forUser($this->ada)->allows('view', $this->memoNamed('Memo on Root')));
    }

    #[Test]
    public function it_grants_no_other_ability_than_view(): void
    {
        $memo = $this->memoNamed('Release notes memo');

        $this->assertTrue(Gate::forUser($this->ada)->allows('view', $memo));

        foreach (['update', 'delete'] as $ability) {
            $this->assertSame('no_perimeter', Gate::forUser($this->ada)->inspect($ability, $memo)->code());
            $this->assertSame([], Memo::controlledFor($this->ada, $ability)->pluck('body')->all());
        }

        $this->assertFalse(Access::controlFor(Memo::class)->applies($this->ada, Ability::of('approve'), $memo));
        $this->assertSame([], Memo::controlledFor($this->ada, 'approve')->pluck('body')->all());
    }

    #[Test]
    public function it_grants_view_any_to_every_reader_its_allowed_accepts_whether_or_not_a_parent_is_reachable(): void
    {
        $this->assertTrue(Gate::forUser($this->root)->allows('viewAny', Memo::class));
        $this->assertSame([], $this->visibleMemos($this->root));
        $this->assertFalse(Gate::forUser($this->grace)->allows('viewAny', Memo::class));
    }

    #[Test]
    public function it_opens_nothing_through_an_unlisted_type_a_missing_row_or_an_unknown_type(): void
    {
        $this->assertTrue(Gate::forUser($this->ada)->allows('view', Comment::query()->where('body', 'Ship it')->firstOrFail()));
        $this->link($this->memo('Memo on a comment'), Comment::class, Comment::query()->where('body', 'Ship it')->firstOrFail()->getKey());
        $this->link($this->memo('Memo on a deleted post'), Post::class, 99999);
        $this->link($this->memo('Memo on an unknown type'), 'Nope\Missing', $this->releaseNotes->getKey());

        $this->assertSame(['Release notes memo', 'Memo on Ada'], $this->visibleMemos($this->ada));

        foreach ([$this->ada, $this->linus] as $reader) {
            $this->assertControlAgrees(MemoControl::class, $reader, Memo::query()->orderBy('id')->get());
        }
    }

    #[Test]
    public function it_follows_the_morph_map_of_the_application(): void
    {
        Relation::morphMap(['post' => Post::class, 'user' => User::class]);

        try {
            $this->link($this->memo('Mapped release notes memo'), 'post', $this->releaseNotes->getKey());
            $this->link($this->memo('Mapped roadmap memo'), 'post', $this->roadmap->getKey());

            $this->assertContains('Mapped release notes memo', $this->visibleMemos($this->ada));
            $this->assertNotContains('Mapped roadmap memo', $this->visibleMemos($this->ada));
            $this->assertControlAgrees(MemoControl::class, $this->ada, Memo::query()->orderBy('id')->get());
        } finally {
            Relation::morphMap([], false);
        }
    }

    #[Test]
    public function it_throws_when_a_listed_type_has_no_controlled_for_scope(): void
    {
        ConfiguredMemoControl::bind(
            LinkedSubjectsPerimeter::new()
                ->allowed(fn (User $user, Ability $ability): bool => $ability->is('view'))
                ->query(function (Builder $query, User $user): void {
                    $query->whereHas('links', fn (Builder $links) => $links->whereHasMorph(
                        'subject',
                        [Post::class, Project::class],
                        fn (Builder $subjects) => $subjects->controlledFor($user),
                    ));
                }),
        );

        $this->expectException(BadMethodCallException::class);

        $this->visibleMemos($this->ada);
    }

    protected function link(Memo $memo, string $type, int $id): void
    {
        $memo->links()->create(['subject_type' => $type, 'subject_id' => $id]);
    }
}
