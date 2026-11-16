<?php

namespace Lomkit\Access\Tests\Feature\Parents;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\Decisions\Draft;
use Lomkit\Access\Exceptions\DuplicatePerimeterCallbackException;
use Lomkit\Access\Exceptions\ImpurePerimetersException;
use Lomkit\Access\Exceptions\ThroughParentCallbackException;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Fixtures\Blog\Comment;
use Lomkit\Access\Tests\Fixtures\Blog\Editor;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredCommentControl;
use Lomkit\Access\Tests\Fixtures\Inheritance\Controls\CommentControl;
use Lomkit\Access\Tests\Fixtures\Inheritance\ThresholdCommentControl;
use PHPUnit\Framework\Attributes\Test;

class ThroughParentAllowedTest extends ParentTestCase
{
    /** @var list<string> */
    protected array $allowedCalls = [];

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
        $this->grant([
            'view own posts' => ['Ada', 'Grace'],
            'update own posts' => ['Ada', 'Grace'],
            'view global comments' => ['Root'],
            'view comments' => ['Ada'],
            'update comments' => ['Ada'],
            'create comments' => ['Ada'],
        ]);
        ConfiguredCommentControl::bind($this->throughPost(['view', 'update', 'create' => 'update']));
    }

    /** @param  array<array-key, string>  $delegates */
    protected function throughPost(array $delegates): ThroughParent
    {
        return ThroughParent::belongsTo('post')
            ->delegates($delegates)
            ->allowed(function (User $user, Ability $ability): bool {
                $this->allowedCalls[] = "{$user->name}:{$ability}";

                return $user->can("{$ability} comments");
            });
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

    protected function code(User $reader, string $ability, mixed $subject): string
    {
        $response = Gate::forUser($reader)->inspect($ability, $subject);

        return $response->allowed() ? 'granted' : (string) $response->code();
    }

    protected function assertEveryReaderAgrees(): void
    {
        $comments = Comment::query()->orderBy('id')->get();

        foreach ([$this->ada, $this->grace, $this->linus, $this->root] as $reader) {
            $this->assertControlAgrees(CommentControl::class, $reader, $comments, ['view', 'update', 'create']);
        }
    }

    #[Test]
    public function it_refuses_with_no_perimeter_when_allowed_refuses_even_though_the_parent_grants(): void
    {
        $comment = $this->commentNamed('Add search');
        $this->assertTrue(Gate::forUser($this->grace)->allows('update', $this->roadmap));

        $queries = $this->queriesDuring(function () use ($comment): void {
            $this->assertSame('no_perimeter', $this->code($this->grace, 'view', $comment));
            $this->assertSame('no_perimeter', $this->code($this->grace, 'update', $comment));
        });

        $this->assertSame([], $queries);
        $this->assertSame(['Grace:view', 'Grace:update'], $this->allowedCalls);
    }

    #[Test]
    public function it_grants_when_allowed_accepts_and_the_parent_grants(): void
    {
        $this->assertSame('granted', $this->code($this->ada, 'view', $this->commentNamed('Ship it')));
        $this->assertSame('granted', $this->code($this->ada, 'update', $this->commentNamed('Ship it')));
        $this->assertSame('outside_rows', $this->code($this->ada, 'view', $this->commentNamed('Add search')));
    }

    #[Test]
    public function it_never_calls_allowed_for_an_ability_it_does_not_delegate(): void
    {
        $comment = $this->commentNamed('Ship it');

        $queries = $this->queriesDuring(function () use ($comment): void {
            $this->assertSame('no_perimeter', $this->code($this->ada, 'delete', $comment));
            $this->assertSame('no_perimeter', $this->code($this->ada, 'forceDelete', $comment));
        });

        $this->assertSame([], $this->allowedCalls);
        $this->assertSame([], $queries);
    }

    #[Test]
    public function it_gives_allowed_the_ability_of_the_child_rather_than_the_one_of_the_parent(): void
    {
        $this->assertSame('granted', $this->code($this->ada, 'create', [Comment::class, $this->draftOn($this->releaseNotes, $this->ada)]));

        $this->assertSame(['Ada:create'], $this->allowedCalls);
    }

    #[Test]
    public function it_leaves_the_perimeter_out_for_a_reader_of_another_class_than_the_one_allowed_takes(): void
    {
        ConfiguredCommentControl::bind(
            ThroughParent::belongsTo('post')->allowed(fn (Editor $editor): bool => true),
            GlobalPerimeter::new()
                ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} global comments"))
                ->unrestricted(),
        );

        $this->assertSame([], $this->visibleComments($this->ada));
        $this->assertCount(7, $this->visibleComments($this->root));
    }

    #[Test]
    public function it_lists_and_batches_nothing_for_a_reader_allowed_refuses(): void
    {
        $comments = Comment::query()->orderBy('id')->get();

        $this->assertSame([], Comment::controlledFor($this->grace, 'update')->pluck('body')->all());
        $this->assertSame([], Comment::controlledFor($this->grace)->withAbilities(['view', 'update'])->pluck('body')->all());
        $this->assertSame([], Access::controlFor(Comment::class)->reachableKeys($this->grace, Ability::update(), $comments));
        $this->assertSame([], $this->queriesDuring(function () use ($comments): void {
            Access::preload($this->grace, 'update', $comments);
            Access::preload($this->grace, 'view', $comments);
        }));
        $this->assertEveryReaderAgrees();
    }

    #[Test]
    public function it_refuses_the_ability_allowed_refuses_and_keeps_the_ones_it_accepts_in_a_batch(): void
    {
        $this->grant(['view comments' => ['Ada', 'Grace']]);
        $comments = Comment::controlledFor($this->grace)
            ->withAbilities(['view', 'update'])
            ->orderBy('id')
            ->get();

        $this->assertSame(['Add search'], $comments->pluck('body')->all());
        $this->assertSame([], $this->queriesDuring(fn () => $this->assertFalse(Gate::forUser($this->grace)->allows('update', $comments->firstOrFail()))));
        $this->assertSame([], Comment::controlledFor($this->grace, 'update')->pluck('body')->all());
        $this->assertEveryReaderAgrees();
    }

    #[Test]
    public function it_agrees_on_every_path_when_allowed_accepts(): void
    {
        $this->assertSame(['Ship it', 'Typo in the title', 'Old news'], $this->visibleComments($this->ada));
        $this->assertSame(['Ship it', 'Typo in the title'], Comment::controlledFor($this->ada, 'update')->orderBy('id')->pluck('body')->all());
        $this->assertEveryReaderAgrees();
    }

    #[Test]
    public function it_refuses_a_draft_allowed_refuses_without_any_query_or_loading_the_parent(): void
    {
        $draft = $this->draftOn($this->roadmap, $this->grace);

        $queries = $this->queriesDuring(function () use ($draft): void {
            $this->assertSame('no_perimeter', $this->code($this->grace, 'create', [Comment::class, $draft]));
            $this->assertFalse(Access::controlFor(Comment::class)->applies($this->grace, Ability::create(), Draft::of($draft)));
        });

        $this->assertSame([], $queries);
        $this->assertFalse($draft->relationLoaded('post'));
    }

    #[Test]
    public function it_calls_allowed_once_per_reader_ability_and_context_for_a_page(): void
    {
        $comments = Comment::query()->orderBy('id')->get();

        foreach ($comments as $comment) {
            Gate::forUser($this->ada)->allows('view', $comment);
            Gate::forUser($this->ada)->allows('update', $comment);
            Gate::forUser($this->grace)->allows('view', $comment);
        }

        $this->assertSame(['Ada:view', 'Ada:update', 'Grace:view'], $this->allowedCalls);
    }

    #[Test]
    public function it_calls_allowed_once_per_ability_for_a_page_with_abilities(): void
    {
        $comments = Comment::controlledFor($this->ada)
            ->withAbilities(['view', 'update'])
            ->orderBy('id')
            ->get();

        foreach ($comments as $comment) {
            Gate::forUser($this->ada)->allows('update', $comment);
        }

        $this->assertCount(3, $comments);
        $this->assertSame(['Ada:view', 'Ada:update'], array_values(array_unique($this->allowedCalls)));
        $this->assertCount(1, array_keys($this->allowedCalls, 'Ada:update', true));
    }

    #[Test]
    public function it_throws_when_a_captured_value_changes_between_two_builds(): void
    {
        $this->app->instance(CommentControl::class, new ThresholdCommentControl);
        config(['blog.comment_threshold' => 1]);
        $this->assertSame(['Ship it', 'Typo in the title', 'Old news'], $this->visibleComments($this->ada));

        config(['blog.comment_threshold' => 2]);

        $this->expectException(ImpurePerimetersException::class);
        $this->expectExceptionMessage(sprintf('The perimeters() or boundary() method of the [%s] control built a different declaration twice.', ThresholdCommentControl::class));

        $this->visibleComments($this->ada);
    }

    #[Test]
    public function it_works_as_a_level_of_a_group(): void
    {
        ConfiguredCommentControl::bind(Levels::of(
            GlobalPerimeter::new()
                ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} global comments"))
                ->unrestricted(),
            $this->throughPost(['view', 'update']),
        ));

        $this->assertCount(7, $this->visibleComments($this->root));
        $this->assertSame(['Ship it', 'Typo in the title', 'Old news'], $this->visibleComments($this->ada));
        $this->assertSame([], $this->visibleComments($this->grace));
        $this->assertLevelsNest(CommentControl::class, [$this->ada, $this->grace, $this->linus, $this->root]);
        $this->assertEveryReaderAgrees();
    }

    #[Test]
    public function it_refuses_a_second_allowed_and_the_other_callbacks(): void
    {
        $perimeter = ThroughParent::belongsTo('post')->allowed(fn (User $user): bool => true);

        try {
            $perimeter->allowed(fn (User $user): bool => true);
            $this->fail('A second allowed() was accepted.');
        } catch (DuplicatePerimeterCallbackException) {
        }

        $this->expectException(ThroughParentCallbackException::class);
        $this->expectExceptionMessage('A ThroughParent perimeter takes no ->should(): the control of the parent decides which rows it reaches. To require a rule of the child as well, declare ->allowed() on the ThroughParent; to grant other rows, declare a separate perimeter next to it.');

        $perimeter->should(fn (User $user): bool => true);
    }
}
