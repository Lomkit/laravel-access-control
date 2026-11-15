<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Search;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Boundaries\Boundary;

class HiddenBoundary extends Boundary
{
    /** @param  Builder<covariant Model>  $query */
    #[\Override]
    protected function scope(Builder $query, AccessContext $context): void
    {
        $query->where('hidden', false);
    }
}
