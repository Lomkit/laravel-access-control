<?php

namespace Lomkit\Access\Tests\Fixtures\Check\DraftsGranted;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Perimeters\Perimeter;

final class AuthoredPerimeter extends Perimeter
{
    #[\Override]
    protected function allows(AccessContext $context): bool
    {
        return true;
    }

    #[\Override]
    protected function includes(Model $row, AccessContext $context): bool
    {
        return $row->getAttribute('author_id') === $context->reader()->getKey();
    }

    /** @param  Builder<covariant Model>  $query */
    #[\Override]
    protected function scope(Builder $query, AccessContext $context): void
    {
        $query->where('author_id', $context->reader()->getKey());
    }
}
