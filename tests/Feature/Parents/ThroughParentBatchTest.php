<?php

namespace Lomkit\Access\Tests\Feature\Parents;

use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Fixtures\Blog\Comment;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredCommentControl;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use Lomkit\Access\Tests\Fixtures\Inheritance\Controls\CommentControl;
use PHPUnit\Framework\Attributes\Test;

class ThroughParentBatchTest extends ParentTestCase
{
    protected const array PERMISSIONS = [
        'view global posts' => ['Root'],
        'update global posts' => ['Root'],
        'view client posts' => ['Linus'],
        'update client posts' => ['Linus'],
        'view own posts' => ['Ada', 'Grace'],
        'update own posts' => ['Ada'],
        'delete own posts' => ['Ada'],
        'view shared posts' => ['Grace'],
        'view own comments' => ['Grace', 'Linus'],
        'update own comments' => ['Grace'],
        'view client projects' => ['Linus', 'Ada'],
        'update client projects' => ['Ada'],
        'view own users' => ['Ada'],
    ];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->grant(self::PERMISSIONS);
        $this->pricing->sharedWith()->attach($this->grace);
    }

    protected function assertEveryReaderAgrees(): void
    {
        $comments = Comment::query()->orderBy('id')->get();

        foreach ([$this->ada, $this->grace, $this->linus, $this->root] as $reader) {
            $this->assertControlAgrees(CommentControl::class, $reader, $comments, ['view', 'update', 'delete']);
        }
    }

    #[Test]
    public function it_agrees_on_every_path_with_the_default_delegation(): void
    {
        $this->assertEveryReaderAgrees();
    }

    #[Test]
    public function it_agrees_on_every_path_with_delegated_and_mapped_abilities(): void
    {
        ConfiguredCommentControl::bind(
            ThroughParent::belongsTo('post')->delegates(['view', 'update', 'delete' => 'update']),
            OwnPerimeter::new()
                ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} own comments"))
                ->query(fn ($query, User $user) => $query->where('author_id', $user->getKey())),
        );

        $this->assertEveryReaderAgrees();
    }

    #[Test]
    public function it_agrees_on_every_path_with_two_parents_a_child_boundary_and_levels(): void
    {
        ConfiguredCommentControl::bindWithin(
            Boundary::where('workspace_id', fn (User $user): ?int => $user->current_workspace_id),
            Levels::of(
                GlobalPerimeter::new()
                    ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} global comments"))
                    ->unrestricted(),
                ThroughParent::belongsTo('post')->delegates(['view', 'update']),
            ),
            ThroughParent::belongsTo('author'),
        );

        $this->assertEveryReaderAgrees();
    }

    #[Test]
    public function it_agrees_on_every_path_through_nested_parents(): void
    {
        ConfiguredPostControl::bindWithin(
            Boundary::where('workspace_id', fn (User $user): ?int => $user->current_workspace_id),
            ThroughParent::belongsTo('project')->delegates(['view', 'update']),
        );
        ConfiguredCommentControl::bind(ThroughParent::belongsTo('post')->delegates(['view', 'update', 'delete' => 'view']));

        $this->assertEveryReaderAgrees();
        $this->assertSame(['Ship it', 'Typo in the title', 'Add search', 'Old news'], $this->bodiesFor($this->ada, 'update'));
    }

    #[Test]
    public function it_decides_the_comments_of_a_page_in_one_query_with_abilities(): void
    {
        ConfiguredCommentControl::bind(ThroughParent::belongsTo('post')->delegates(['view', 'update']));
        $comments = null;

        $queries = $this->queriesWhile(function () use (&$comments): void {
            $comments = Comment::controlledFor($this->ada)
                ->withAbilities(['view', 'update'])
                ->orderBy('id')
                ->get();
        });

        $this->assertCount(1, $queries);
        $this->assertNotNull($comments);
        $this->assertSame(['Ship it', 'Typo in the title', 'Old news'], $comments->pluck('body')->all());
        $this->assertSame([], $this->queriesWithoutForgetting(function () use ($comments): void {
            $this->assertSame(
                [true, true, false],
                $comments->map(fn (Comment $comment): bool => Gate::forUser($this->ada)->allows('update', $comment))->all(),
            );
        }));
    }

    #[Test]
    public function it_preloads_the_decisions_of_the_comments_in_one_query_per_ability(): void
    {
        $comments = Comment::query()->orderBy('id')->get();

        $queries = $this->queriesWhile(function () use ($comments): void {
            Access::preload($this->grace, 'view', $comments);
            Access::preload($this->grace, Ability::update(), $comments);
        });

        $this->assertCount(2, $queries);
        $this->assertSame([], $this->queriesWithoutForgetting(function () use ($comments): void {
            $this->assertSame(
                ['Ship it', 'Add search', 'Old news', 'Wrong workspace', 'Too expensive', 'Agreed'],
                $comments->filter(fn (Comment $comment): bool => Gate::forUser($this->grace)->allows('view', $comment))->pluck('body')->values()->all(),
            );
        }));
    }

    #[Test]
    public function it_reaches_the_keys_of_the_comments_in_one_query(): void
    {
        $comments = Comment::query()->orderBy('id')->get();
        $this->linus->load('client');
        $keys = [];

        $queries = $this->queriesWhile(function () use ($comments, &$keys): void {
            $keys = Access::controlFor(Comment::class)->reachableKeys($this->linus, Ability::view(), $comments);
        });

        $this->assertCount(1, $queries);
        $this->assertSame(
            Comment::query()->whereIn('body', ['Too expensive', 'Agreed', 'Typo in the title'])->orderBy('id')->pluck('id')->all(),
            $keys,
        );
    }
}
