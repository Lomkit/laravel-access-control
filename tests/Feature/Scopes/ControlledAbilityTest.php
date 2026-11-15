<?php

namespace Lomkit\Access\Tests\Feature\Scopes;

use Lomkit\Access\Ability;
use Lomkit\Access\Decisions\DecisionMemo;
use Lomkit\Access\Exceptions\IgnoredArgumentException;
use Lomkit\Access\Exceptions\InvalidAbilityException;
use Lomkit\Access\Exceptions\MissingPerimeterCallbackException;
use Lomkit\Access\Exceptions\UnlistableAbilityException;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Feature\Parents\ParentTestCase;
use Lomkit\Access\Tests\Fixtures\Blog\Comment;
use Lomkit\Access\Tests\Fixtures\Blog\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredCommentControl;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use PHPUnit\Framework\Attributes\Test;

class ControlledAbilityTest extends ParentTestCase
{
    /** @return list<string> */
    protected function titlesFor(?User $reader, Ability|string $ability): array
    {
        return Post::controlledFor($reader, $ability)
            ->orderBy('id')
            ->pluck('title')
            ->all();
    }

    /** @return list<string> */
    protected function commentsFor(User $reader, string $ability): array
    {
        return Comment::controlledFor($reader, $ability)
            ->orderBy('id')
            ->pluck('body')
            ->all();
    }

    /** @return list<string> */
    protected function grantedTitles(User $reader, string $ability): array
    {
        $titles = [];

        foreach (Post::uncontrolled()->orderBy('id')->get() as $post) {
            if ($reader->can($ability, $post)) {
                $titles[] = $post->title;
            }
        }

        return $titles;
    }

    #[Test]
    public function it_lists_an_archived_post_for_view_and_not_for_update(): void
    {
        $this->grant(['view own posts' => ['Ada'], 'update own posts' => ['Ada']]);

        $this->assertSame(['Release notes', 'Archive'], $this->titlesFor($this->ada, 'view'));
        $this->assertSame(['Release notes'], $this->titlesFor($this->ada, 'update'));
        $this->assertSame($this->grantedTitles($this->ada, 'update'), $this->titlesFor($this->ada, 'update'));
    }

    #[Test]
    public function it_lists_the_level_the_reader_reaches_for_the_ability(): void
    {
        $this->grant(['view client posts' => ['Ada'], 'update own posts' => ['Ada']]);

        $this->assertSame(['Release notes', 'Roadmap', 'Archive'], $this->titlesFor($this->ada, 'view'));
        $this->assertSame(['Release notes'], $this->titlesFor($this->ada, 'update'));
    }

    #[Test]
    public function it_lists_every_row_inside_the_boundary_for_an_unrestricted_perimeter(): void
    {
        $this->grant(['update global posts' => ['Ada']]);

        $this->assertSame(['Release notes', 'Roadmap', 'Pricing'], $this->titlesFor($this->ada, 'update'));
        $this->assertSame([], $this->titlesFor($this->ada, 'view'));
    }

    #[Test]
    public function it_lists_the_rows_of_a_business_ability(): void
    {
        $this->grant(['approve own posts' => ['Grace']]);

        $this->assertSame(['Roadmap'], $this->titlesFor($this->grace, 'approve'));
        $this->assertSame(['Roadmap'], $this->grantedTitles($this->grace, 'approve'));
        $this->assertSame([], $this->titlesFor($this->ada, 'approve'));
    }

    #[Test]
    public function it_lists_no_child_for_an_ability_the_parent_does_not_delegate(): void
    {
        $this->grant(['view own posts' => ['Ada'], 'update own posts' => ['Ada']]);

        $this->assertSame(['Ship it', 'Typo in the title', 'Old news'], $this->commentsFor($this->ada, 'view'));
        $this->assertSame([], $this->commentsFor($this->ada, 'update'));
    }

    #[Test]
    public function it_restricts_a_delegated_ability_through_the_parent(): void
    {
        ConfiguredCommentControl::bind(ThroughParent::belongsTo('post')->delegates(['view', 'update']));
        $this->grant(['view own posts' => ['Ada'], 'update own posts' => ['Ada']]);

        $this->assertSame(['Ship it', 'Typo in the title'], $this->commentsFor($this->ada, 'update'));
        $this->assertSame($this->bodiesFor($this->ada, 'update'), $this->commentsFor($this->ada, 'update'));
    }

    #[Test]
    public function it_restricts_a_mapped_ability_with_the_ability_of_the_parent(): void
    {
        ConfiguredCommentControl::bind(ThroughParent::belongsTo('post')->delegates(['delete' => 'update']));
        $this->grant(['view own posts' => ['Ada'], 'update own posts' => ['Ada']]);

        $this->assertSame(['Ship it', 'Typo in the title'], $this->commentsFor($this->ada, 'delete'));
        $this->assertSame([], $this->commentsFor($this->ada, 'view'));
    }

