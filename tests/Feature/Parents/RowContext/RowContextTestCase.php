<?php

namespace Lomkit\Access\Tests\Feature\Parents\RowContext;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Feature\Parents\ParentTestCase;
use Lomkit\Access\Tests\Fixtures\Blog\Comment;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredCommentControl;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use Lomkit\Access\Tests\Fixtures\RowContext\RequestedBrands;
use Lomkit\Access\Tests\Fixtures\RowContext\RequestedBrandsBoundary;

abstract class RowContextTestCase extends ParentTestCase
{
    /** @var array<string, array<string, list<int>>> */
    protected array $rights = [
        'Ada' => ['view brand posts' => [1, 2], 'view comments' => [1, 2], 'create comments' => [1, 2]],
        'Grace' => ['view brand posts' => [2], 'view comments' => [1, 2], 'create comments' => [1, 2]],
        'Linus' => ['view brand posts' => [2], 'view comments' => [1]],
    ];

    protected RequestedBrands $brands;

    protected int $switches = 0;

    /** @var list<string> */
    protected array $postAllowedCalls = [];

    /** @var list<string> */
    protected array $parentAllowedCalls = [];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->brands = new RequestedBrands;
        $this->brands->request([1, 2]);
        $this->app->instance(RequestedBrands::class, $this->brands);
        Access::decisionContextUsing(fn (): array => $this->brands->decisionContext());
        $this->declareRowContext();

        $this->comment($this->elsewhere, $this->grace, 'Sister brand', 1);
        Comment::factory()
            ->for($this->releaseNotes)
            ->for($this->grace, 'author')
            ->createOne(['body' => 'No brand', 'workspace_id' => null]);

        ConfiguredPostControl::bindWithin(Boundary::none(), $this->postsOfTheRequestedBrands());
        ConfiguredCommentControl::bindWithin(new RequestedBrandsBoundary, $this->throughPost());
    }

    protected function declareRowContext(): void
    {
        Access::rowContextUsing(
            values: fn (): array => $this->brands->all(),
            within: function (int|string $brand, Closure $decide): mixed {
                $this->switches++;

                return $this->brands->actingIn([(int) $brand], $decide);
            },
        );
    }

    protected function aloneIn(int|string $brand, Closure $check): mixed
    {
        $requested = $this->brands->all();
        $this->brands->request([(int) $brand]);

        try {
            return $check();
        } finally {
            $this->brands->request($requested);
        }
    }

    protected function throughPost(): ThroughParent
    {
        return $this->plainThroughPost()->decidedInRowContext('workspace_id');
    }

    protected function plainThroughPost(): ThroughParent
    {
        return ThroughParent::belongsTo('post', inverse: 'comments')
            ->delegates(['view', 'create' => 'view'])
            ->allowed(function (User $user, Ability $ability): bool {
                $this->parentAllowedCalls[] = "{$user->name}:{$ability}:".implode(',', $this->brands->all());

                return $this->brandsWhere($user, "{$ability} comments") !== [];
            });
    }

    protected function postsOfTheRequestedBrands(): Perimeter
    {
        return GlobalPerimeter::new()
            ->allowed(function (User $user, Ability $ability): bool {
                $this->postAllowedCalls[] = "{$user->name}:{$ability}:".implode(',', $this->brands->all());

                return $this->brandsWhere($user, "{$ability} brand posts") !== [];
            })
            ->should(fn (User $user, Post $post, Ability $ability): bool => in_array($post->getAttribute('workspace_id'), $this->brandsWhere($user, "{$ability} brand posts"), true))
            ->query(function (Builder $query, User $user, Ability $ability): void {
                $query->whereIn('workspace_id', $this->brandsWhere($user, "{$ability} brand posts"));
            });
    }

    protected function postsOfTheRowBrand(): Perimeter
    {
        return GlobalPerimeter::new()
            ->allowed(fn (User $user, Ability $ability): bool => $this->rightsOf($user, "{$ability} brand posts") !== [])
            ->should(fn (User $user, Post $post, Ability $ability): bool => in_array($post->getAttribute('workspace_id'), $this->rightsOf($user, "{$ability} brand posts"), true))
            ->query(function (Builder $query, User $user, Ability $ability): void {
                $query->whereIn('workspace_id', $this->rightsOf($user, "{$ability} brand posts"));
            });
    }

    /** @return list<int> */
    protected function rightsOf(User $user, string $permission): array
    {
        return $this->rights[$user->name][$permission] ?? [];
    }

    /** @return list<int> */
    protected function brandsWhere(User $user, string $permission): array
    {
        return $this->brands->within($this->rightsOf($user, $permission));
    }

    protected function code(?User $reader, string $ability, mixed $subject): string
    {
        $response = Gate::forUser($reader)->inspect($ability, $subject);

        return $response->allowed() ? 'granted' : (string) $response->code();
    }

    protected function draftIn(Post $post, User $author, ?int $brand): Comment
    {
        return new Comment(['post_id' => $post->getKey(), 'author_id' => $author->getKey(), 'body' => 'Draft', 'workspace_id' => $brand]);
    }
}
