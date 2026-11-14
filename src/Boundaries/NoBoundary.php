<?php

namespace Lomkit\Access\Boundaries;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\AccessContext;

final class NoBoundary extends Boundary
{
    /** @param  Builder<covariant Model>  $query */
    #[\Override]
    protected function scope(Builder $query, AccessContext $context): void {}

    #[\Override]
    protected function includes(Model $row, AccessContext $context): bool
    {
        return true;
    }
}