    #[Test]
    public function it_lists_no_row_without_a_reader_whatever_the_ability(): void
    {
        $this->grant(['update global posts' => ['Ada']]);

        $this->assertSame([], $this->titlesFor(null, 'update'));
        $this->assertSame(0, Post::controlled(Ability::delete())->count());
    }

    #[Test]
    public function it_lists_the_rows_of_the_signed_in_user_for_the_ability(): void
    {
        $this->grant(['view own posts' => ['Ada'], 'update own posts' => ['Ada']]);
        $this->actingAs($this->ada);

        $this->assertSame(['Release notes'], Post::controlled('update')->pluck('title')->all());
    }

    #[Test]
    public function it_maps_a_policy_method_to_its_ability(): void
    {
        $this->grant(['view client posts' => ['Ada'], 'update own posts' => ['Ada']]);

        $viewAny = Post::controlledFor($this->ada, 'viewAny');
        $view = Post::controlledFor($this->ada);

        $this->assertSame($view->toSql(), $viewAny->toSql());
        $this->assertSame($view->getBindings(), $viewAny->getBindings());
        $this->assertSame($this->titlesFor($this->ada, 'update'), $this->titlesFor($this->ada, Ability::update()));
    }

    #[Test]
    public function it_refuses_to_list_create_by_name(): void
    {
        $this->expectException(UnlistableAbilityException::class);

        Post::controlledFor($this->ada, 'create');
    }

    #[Test]
    public function it_refuses_to_list_create_as_an_ability_even_without_a_reader(): void
    {
        $this->expectException(UnlistableAbilityException::class);

        Post::controlledFor(null, Ability::create());
    }

    #[Test]
    public function it_refuses_an_empty_ability_name(): void
    {
        $this->expectException(InvalidAbilityException::class);

        Post::controlledFor($this->ada, '');
    }

    #[Test]
    public function it_refuses_a_second_ability_given_to_controlled_for(): void
    {
        $this->expectException(IgnoredArgumentException::class);

        Post::controlledFor($this->ada, 'update', 'delete');
    }

    #[Test]
    public function it_refuses_a_second_ability_given_to_controlled(): void
    {
        $this->actingAs($this->ada);

        $this->expectException(IgnoredArgumentException::class);

        Post::controlled('update', 'delete');
    }

    #[Test]
    public function it_needs_the_query_of_a_memory_perimeter_for_any_ability(): void
    {
        ConfiguredPostControl::bind(OwnPerimeter::new()
            ->allowed(fn (User $user): bool => true)
            ->should(fn (User $user, Post $post): bool => $post->author_id === $user->getKey()));

        $this->expectException(MissingPerimeterCallbackException::class);

        Post::controlledFor($this->ada, 'update');
    }

    #[Test]
    public function it_lists_the_rows_in_one_query_without_keeping_a_decision(): void
    {
        $this->grant(['view own posts' => ['Ada'], 'update own posts' => ['Ada']]);
        $memo = $this->app->make(DecisionMemo::class);

        $queries = $this->queriesWhile(fn () => Post::controlledFor($this->ada, 'update')->get());

        $this->assertCount(1, $queries);
        $this->assertSame(0, $memo->count());
    }

    #[Test]
    public function it_decides_other_abilities_on_the_rows_it_lists(): void
    {
        $this->grant(['view client posts' => ['Ada'], 'update client posts' => ['Ada'], 'delete own posts' => ['Ada']]);
        $posts = Post::controlledFor($this->ada, 'update')
            ->withAbilities(['view', 'delete'])
            ->with('project')
            ->orderBy('id')
            ->get();

        $queries = $this->queriesWithoutForgetting(function () use ($posts, &$answers): void {
            $answers = $posts->map(fn (Post $post): array => [$post->title, $this->ada->can('view', $post), $this->ada->can('delete', $post)])->all();
        });

        $this->assertSame([], $queries);
        $this->assertSame([['Release notes', true, true], ['Roadmap', true, false]], $answers);
    }

    #[Test]
    public function it_selects_the_keys_of_the_list_in_a_where_in_subquery(): void
    {
        $this->grant(['view client posts' => ['Ada'], 'update own posts' => ['Ada']]);

        $bodies = Comment::query()
            ->whereIn('post_id', Post::controlledFor($this->ada, 'update')->select('id'))
            ->orderBy('id')
            ->pluck('body')
            ->all();

        $this->assertSame(['Ship it', 'Typo in the title'], $bodies);
    }
}
