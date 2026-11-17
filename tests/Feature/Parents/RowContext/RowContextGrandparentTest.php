<?php

namespace Lomkit\Access\Tests\Feature\Parents\RowContext;

use Lomkit\Access\Ability;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Fixtures\Blog\Comment;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use Lomkit\Access\Tests\Fixtures\ConfiguredProjectControl;
use Lomkit\Access\Tests\Fixtures\Inheritance\Controls\CommentControl;
use PHPUnit\Framework\Attributes\Test;

class RowContextGrandparentTest extends RowContextTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->rights['Grace']['view brand projects'] = [2];
        $this->rights['Ada']['view brand projects'] = [1, 2];
        ConfiguredProjectControl::bind(GlobalPerimeter::new()
            ->allowed(function (User $user, Ability $ability): bool {
                $this->postAllowedCalls[] = "{$user->name}:{$ability}:".implode(',', $this->brands->all());

                return $this->brandsWhere($user, "{$ability} brand projects") !== [];
            })
            ->unrestricted());
        ConfiguredPostControl::bindWithin(Boundary::none(), ThroughParent::belongsTo('project', inverse: 'posts')->decidedInRowContext('workspace_id'));
    }

    #[Test]
    public function it_decides_the_grandparent_within_the_value_its_parent_is_decided_in(): void
    {
        $this->assertSame(['Wrong workspace'], $this->visibleComments($this->grace));
        $this->assertSame(['Grace:view:1', 'Grace:view:2'], $this->postAllowedCalls);
        $this->assertSame(['Ship it', 'Typo in the title', 'Add search', 'Old news', 'Wrong workspace', 'Too expensive'], $this->visibleComments($this->ada));

        foreach ([$this->ada, $this->grace] as $reader) {
            $comments = Comment::query()->orderBy('id')->get();

            $this->assertControlAgrees(CommentControl::class, $reader, $comments, ['view']);
            $this->assertRowContextAgrees(CommentControl::class, $reader, $comments, alone: $this->aloneIn(...), abilities: ['view']);
        }
    }

    #[Test]
    public function it_lists_the_posts_of_each_brand_through_the_projects_readable_in_that_brand(): void
    {
        $this->assertSame(['Elsewhere'], Post::controlledFor($this->grace)->pluck('title')->all());
    }
}
