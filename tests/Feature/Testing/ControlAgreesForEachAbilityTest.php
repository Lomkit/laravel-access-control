<?php

namespace Lomkit\Access\Tests\Feature\Testing;

use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Ability;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Feature\Parents\ParentTestCase;
use Lomkit\Access\Tests\Fixtures\Blog\Comment;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\NarrowedPostPolicy;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\PostControl;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Blog\WorkspaceBoundary;
use Lomkit\Access\Tests\Fixtures\ConfiguredCommentControl;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use Lomkit\Access\Tests\Fixtures\Inheritance\Controls\CommentControl;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\Test;

class ControlAgreesForEachAbilityTest extends ParentTestCase
{
    protected const array ABILITIES = ['view', 'update', 'delete', 'approve'];

    /** @return list<Post> */
    protected function posts(): array
    {
        return Post::uncontrolled()->orderBy('id')->get()->all();
    }

    /** @return list<Comment> */
    protected function comments(): array
    {
        return Comment::query()->orderBy('id')->get()->all();
    }

    #[Test]
    public function it_compares_the_list_of_each_ability_across_levels_a_read_only_boundary_and_memory_perimeters(): void
    {
        $this->grant([
            'view global posts' => ['Root'],
            'update global posts' => ['Root'],
            'view client posts' => ['Ada', 'Linus'],
            'update client posts' => ['Linus'],
            'view own posts' => ['Ada', 'Grace'],
            'update own posts' => ['Ada', 'Grace'],
            'delete own posts' => ['Ada'],
            'approve own posts' => ['Grace'],
            'view shared posts' => ['Grace'],
            'update shared posts' => ['Grace'],
        ]);
        $this->pricing->sharedWith()->attach($this->grace);

        foreach ([$this->ada, $this->grace, $this->linus, $this->root] as $reader) {
            $this->assertControlAgrees(PostControl::class, $reader, $this->posts(), self::ABILITIES);
        }
    }

    #[Test]
    public function it_compares_the_list_of_each_ability_through_a_parent_that_delegates_update(): void
    {
        ConfiguredCommentControl::bind(ThroughParent::belongsTo('post')->delegates(['view', 'update']));
        $this->grant(['view client posts' => ['Ada'], 'update own posts' => ['Ada', 'Grace'], 'view own posts' => ['Grace']]);

        foreach ([$this->ada, $this->grace, $this->linus] as $reader) {
            $this->assertControlAgrees(CommentControl::class, $reader, $this->comments(), ['view', 'update', 'delete']);
        }
    }

    #[Test]
    public function it_compares_the_list_of_each_ability_for_an_unrestricted_perimeter_inside_a_sql_boundary(): void
    {
        ConfiguredPostControl::bindWithin(new WorkspaceBoundary, GlobalPerimeter::new()
            ->allowed(fn (User $user, Ability $ability): bool => $user->is($this->ada) && ! $ability->is('delete'))
            ->unrestricted());

        $this->assertControlAgrees(PostControl::class, $this->ada, $this->posts(), self::ABILITIES);
    }

    #[Test]
    public function it_leaves_create_out_of_the_lists(): void
    {
        $this->grant(['view own posts' => ['Ada'], 'create own posts' => ['Ada']]);

        $this->assertControlAgrees(PostControl::class, $this->ada, $this->posts(), ['view', 'create']);
    }

    #[Test]
    public function it_fails_for_an_ability_that_a_policy_method_narrows_beyond_the_control(): void
    {
        Gate::policy(Post::class, NarrowedPostPolicy::class);
        $this->grant(['view client posts' => ['Ada'], 'update client posts' => ['Ada']]);

        try {
            $this->assertControlAgrees(PostControl::class, $this->ada, $this->posts(), ['view', 'update']);
            $this->fail('The narrowed policy method was not reported.');
        } catch (AssertionFailedError $failure) {
            $this->assertStringContainsString(
                sprintf('- row [%s], ability [update]: row by row [frozen], preload [frozen], withAbilities [frozen], controlled() [listed]', $this->roadmap->getKey()),
                $failure->getMessage(),
            );
            $this->assertStringNotContainsString('ability [view]', $failure->getMessage());
            $this->assertStringContainsString('move the rule into the control', $failure->getMessage());
        }
    }
}
