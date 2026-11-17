<?php

namespace Lomkit\Access\Tests\Feature\Parents;

use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Access;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Fixtures\Blog\Comment;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\RequestBrandsBoundary;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredCommentControl;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use Lomkit\Access\Tests\Fixtures\Inheritance\Controls\CommentControl;
use PHPUnit\Framework\Attributes\Test;

class ParentUnderSeveralBrandsTest extends ParentTestCase
{
    private string $brands = '1,2';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Access::decisionContextUsing(fn (): array => ['brands' => $this->brands]);
        ConfiguredPostControl::bindWithin(new RequestBrandsBoundary, GlobalPerimeter::new()
            ->allowed(fn (User $user): bool => $user->is($this->ada))
            ->unrestricted());
        ConfiguredCommentControl::bindWithin(new RequestBrandsBoundary, ThroughParent::belongsTo('post'));
    }

    #[Test]
    public function it_decides_a_parent_that_reads_the_brands_of_the_request_under_all_of_them(): void
    {
        $this->assertSame(
            ['Ship it', 'Typo in the title', 'Add search', 'Old news', 'Wrong workspace', 'Too expensive', 'Agreed'],
            $this->visibleComments($this->ada),
        );
        $this->assertTrue(Gate::forUser($this->ada)->allows('view', $this->commentNamed('Agreed')));
        $this->assertControlAgrees(CommentControl::class, $this->ada, Comment::query()->orderBy('id')->get(), ['view']);
    }

    #[Test]
    public function it_lists_under_neither_brand_alone_a_child_of_one_brand_whose_parent_is_readable_in_the_other(): void
    {
        $this->brands = '1';

        $this->assertSame(['Ship it', 'Typo in the title', 'Add search', 'Old news', 'Too expensive'], $this->visibleComments($this->ada));
        $this->assertFalse(Gate::forUser($this->ada)->allows('view', $this->commentNamed('Agreed')));

        $this->brands = '2';

        $this->assertSame(['Wrong workspace'], $this->visibleComments($this->ada));
        $this->assertFalse(Gate::forUser($this->ada)->allows('view', $this->commentNamed('Agreed')));
    }

    #[Test]
    public function it_decides_the_child_again_under_the_context_in_force_after_a_switch_without_forgetting(): void
    {
        $agreed = $this->commentNamed('Agreed');
        Access::preload($this->ada, 'view', [$agreed]);
        $this->assertTrue(Gate::forUser($this->ada)->allows('view', $agreed));

        $this->brands = '2';

        $this->assertFalse(Gate::forUser($this->ada)->allows('view', $agreed));
        $this->assertSame(['Wrong workspace'], Comment::controlledFor($this->ada)
            ->withAbilities(['view'])
            ->orderBy('id')
            ->pluck('body')
            ->all());
        Access::preload($this->ada, 'view', [$agreed]);
        $this->assertFalse(Gate::forUser($this->ada)->allows('view', $agreed));
        $this->assertControlAgrees(CommentControl::class, $this->ada, Comment::query()->orderBy('id')->get(), ['view']);

        $this->brands = '1,2';

        $this->assertTrue(Gate::forUser($this->ada)->allows('view', $agreed));
    }
}
