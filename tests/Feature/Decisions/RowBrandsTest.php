<?php

namespace Lomkit\Access\Tests\Feature\Decisions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Testing\InteractsWithAccess;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\PostControl;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use PHPUnit\Framework\Attributes\Test;

class RowBrandsTest extends DecisionTestCase
{
    use InteractsWithAccess;

    private const array RIGHTS = [
        'Ada' => ['view brand posts' => [1], 'update brand posts' => [1], 'view own posts' => [1, 2]],
    ];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $website = Project::query()->where('name', 'Website')->firstOrFail();
        Post::factory()->for($website)->for($this->grace, 'author')->createOne(['title' => 'Grace elsewhere', 'workspace_id' => 2]);
        Post::factory()->for($website)->for($this->ada, 'author')->createOne(['title' => 'Third brand', 'workspace_id' => 3]);
        Access::decisionContextUsing(fn (): array => ['brand' => 1]);
    }

    #[Test]
    public function it_lists_the_rows_of_several_brands_in_one_query_with_additive_perimeters(): void
    {
        ConfiguredPostControl::bindWithin(Boundary::none(), $this->brandPosts(), $this->ownPosts());

        $this->assertSame(['Release notes', 'Roadmap', 'Pricing', 'Elsewhere'], $this->titles(Post::controlledFor($this->ada)));
        $this->assertSame(['Release notes', 'Roadmap', 'Pricing'], $this->titles(Post::controlledFor($this->ada, 'update')));
    }

    #[Test]
    public function it_lists_and_decides_the_same_rows_whatever_the_current_brand(): void
    {
        ConfiguredPostControl::bindWithin(Boundary::none(), $this->brandPosts(), $this->ownPosts());
        $posts = Post::query()->orderBy('id')->get();

        Access::decisionContextUsing(fn (): array => ['brand' => 2]);

        $this->assertSame(['Release notes', 'Roadmap', 'Pricing', 'Elsewhere'], $this->titles(Post::controlledFor($this->ada)));
        $this->assertControlAgrees(PostControl::class, $this->ada, $posts, ['view', 'update']);
    }

    #[Test]
    public function it_decides_each_row_of_several_brands_as_the_list_does(): void
    {
        ConfiguredPostControl::bindWithin(Boundary::none(), $this->brandPosts(), $this->ownPosts());
        $posts = Post::query()->orderBy('id')->get();

        $this->assertControlAgrees(PostControl::class, $this->ada, $posts, ['view', 'update']);
        $this->assertSame(
            ['Release notes', 'Roadmap', 'Pricing', 'Elsewhere'],
            $posts->filter(fn (Post $post): bool => Gate::forUser($this->ada)->allows('view', $post))->pluck('title')->values()->all(),
        );
    }

    #[Test]
    public function it_loses_the_rows_of_a_lower_level_in_another_brand_inside_levels(): void
    {
        ConfiguredPostControl::bindWithin(Boundary::none(), Levels::of($this->brandPosts(), $this->ownPosts()));

        $this->assertSame(['Release notes', 'Roadmap', 'Pricing'], $this->titles(Post::controlledFor($this->ada)));
    }

    private function brandPosts(): Perimeter
    {
        return GlobalPerimeter::new()
            ->allowed(fn (User $user, Ability $ability): bool => self::brandsWhere($user, "{$ability} brand posts") !== [])
            ->should(fn (User $user, Post $post, Ability $ability): bool => in_array($post->workspace_id, self::brandsWhere($user, "{$ability} brand posts"), true))
            ->query(function (Builder $query, User $user, Ability $ability): void {
                $query->whereIn('workspace_id', self::brandsWhere($user, "{$ability} brand posts"));
            });
    }

    private function ownPosts(): Perimeter
    {
        return OwnPerimeter::new()
            ->allowed(fn (User $user, Ability $ability): bool => self::brandsWhere($user, "{$ability} own posts") !== [])
            ->should(fn (User $user, Post $post, Ability $ability): bool => $post->author_id === $user->getKey() && in_array($post->workspace_id, self::brandsWhere($user, "{$ability} own posts"), true))
            ->query(function (Builder $query, User $user, Ability $ability): void {
                $query->whereBelongsTo($user, 'author')->whereIn('workspace_id', self::brandsWhere($user, "{$ability} own posts"));
            });
    }

    /** @return list<int> */
    private static function brandsWhere(User $user, string $permission): array
    {
        return self::RIGHTS[$user->name][$permission] ?? [];
    }

    /**
     * @param  Builder<Post>  $query
     * @return list<string>
     */
    private function titles(Builder $query): array
    {
        return $query->orderBy('id')->pluck('title')->all();
    }
}
