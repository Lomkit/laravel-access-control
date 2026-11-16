<?php

namespace Lomkit\Access\Tests\Fixtures\Types;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Fixtures\Blog\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\User;

class TypedPostControl extends Control
{
    protected string $model = Post::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            PublishedPerimeter::new(),
            OwnPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->query(function (Builder $query, User $user): void {
                    PostRows::publishedBy($query, $user);
                }),
        ];
    }

    #[\Override]
    protected function boundary(): Boundary
    {
        return new VisibleBoundary;
    }
}
