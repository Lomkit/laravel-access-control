<?php

namespace Lomkit\Access\Tests\Fixtures\Types;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Post;

class PublishedPerimeter extends Perimeter
{
    #[\Override]
    protected function allows(AccessContext $context): bool
    {
        return true;
    }

    #[\Override]
    protected function includes(Model $row, AccessContext $context): bool
    {
        return $row instanceof Post && $row->getAttribute('published') === true;
    }

    #[\Override]
    protected function scope(Builder $query, AccessContext $context): void
    {
        PostRows::visible($query);
        PostRows::of(Post::class, $query)->published();
    }
}
