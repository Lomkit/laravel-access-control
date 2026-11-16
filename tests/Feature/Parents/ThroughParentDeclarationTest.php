<?php

namespace Lomkit\Access\Tests\Feature\Parents;

use Closure;
use Illuminate\Support\Facades\DB;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Exceptions\InvalidAbilityException;
use Lomkit\Access\Exceptions\InvalidDelegationException;
use Lomkit\Access\Exceptions\InvalidParentRelationException;
use Lomkit\Access\Exceptions\MissingParentControlException;
use Lomkit\Access\Exceptions\ParentCycleException;
use Lomkit\Access\Exceptions\ThroughParentCallbackException;
use Lomkit\Access\Perimeters\PerimeterList;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Fixtures\Blog\Comment;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\ConfiguredCommentControl;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use Lomkit\Access\Tests\Fixtures\Inheritance\Book;
use Lomkit\Access\Tests\Fixtures\Inheritance\Chapter;
use Lomkit\Access\Tests\Fixtures\Inheritance\ConfiguredChapterControl;
use PHPUnit\Framework\Attributes\Test;

class ThroughParentDeclarationTest extends ParentTestCase
{
    protected function assertThrowsWithoutQuery(string $exception, string $message, Closure $callback): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $callback();

            $this->fail(sprintf('No [%s] was thrown.', $exception));
        } catch (\Throwable $thrown) {
            $this->assertInstanceOf($exception, $thrown);
            $this->assertStringContainsString($message, $thrown->getMessage());
        } finally {
            DB::disableQueryLog();
        }

        $this->assertSame([], DB::getQueryLog());
    }

    #[Test]
    public function it_throws_when_the_child_model_has_no_such_relation(): void
    {
        ConfiguredCommentControl::bind(ThroughParent::belongsTo('thread'));

        $this->assertThrowsWithoutQuery(
            InvalidParentRelationException::class,
            sprintf('The ThroughParent perimeter of the [%s] control names the [thread] relation, which the [%s] model does not define.', ConfiguredCommentControl::class, Comment::class),
            fn () => Comment::controlledFor($this->ada)->get(),
        );
    }

    #[Test]
    public function it_throws_when_the_relation_is_not_a_belongs_to(): void
    {
        foreach (['chapters' => 'HasMany', 'subject' => 'MorphTo', 'title' => 'string'] as $relation => $type) {
            ConfiguredChapterControl::bind(ThroughParent::belongsTo($relation));

            $this->assertThrowsWithoutQuery(
                InvalidParentRelationException::class,
                sprintf('names the [%s] relation of the [%s] model, which is a [', $relation, Chapter::class),
                fn () => Access::controlFor(Chapter::class),
            );
            $this->assertThrowsWithoutQuery(InvalidParentRelationException::class, $type, fn () => Access::controlFor(Chapter::class));
        }
    }

    #[Test]
    public function it_throws_when_the_parent_has_no_control(): void
    {
        $this->discoverControls(['Controls']);
        ConfiguredPostControl::bind(ThroughParent::belongsTo('project'));

        $this->assertThrowsWithoutQuery(
            MissingParentControlException::class,
            sprintf('The ThroughParent perimeter of the [%s] control inherits the access of the [%s] model through the [project] relation, but no control is registered for that model', ConfiguredPostControl::class, Project::class),
            fn () => Post::controlledFor($this->ada)->get(),
        );
    }

    #[Test]
    public function it_throws_when_a_model_inherits_from_itself(): void
    {
        ConfiguredChapterControl::bind(ThroughParent::belongsTo('previous'));

        $this->assertThrowsWithoutQuery(
            ParentCycleException::class,
            sprintf('form a cycle: %s → %s.', Chapter::class, Chapter::class),
            fn () => Access::controlFor(Chapter::class),
        );
    }

    #[Test]
    public function it_throws_when_the_parents_lead_back_to_the_child(): void
    {
        ConfiguredChapterControl::bind(ThroughParent::belongsTo('book'));

        $this->assertThrowsWithoutQuery(
            ParentCycleException::class,
            sprintf('form a cycle: %s → %s → %s.', Chapter::class, Book::class, Chapter::class),
            fn () => Access::controlFor(Chapter::class),
        );
        $this->assertThrowsWithoutQuery(
            ParentCycleException::class,
            sprintf('form a cycle: %s → %s → %s.', Book::class, Chapter::class, Book::class),
            fn () => Access::controlFor(Book::class)->decide($this->ada, Ability::view()),
        );
    }

    #[Test]
    public function it_throws_when_the_inverse_is_not_the_has_many_of_the_parent_on_the_same_keys(): void
    {
        $cases = [
            [Comment::class, 'post', 'sharedWith'],
            [Comment::class, 'post', 'nothing'],
            [Comment::class, 'author', 'posts'],
            [Chapter::class, 'book', 'drafts'],
            [Chapter::class, 'book', 'coverChapter'],
        ];

        foreach ($cases as [$model, $relation, $inverse]) {
            $model === Comment::class
                ? ConfiguredCommentControl::bind(ThroughParent::belongsTo($relation, inverse: $inverse))
                : ConfiguredChapterControl::bind(ThroughParent::belongsTo($relation, inverse: $inverse));

            $this->assertThrowsWithoutQuery(
                InvalidParentRelationException::class,
                sprintf('names [%s] as the inverse of the [%s] relation', $inverse, $relation),
                fn () => Access::controlFor($model),
            );
        }
    }

    #[Test]
    public function it_accepts_the_has_many_of_the_parent_as_the_inverse(): void
    {
        ConfiguredCommentControl::bind(ThroughParent::belongsTo('post', inverse: 'comments'));
        $this->grant(['view own posts' => ['Ada']]);

        $this->assertSame(['Ship it', 'Typo in the title', 'Old news'], $this->visibleComments($this->ada));
    }

    #[Test]
    public function it_validates_the_declaration_when_the_control_is_built_rather_than_when_the_perimeter_is(): void
    {
        $perimeter = ThroughParent::belongsTo('thread', inverse: 'nothing');

        ConfiguredCommentControl::bind($perimeter);

        $this->expectException(InvalidParentRelationException::class);

        Access::controlFor(Comment::class);
    }

    #[Test]
    public function it_throws_on_an_invalid_delegation(): void
    {
        $cases = [
            [[], InvalidDelegationException::class, 'The ThroughParent perimeter through the [post] relation delegates no ability.'],
            [['view', 'view' => 'update'], InvalidDelegationException::class, 'delegates the [view] ability twice.'],
            [['update', 'update'], InvalidDelegationException::class, 'delegates the [update] ability twice.'],
            [[42], InvalidDelegationException::class, 'delegates a [int] at key [0], not an ability name or an Ability.'],
            [['create' => null], InvalidDelegationException::class, 'delegates a [null] at key [create]'],
            [['up date'], InvalidAbilityException::class, 'The [up date] ability name contains whitespace.'],
            [['re view' => 'view'], InvalidAbilityException::class, 'The [re view] ability name contains whitespace.'],
        ];

        foreach ($cases as [$abilities, $exception, $message]) {
            $this->assertThrowsWithoutQuery($exception, $message, fn () => ThroughParent::belongsTo('post')->delegates($abilities));
        }
    }

    #[Test]
    public function it_takes_no_callback_of_a_perimeter(): void
    {
        $perimeter = ThroughParent::belongsTo('post');
        $calls = [
            'should' => fn () => $perimeter->should(fn (): bool => true),
            'query' => fn () => $perimeter->query(fn (): bool => true),
            'searchKeys' => fn () => $perimeter->searchKeys('post', fn (): int => 1, fn (): int => 1),
            'reindexWhen' => fn () => $perimeter->reindexWhen(Post::class, ['title'], fn (): array => []),
            'unrestricted' => fn () => $perimeter->unrestricted(),
        ];

        foreach ($calls as $method => $call) {
            $this->assertThrowsWithoutQuery(ThroughParentCallbackException::class, "A ThroughParent perimeter takes no ->{$method}()", $call);
        }

        $this->assertThrowsWithoutQuery(ThroughParentCallbackException::class, "is built with ThroughParent::belongsTo('relation')", fn () => ThroughParent::new());
    }

    #[Test]
    public function it_throws_when_used_outside_a_control(): void
    {
        $comment = $this->commentNamed('Ship it');

        $this->assertThrowsWithoutQuery(
            ThroughParentCallbackException::class,
            'The ThroughParent perimeter through the [post] relation is used outside a control.',
            fn () => ThroughParent::belongsTo('post')->admits($this->ada, Ability::view(), $comment),
        );
    }

    #[Test]
    public function it_returns_a_new_perimeter_on_each_declaration(): void
    {
        $perimeter = ThroughParent::belongsTo('post');
        $widened = $perimeter->delegates(['view', 'update']);

        $this->assertNotSame($perimeter, $widened);
        $this->assertFalse((new PerimeterList([$perimeter]))->matches(new PerimeterList([$widened])));
    }

    #[Test]
    public function it_compares_the_relation_the_inverse_and_the_delegation_when_checking_purity(): void
    {
        $declared = new PerimeterList([ThroughParent::belongsTo('post', inverse: 'comments')->delegates(['view', 'create' => 'view'])]);

        $this->assertTrue($declared->matches(new PerimeterList([ThroughParent::belongsTo('post', inverse: 'comments')->delegates(['create' => Ability::view(), 'view'])])));
        $this->assertFalse($declared->matches(new PerimeterList([ThroughParent::belongsTo('author', inverse: 'comments')->delegates(['view', 'create' => 'view'])])));
        $this->assertFalse($declared->matches(new PerimeterList([ThroughParent::belongsTo('post')->delegates(['view', 'create' => 'view'])])));
        $this->assertFalse($declared->matches(new PerimeterList([ThroughParent::belongsTo('post', inverse: 'comments')->delegates(['view', 'create' => 'update'])])));
    }

    #[Test]
    public function it_refuses_every_row_in_sql_for_an_ability_it_does_not_delegate(): void
    {
        $perimeter = ThroughParent::belongsTo('post')->boundTo(Access::controlFor(Comment::class));
        $query = Comment::query();

        $perimeter->applyQueryCallback($query, AccessContext::of($this->ada, Ability::update()));

        $this->assertSame('select * from comments where 0=1', str_replace(['"', '`'], '', $query->toSql()));
    }
}
